<?php
/**
 * Core stock consumption engine for raw material processing. Supports two
 * modes: FIFO (oldest active lot first, across as many lots as needed to
 * meet a chosen Jumbo requirement) and LCFO (the single most-recently
 * created active lot, taken whole). No manual lot selection in either mode.
 */
class FifoProcessor
{
    /**
     * Works out which lots would be consumed for a given requirement,
     * WITHOUT writing anything to the database. Used to show a preview
     * before the user confirms.
     *
     * @return array{allocations: array, shortfall: int}
     */
    public static function preview(PDO $pdo, int $rawMaterialId, int $requirementJumbo): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, lot_no, remaining_jumbo, per_jumbo_weight, remaining_mt
             FROM raw_material_stock
             WHERE raw_material_id = :rm AND status = 'active' AND remaining_jumbo > 0
             ORDER BY id ASC"
        );
        $stmt->execute([':rm' => $rawMaterialId]);
        $lots = $stmt->fetchAll();

        $remainingNeeded = $requirementJumbo;
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remainingNeeded <= 0) break;

            $take = min((int) $lot['remaining_jumbo'], $remainingNeeded);
            if ($take <= 0) continue;

            $mtTaken = round($take * (float) $lot['per_jumbo_weight'], 6);

            $allocations[] = [
                'stock_id'         => $lot['id'],
                'lot_no'           => $lot['lot_no'],
                'jumbo_taken'      => $take,
                'per_jumbo_weight' => $lot['per_jumbo_weight'],
                'mt_taken'         => $mtTaken,
            ];

            $remainingNeeded -= $take;
        }

        return [
            'allocations' => $allocations,
            'shortfall'   => max(0, $remainingNeeded),
        ];
    }

    /**
     * LCFO (Last Come First Out): finds the single most-recently-created
     * active lot for this raw material and takes it whole - the entire
     * remaining Jumbo of that one lot, no partial fill, nothing for the
     * user to type. Returns null if there is no active stock at all.
     *
     * @return array{allocations: array, requirement_jumbo: int}|null
     */
    public static function previewLcfo(PDO $pdo, int $rawMaterialId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, lot_no, remaining_jumbo, per_jumbo_weight
             FROM raw_material_stock
             WHERE raw_material_id = :rm AND status = 'active' AND remaining_jumbo > 0
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':rm' => $rawMaterialId]);
        $lot = $stmt->fetch();
        if (!$lot) {
            return null;
        }

        $jumbo = (int) $lot['remaining_jumbo'];
        return [
            'allocations' => [[
                'stock_id'         => $lot['id'],
                'lot_no'           => $lot['lot_no'],
                'jumbo_taken'      => $jumbo,
                'per_jumbo_weight' => $lot['per_jumbo_weight'],
                'mt_taken'         => round($jumbo * (float) $lot['per_jumbo_weight'], 6),
            ]],
            'requirement_jumbo' => $jumbo,
        ];
    }

    /**
     * Actually consumes stock: creates the processing_requests row,
     * one processing_lot_consumption row per lot touched, decrements
     * raw_material_stock, and writes a stock_ledger entry per lot.
     * Runs entirely inside one DB transaction.
     *
     * @throws Exception if stock is insufficient
     * @return int the new processing_requests.id
     */
    public static function process(
        PDO $pdo,
        int $rawMaterialId,
        int $requirementJumbo,
        string $processingDate,
        int $userId
    ): int {
        $preview = self::preview($pdo, $rawMaterialId, $requirementJumbo);

        if ($preview['shortfall'] > 0) {
            throw new Exception(
                'Insufficient stock: short by ' . $preview['shortfall'] . ' Jumbo for this raw material.'
            );
        }

        return self::commitAllocations($pdo, $rawMaterialId, $requirementJumbo, $preview['allocations'], $processingDate, $userId);
    }

    /**
     * @throws Exception if there is no active stock to consume
     * @return int the new processing_requests.id
     */
    public static function processLcfo(PDO $pdo, int $rawMaterialId, string $processingDate, int $userId): int
    {
        $preview = self::previewLcfo($pdo, $rawMaterialId);
        if (!$preview) {
            throw new Exception('No active stock available for this raw material.');
        }

        return self::commitAllocations($pdo, $rawMaterialId, $preview['requirement_jumbo'], $preview['allocations'], $processingDate, $userId);
    }

    private static function commitAllocations(
        PDO $pdo,
        int $rawMaterialId,
        int $requirementJumbo,
        array $allocations,
        string $processingDate,
        int $userId
    ): int {
        $pdo->beginTransaction();
        try {
            $totalMt = 0.0;
            foreach ($allocations as $a) {
                $totalMt += $a['mt_taken'];
            }
            $totalMt = round($totalMt, 6);

            $stmt = $pdo->prepare(
                "INSERT INTO processing_requests
                    (raw_material_id, requirement_jumbo, total_mt_consumed, processing_date, created_by)
                 VALUES (:rm, :req, :mt, :pdate, :uid)"
            );
            $stmt->execute([
                ':rm'    => $rawMaterialId,
                ':req'   => $requirementJumbo,
                ':mt'    => $totalMt,
                ':pdate' => $processingDate,
                ':uid'   => $userId,
            ]);
            $processingId = (int) $pdo->lastInsertId();

            foreach ($allocations as $a) {
                // Record the consumption line
                $stmt = $pdo->prepare(
                    "INSERT INTO processing_lot_consumption
                        (processing_id, stock_id, jumbo_consumed, mt_consumed)
                     VALUES (:pid, :sid, :jumbo, :mt)"
                );
                $stmt->execute([
                    ':pid'   => $processingId,
                    ':sid'   => $a['stock_id'],
                    ':jumbo' => $a['jumbo_taken'],
                    ':mt'    => $a['mt_taken'],
                ]);

                // Lock and fetch current stock row to compute previous/new for the ledger
                $stmt = $pdo->prepare("SELECT * FROM raw_material_stock WHERE id = :id FOR UPDATE");
                $stmt->execute([':id' => $a['stock_id']]);
                $stock = $stmt->fetch();

                $newRemainingJumbo = $stock['remaining_jumbo'] - $a['jumbo_taken'];
                $newRemainingMt    = round($stock['remaining_mt'] - $a['mt_taken'], 6);
                $newStatus         = $newRemainingJumbo <= 0 ? 'exhausted' : 'active';

                $stmt = $pdo->prepare(
                    "UPDATE raw_material_stock
                     SET remaining_jumbo = :rj, remaining_mt = :rm, status = :status
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':rj'     => $newRemainingJumbo,
                    ':rm'     => $newRemainingMt,
                    ':status' => $newStatus,
                    ':id'     => $a['stock_id'],
                ]);

                logStockMovement(
                    $pdo,
                    'processing_out',
                    'processing_lot_consumption',
                    $processingId,
                    'raw',
                    $a['mt_taken'],
                    'MT',
                    $stock['remaining_mt'],
                    $newRemainingMt,
                    $userId,
                    'Lot ' . $a['lot_no'] . ' consumed (' . $a['jumbo_taken'] . ' Jumbo) for processing #' . $processingId
                );
            }

            $pdo->commit();
            return $processingId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

<?php
/**
 * Labour attendance helpers. Admin manages the roster from Settings ->
 * Labour; Plant Head marks each labourer's daily status (Full Day /
 * Absent) from modules/labour/attendance.php; Admin reviews it from
 * modules/admin/labour-status.php.
 */

/** Every active labourer, each annotated with their attendance status for the given date (null if not yet marked). */
function getLabourAttendanceForDate(PDO $pdo, string $date): array {
    $labour = $pdo->query("SELECT * FROM labour WHERE status='active' ORDER BY name")->fetchAll();
    $stmt = $pdo->prepare("SELECT status FROM labour_attendance WHERE labour_id=:id AND attendance_date=:d");
    foreach ($labour as &$l) {
        $stmt->execute([':id' => $l['id'], ':d' => $date]);
        $l['attendance_status'] = $stmt->fetchColumn() ?: null;
    }
    unset($l);
    return $labour;
}

function labourStatusLabel(?string $status): string {
    $map = ['full_day' => 'Full Day', 'absent' => 'Absent'];
    return $map[$status] ?? 'Not Marked';
}

/** What a labourer earns for a given attendance status - full rate for Full Day, 0 otherwise. */
function labourWageForStatus(?float $dailyWage, ?string $status): float {
    if (!$dailyWage || $status !== 'full_day') return 0.0;
    return $dailyWage;
}

/**
 * Per-labourer production bonus for a day's total MT output (see the
 * "Production Bonus Scheme" card on modules/utilities/labour-cost.php,
 * Admin-set thresholdMt/baseAmount/perExtraMt). Below the threshold, no
 * bonus. At or above it, everyone who worked that day gets the flat base
 * amount plus a per-labourer rate for each MT produced beyond the
 * threshold - e.g. threshold 30, base Rs100, rate Rs10: 35MT produced ->
 * Rs100 + 5*Rs10 = Rs150 per labourer who worked.
 */
function labourBonusPerHead(float $dailyMt, float $thresholdMt, float $baseAmount, float $perExtraMt): float {
    if ($thresholdMt <= 0 || $dailyMt < $thresholdMt) return 0.0;
    return $baseAmount + ($dailyMt - $thresholdMt) * $perExtraMt;
}

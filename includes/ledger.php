<?php
/**
 * Writes one row to stock_ledger. Every module that moves stock
 * (inward, processing, packing finish, damage, reprocessing, garbage)
 * must call this - never write ledger rows ad hoc inside a module.
 */
function logStockMovement(
    PDO $pdo,
    $type,            // inward | processing_out | packing_finish | damage | reprocessing | garbage
    $refTable,
    $refId,
    $materialType,    // raw | finished
    $quantity,
    $unit,
    $previousStock,
    $newStock,
    $userId,
    $remarks = null
) {
    $stmt = $pdo->prepare(
        "INSERT INTO stock_ledger
            (transaction_type, reference_table, reference_id, material_type, quantity, unit,
             previous_stock, new_stock, transaction_date, user_id, remarks)
         VALUES
            (:type, :ref_table, :ref_id, :material_type, :quantity, :unit,
             :previous_stock, :new_stock, NOW(), :user_id, :remarks)"
    );
    $stmt->execute([
        ':type'           => $type,
        ':ref_table'      => $refTable,
        ':ref_id'         => $refId,
        ':material_type'  => $materialType,
        ':quantity'       => $quantity,
        ':unit'           => $unit,
        ':previous_stock' => $previousStock,
        ':new_stock'      => $newStock,
        ':user_id'        => $userId,
        ':remarks'        => $remarks,
    ]);
}

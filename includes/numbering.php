<?php
/**
 * MartPro Sequential Numbering Generator
 * Atomic document number generation with database row locking
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/database.php';

function next_document_number(string $prefix): string {
    $db = get_db();
    
    // Acquire row-level lock for atomic sequential counter
    $stmt = $db->prepare("SELECT current_number FROM numbering_sequences WHERE prefix = :prefix FOR UPDATE");
    $stmt->execute(['prefix' => $prefix]);
    $row = $stmt->fetch();

    if ($row) {
        $next_num = (int)$row['current_number'] + 1;
        $update = $db->prepare("UPDATE numbering_sequences SET current_number = :num WHERE prefix = :prefix");
        $update->execute(['num' => $next_num, 'prefix' => $prefix]);
    } else {
        $next_num = 1001;
        $insert = $db->prepare("INSERT INTO numbering_sequences (prefix, current_number) VALUES (:prefix, :num)");
        $insert->execute(['prefix' => $prefix, 'num' => $next_num]);
    }

    $year = date('y');
    return sprintf("%s-%s-%05d", $prefix, $year, $next_num);
}

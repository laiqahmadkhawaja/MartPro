<?php
/**
 * MartPro Double-Entry Accounting Engine
 * Balanced journal entries generation with integrity validation
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/numbering.php';

function post_journal_entry(
    string $description,
    string $source_doc,
    array $lines,
    ?string $entry_date = null
): int {
    $db = get_db();
    $entry_date = $entry_date ?: date('Y-m-d');
    $user_id = current_user_id();

    // Verify debit == credit balance
    $total_debit = 0.0;
    $total_credit = 0.0;
    foreach ($lines as $line) {
        $total_debit += (float)($line['debit'] ?? 0);
        $total_credit += (float)($line['credit'] ?? 0);
    }

    // Check with 2-decimal epsilon
    if (abs($total_debit - $total_credit) > 0.01) {
        throw new Exception("Unbalanced Journal Entry: Debits ($total_debit) do not equal Credits ($total_credit).");
    }

    $entry_num = 'JRN-' . date('y') . '-' . substr(uniqid(), -6);

    $stmt = $db->prepare("INSERT INTO journal_entries 
        (entry_number, entry_date, source_document, description, created_by, created_at)
        VALUES (:num, :edate, :src, :desc, :uid, NOW())");
    $stmt->execute([
        'num'   => $entry_num,
        'edate' => $entry_date,
        'src'   => $source_doc,
        'desc'  => $description,
        'uid'   => $user_id
    ]);
    $entry_id = (int)$db->lastInsertId();

    $line_stmt = $db->prepare("INSERT INTO journal_entry_lines 
        (journal_entry_id, account_id, debit, credit, memo)
        VALUES (:jid, :aid, :dr, :cr, :memo)");

    foreach ($lines as $line) {
        $line_stmt->execute([
            'jid'  => $entry_id,
            'aid'  => $line['account_id'],
            'dr'   => (float)($line['debit'] ?? 0),
            'cr'   => (float)($line['credit'] ?? 0),
            'memo' => $line['memo'] ?? null
        ]);
    }

    return $entry_id;
}

/**
 * Get account ID by code
 */
function get_account_id(string $code): int {
    static $acc_cache = [];
    if (!isset($acc_cache[$code])) {
        $db = get_db();
        $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();
        $acc_cache[$code] = $id ? (int)$id : 1;
    }
    return $acc_cache[$code];
}

/**
 * Post accounting entries for a completed retail sale
 */
function post_sale_accounting(int $sale_id, string $inv_num, float $subtotal, float $tax, float $paid_cash, float $paid_card, float $credit_khata, float $cogs, float $paid_loyalty = 0.0): void {
    try {
        $lines = [];
        // 1. Cash received
        if ($paid_cash > 0) {
            $lines[] = [
                'account_id' => get_account_id('1010'), // Cash on Hand
                'debit'      => $paid_cash,
                'credit'     => 0,
                'memo'       => "Cash received for $inv_num"
            ];
        }
        // 2. Card / Digital received
        if ($paid_card > 0) {
            $lines[] = [
                'account_id' => get_account_id('1030'), // Bank Operating Account
                'debit'      => $paid_card,
                'credit'     => 0,
                'memo'       => "Card/Digital payment for $inv_num"
            ];
        }
        // 3. Customer credit (Khata)
        if ($credit_khata > 0) {
            $lines[] = [
                'account_id' => get_account_id('1100'), // Accounts Receivable
                'debit'      => $credit_khata,
                'credit'     => 0,
                'memo'       => "Customer credit for $inv_num"
            ];
        }
        // 4. Loyalty Points Redeemed
        if ($paid_loyalty > 0) {
            $lines[] = [
                'account_id' => get_account_id('4020'), // Sales Discounts & Promotions
                'debit'      => $paid_loyalty,
                'credit'     => 0,
                'memo'       => "Loyalty points redeemed for $inv_num"
            ];
        }
        // 5. Sales Revenue
        $lines[] = [
            'account_id' => get_account_id('4010'), // Sales Revenue
            'debit'      => 0,
            'credit'     => $subtotal,
            'memo'       => "Retail sales revenue for $inv_num"
        ];
        // 6. Sales Tax Payable
        if ($tax > 0) {
            $lines[] = [
                'account_id' => get_account_id('2020'), // Sales Tax Payable
                'debit'      => 0,
                'credit'     => $tax,
                'memo'       => "Tax collected for $inv_num"
            ];
        }

        // Post Revenue / Cash entry
        post_journal_entry("POS Sale $inv_num", $inv_num, $lines);

        // COGS and Inventory Reduction entry
        if ($cogs > 0) {
            $cogs_lines = [
                [
                    'account_id' => get_account_id('5010'), // COGS
                    'debit'      => $cogs,
                    'credit'     => 0,
                    'memo'       => "Cost of Goods Sold for $inv_num"
                ],
                [
                    'account_id' => get_account_id('1200'), // Merchandise Inventory
                    'debit'      => 0,
                    'credit'     => $cogs,
                    'memo'       => "Inventory deduction for $inv_num"
                ]
            ];
            post_journal_entry("COGS for Sale $inv_num", $inv_num, $cogs_lines);
        }
    } catch (Exception $e) {
        error_log("Failed to post sale accounting: " . $e->getMessage());
    }
}

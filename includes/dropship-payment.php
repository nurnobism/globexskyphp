<?php
/**
 * GlobexSky Dropship Payment Split Calculator
 *
 * When a customer buys from a dropshipper's store:
 *
 * Total Paid = Selling Price (supplier_price + markup)
 *
 * Split:
 * 1. Platform Commission = supplier_price × commission_rate
 * 2. Platform Dropship Fee = selling_price × 3%
 * 3. Supplier Earning = supplier_price − platform_commission
 * 4. Dropshipper Earning = markup_amount − platform_dropship_fee
 * 5. Platform Total = platform_commission + platform_dropship_fee
 */

/**
 * Calculate the payment split for a dropship sale.
 *
 * @param float $supplierPrice   Original supplier price
 * @param float $sellingPrice    Customer-facing price (includes markup)
 * @param float $commissionRate  Supplier commission rate (0–1, e.g. 0.10)
 * @param float $dropshipFeeRate Platform dropship fee rate (default 0.03)
 * @return array Split breakdown
 */
function calculateDropshipSplit(
    float $supplierPrice,
    float $sellingPrice,
    float $commissionRate = 0.10,
    float $dropshipFeeRate = 0.03
): array {
    $markupAmount        = round($sellingPrice - $supplierPrice, 2);
    $platformCommission  = round($supplierPrice * $commissionRate, 2);
    $platformDropshipFee = round($sellingPrice * $dropshipFeeRate, 2);
    $supplierEarning     = round($supplierPrice - $platformCommission, 2);
    $dropshipperEarning  = round($markupAmount - $platformDropshipFee, 2);
    $platformTotal       = round($platformCommission + $platformDropshipFee, 2);

    return [
        'supplier_price'        => round($supplierPrice, 2),
        'selling_price'         => round($sellingPrice, 2),
        'markup_amount'         => $markupAmount,
        'commission_rate'       => $commissionRate,
        'platform_commission'   => $platformCommission,
        'dropship_fee_rate'     => $dropshipFeeRate,
        'platform_dropship_fee' => $platformDropshipFee,
        'supplier_earning'      => $supplierEarning,
        'dropshipper_earning'   => $dropshipperEarning,
        'platform_total'        => $platformTotal,
    ];
}

/**
 * Record a payment split in dropship_orders.
 */
function recordDropshipSplit(int $orderId, array $split): bool
{
    $db = getDB();
    try {
        $db->prepare('UPDATE dropship_orders SET
            platform_dropship_fee = ?,
            dropshipper_earning   = ?,
            supplier_earning      = ?,
            platform_earning      = ?
            WHERE order_id = ?')
           ->execute([
               $split['platform_dropship_fee'],
               $split['dropshipper_earning'],
               $split['supplier_earning'],
               $split['platform_total'],
               $orderId,
           ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get the available payout balance for a dropshipper.
 */
function getDropshipperBalance(int $dropshipperId): float
{
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM dropship_earnings
            WHERE dropshipper_id = ? AND status = 'available'");
        $stmt->execute([$dropshipperId]);
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Request a payout for the dropshipper.
 * Returns ['success' => bool, 'error' => string|null]
 */
function requestDropshipPayout(int $dropshipperId, float $amount, string $method = 'bank'): array
{
    $minPayout = 50.0;
    if ($amount < $minPayout) {
        return ['success' => false, 'error' => "Minimum payout amount is \$$minPayout"];
    }

    $balance = getDropshipperBalance($dropshipperId);
    if ($amount > $balance) {
        return ['success' => false, 'error' => 'Insufficient available balance'];
    }

    $db = getDB();
    try {
        // Mark earnings as requested (process all available earnings in batches)
        $db->prepare("UPDATE dropship_earnings SET status = 'requested'
            WHERE dropshipper_id = ? AND status = 'available'")
           ->execute([$dropshipperId]);

        // Create payout request (using existing payouts table if exists)
        try {
            $db->prepare('INSERT INTO payouts (user_id, amount, method, status, type, created_at)
                VALUES (?,?,?,"pending","dropship", NOW())')
               ->execute([$dropshipperId, $amount, $method]);
        } catch (PDOException $e) { /* payouts table may differ */ }

        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Process a dropship payout (admin action).
 */
function processDropshipPayout(int $payoutId): bool
{
    $db = getDB();
    try {
        $db->prepare("UPDATE payouts SET status = 'paid', paid_at = NOW() WHERE id = ?")
           ->execute([$payoutId]);

        // Mark related earnings as paid
        $stmt = $db->prepare('SELECT user_id FROM payouts WHERE id = ?');
        $stmt->execute([$payoutId]);
        $payout = $stmt->fetch();
        if ($payout) {
            $db->prepare("UPDATE dropship_earnings SET status = 'paid', paid_at = NOW()
                WHERE dropshipper_id = ? AND status = 'requested'")
               ->execute([$payout['user_id']]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

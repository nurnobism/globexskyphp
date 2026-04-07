<?php
/**
 * includes/stripe-handler.php — Stripe Payment Handler (PR #6)
 *
 * Uses cURL for Stripe API calls (no Composer dependency required).
 * All keys loaded from DB system_settings / env — never hardcoded.
 *
 * Functions:
 *   getStripeKeys()
 *   createPaymentIntent($orderId, $amount, $currency)
 *   confirmPayment($paymentIntentId)
 *   handleWebhook($payload, $sigHeader)
 *   refundPayment($paymentIntentId, $amount)
 */

// Stripe API version used for all requests
define('STRIPE_API_VERSION',          '2023-10-16');
define('STRIPE_API_BASE',             'https://api.stripe.com/v1');

// Grace period in days after a subscription payment fails before downgrading
define('PLAN_PAYMENT_GRACE_PERIOD_DAYS', 3);

// ---------------------------------------------------------------------------
// Key Management
// ---------------------------------------------------------------------------

/**
 * Get Stripe publishable and secret keys from DB system_settings,
 * falling back to environment constants.
 *
 * @return array{publishable_key:string, secret_key:string, webhook_secret:string, mode:string}
 */
function getStripeKeys(): array
{
    static $keys = null;
    if ($keys !== null) return $keys;

    $keys = [
        'publishable_key' => '',
        'secret_key'      => '',
        'webhook_secret'  => '',
        'mode'            => 'test',
    ];

    try {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT setting_key, setting_value FROM system_settings
             WHERE setting_key IN (
                 'stripe_publishable_key','stripe_secret_key',
                 'stripe_webhook_secret','stripe_mode'
             )"
        );
        foreach ($stmt->fetchAll() as $row) {
            $map = [
                'stripe_publishable_key' => 'publishable_key',
                'stripe_secret_key'      => 'secret_key',
                'stripe_webhook_secret'  => 'webhook_secret',
                'stripe_mode'            => 'mode',
            ];
            if (isset($map[$row['setting_key']])) {
                $keys[$map[$row['setting_key']]] = (string)$row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        error_log('getStripeKeys DB error: ' . $e->getMessage());
    }

    // Fall back to env / config constants
    if (empty($keys['publishable_key']) && defined('STRIPE_PUBLISHABLE_KEY')) {
        $keys['publishable_key'] = STRIPE_PUBLISHABLE_KEY;
    }
    if (empty($keys['secret_key']) && defined('STRIPE_SECRET_KEY')) {
        $keys['secret_key'] = STRIPE_SECRET_KEY;
    }
    if (empty($keys['webhook_secret']) && defined('STRIPE_WEBHOOK_SECRET')) {
        $keys['webhook_secret'] = STRIPE_WEBHOOK_SECRET;
    }

    return $keys;
}

// ---------------------------------------------------------------------------
// Internal cURL helper
// ---------------------------------------------------------------------------

/**
 * Execute a Stripe API request via cURL.
 *
 * @param  string $method  GET|POST|DELETE
 * @param  string $path    e.g. '/payment_intents'
 * @param  array  $params  POST body params
 * @return array  Decoded JSON response
 * @throws RuntimeException on network or API error
 */
function _stripeCurl(string $method, string $path, array $params = []): array
{
    $keys = getStripeKeys();
    if (empty($keys['secret_key'])) {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    $url = STRIPE_API_BASE . $path;
    $ch  = curl_init();

    $headers = [
        'Authorization: Bearer ' . $keys['secret_key'],
        'Stripe-Version: ' . STRIPE_API_VERSION,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } elseif ($method === 'GET' && !empty($params)) {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Stripe cURL error: ' . $curlErr);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from Stripe API.');
    }

    if (!empty($data['error'])) {
        $msg = $data['error']['message'] ?? 'Stripe API error';
        throw new RuntimeException($msg);
    }

    return $data;
}

// ---------------------------------------------------------------------------
// PaymentIntent
// ---------------------------------------------------------------------------

/**
 * Create a Stripe PaymentIntent for an order.
 *
 * @param  int    $orderId
 * @param  int    $amountCents  Amount in cents (e.g. $50.00 = 5000)
 * @param  string $currency     3-letter currency code, default USD
 * @return array  Stripe PaymentIntent data including client_secret
 */
function createPaymentIntent(int $orderId, int $amountCents, string $currency = 'usd'): array
{
    $db = getDB();

    // Load order for metadata
    $stmt = $db->prepare('SELECT buyer_id, supplier_id, order_number FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    $params = [
        'amount'                    => $amountCents,
        'currency'                  => strtolower($currency),
        'automatic_payment_methods[enabled]' => 'true',
        'metadata[order_id]'        => $orderId,
        'metadata[order_number]'    => $order['order_number'] ?? '',
        'metadata[buyer_id]'        => $order['buyer_id'] ?? '',
        'metadata[supplier_id]'     => $order['supplier_id'] ?? '',
    ];

    $intent = _stripeCurl('POST', '/payment_intents', $params);

    // Persist intent ID on the order
    try {
        $db->prepare('UPDATE orders SET payment_intent_id = ? WHERE id = ?')
           ->execute([$intent['id'], $orderId]);

        // Log to payment_intents table
        $db->prepare(
            'INSERT INTO payment_intents
                (order_id, payment_intent_id, amount_cents, currency, status, client_secret)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), updated_at=NOW()'
        )->execute([
            $orderId,
            $intent['id'],
            $amountCents,
            strtoupper($currency),
            $intent['status'] ?? 'created',
            $intent['client_secret'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log('createPaymentIntent DB error: ' . $e->getMessage());
    }

    return $intent;
}

/**
 * Retrieve a PaymentIntent from Stripe to verify its status.
 *
 * @param  string $paymentIntentId
 * @return array  Stripe PaymentIntent data
 */
function confirmPayment(string $paymentIntentId): array
{
    return _stripeCurl('GET', '/payment_intents/' . urlencode($paymentIntentId));
}

// ---------------------------------------------------------------------------
// Refunds
// ---------------------------------------------------------------------------

/**
 * Issue a full or partial refund on a PaymentIntent.
 *
 * @param  string   $paymentIntentId
 * @param  int|null $amountCents  null = full refund
 * @return array  Stripe Refund object
 */
function refundPayment(string $paymentIntentId, ?int $amountCents = null): array
{
    $params = ['payment_intent' => $paymentIntentId];
    if ($amountCents !== null) {
        $params['amount'] = $amountCents;
    }
    return _stripeCurl('POST', '/refunds', $params);
}

// ---------------------------------------------------------------------------
// Webhook handling
// ---------------------------------------------------------------------------

/**
 * Verify Stripe webhook signature and parse the event.
 *
 * @param  string $rawPayload  Raw request body
 * @param  string $sigHeader   Value of Stripe-Signature header
 * @return array  Parsed event array
 * @throws RuntimeException on invalid signature
 */
function verifyStripeWebhook(string $rawPayload, string $sigHeader): array
{
    $keys   = getStripeKeys();
    $secret = $keys['webhook_secret'];

    if (empty($secret)) {
        throw new RuntimeException('Stripe webhook secret is not configured.');
    }

    // Parse signature header: t=timestamp,v1=hash,...
    $parts     = explode(',', $sigHeader);
    $timestamp = '';
    $signatures = [];
    foreach ($parts as $part) {
        [$k, $v] = explode('=', $part, 2);
        if ($k === 't') {
            $timestamp = $v;
        } elseif ($k === 'v1') {
            $signatures[] = $v;
        }
    }

    if (empty($timestamp) || empty($signatures)) {
        throw new RuntimeException('Invalid Stripe-Signature header format.');
    }

    // Tolerance: 5 minutes
    if (abs(time() - (int)$timestamp) > 300) {
        throw new RuntimeException('Stripe webhook timestamp is too old.');
    }

    $expectedSig = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $secret);
    $verified = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expectedSig, $sig)) {
            $verified = true;
            break;
        }
    }

    if (!$verified) {
        throw new RuntimeException('Stripe webhook signature verification failed.');
    }

    $event = json_decode($rawPayload, true);
    if (!is_array($event)) {
        throw new RuntimeException('Invalid JSON in Stripe webhook payload.');
    }

    return $event;
}

/**
 * Handle a verified Stripe webhook event.
 * Updates order status and triggers notifications.
 *
 * @param  array $event  Parsed Stripe event
 * @return array  Result: ['handled' => true, 'action' => '...']
 */
function handleWebhook(array $event): array
{
    $db        = getDB();
    $eventType = $event['type'] ?? '';
    $object    = $event['data']['object'] ?? [];

    // Log the event
    $logId = _logWebhookEvent($db, $eventType, $event['id'] ?? null, $event);

    $result = ['handled' => false, 'action' => 'no_action', 'log_id' => $logId];

    switch ($eventType) {

        case 'payment_intent.succeeded':
            $intentId = $object['id'] ?? '';
            $orderId  = (int)($object['metadata']['order_id'] ?? 0);

            if ($intentId && $orderId) {
                // Update order status — match by payment_intent_id (v14 column)
                $db->prepare(
                    "UPDATE orders SET status='confirmed', payment_status='paid',
                     confirmed_at=NOW(), updated_at=NOW()
                     WHERE id = ? AND payment_intent_id = ?"
                )->execute([$orderId, $intentId]);

                // Record payment
                _stripeRecordPayment($db, $orderId, $intentId, (int)($object['amount'] ?? 0),
                               $object['currency'] ?? 'usd', 'success');

                // Notifications
                _notifyOrderPaid($db, $orderId);

                $result = ['handled' => true, 'action' => 'order_paid', 'order_id' => $orderId];
            }
            break;

        case 'payment_intent.payment_failed':
            $intentId = $object['id'] ?? '';
            $orderId  = (int)($object['metadata']['order_id'] ?? 0);

            if ($intentId && $orderId) {
                $db->prepare(
                    "UPDATE orders SET payment_status='failed', updated_at=NOW()
                     WHERE id = ? AND payment_intent_id = ?"
                )->execute([$orderId, $intentId]);

                _stripeRecordPayment($db, $orderId, $intentId, (int)($object['amount'] ?? 0),
                               $object['currency'] ?? 'usd', 'failed');

                _notifyPaymentFailed($db, $orderId);

                $result = ['handled' => true, 'action' => 'payment_failed', 'order_id' => $orderId];
            }
            break;

        case 'charge.refunded':
            $paymentIntentId = $object['payment_intent'] ?? '';
            if ($paymentIntentId) {
                $oStmt = $db->prepare(
                    'SELECT id FROM orders WHERE payment_intent_id = ? LIMIT 1'
                );
                $oStmt->execute([$paymentIntentId]);
                $row = $oStmt->fetch();
                if ($row) {
                    $db->prepare(
                        "UPDATE orders SET status='refunded', payment_status='refunded',
                         updated_at=NOW() WHERE id = ?"
                    )->execute([$row['id']]);

                    _notifyRefund($db, (int)$row['id']);

                    $result = ['handled' => true, 'action' => 'refunded', 'order_id' => $row['id']];
                }
            }
            break;

        case 'charge.dispute.created':
            $paymentIntentId = $object['payment_intent'] ?? '';
            if ($paymentIntentId) {
                $oStmt = $db->prepare(
                    'SELECT id, buyer_id FROM orders WHERE payment_intent_id = ? LIMIT 1'
                );
                $oStmt->execute([$paymentIntentId]);
                $row = $oStmt->fetch();
                if ($row) {
                    _notifyDispute($db, (int)$row['id']);
                    $result = ['handled' => true, 'action' => 'dispute_flagged', 'order_id' => $row['id']];
                }
            }
            break;

        // ── Subscription Events (PR #9) ──────────────────────────────────

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            $stripeSubId    = $object['id']       ?? '';
            $stripeStatus   = $object['status']   ?? 'active';
            $periodEnd      = $object['current_period_end'] ?? null;
            $cancelAtEnd    = !empty($object['cancel_at_period_end']) ? 1 : 0;

            if ($stripeSubId) {
                $statusMap = ['active' => 'active', 'trialing' => 'trialing', 'past_due' => 'past_due', 'canceled' => 'cancelled'];
                $dbStatus  = $statusMap[$stripeStatus] ?? 'active';
                $endDate   = $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null;

                try {
                    $db->prepare(
                        'UPDATE plan_subscriptions
                         SET status = ?, cancel_at_period_end = ?, ends_at = ?, current_period_end = ?, updated_at = NOW()
                         WHERE stripe_subscription_id = ?'
                    )->execute([$dbStatus, $cancelAtEnd, $endDate, $endDate, $stripeSubId]);
                } catch (\PDOException $e) { /* ignore */ }

                $result = ['handled' => true, 'action' => $eventType, 'stripe_sub_id' => $stripeSubId];
            }
            break;

        case 'customer.subscription.deleted':
            $stripeSubId = $object['id'] ?? '';
            if ($stripeSubId) {
                try {
                    // Find supplier and downgrade to free
                    $subStmt = $db->prepare(
                        'SELECT supplier_id FROM plan_subscriptions WHERE stripe_subscription_id = ? LIMIT 1'
                    );
                    $subStmt->execute([$stripeSubId]);
                    $subRow = $subStmt->fetch();

                    $db->prepare(
                        'UPDATE plan_subscriptions SET status = "cancelled", cancelled_at = NOW(), updated_at = NOW()
                         WHERE stripe_subscription_id = ?'
                    )->execute([$stripeSubId]);

                    if ($subRow) {
                        // Activate Free plan for supplier
                        require_once __DIR__ . '/plans.php';
                        $freePlan = getPlanBySlug('free');
                        if ($freePlan) {
                            subscribeToPlan((int)$subRow['supplier_id'], (int)$freePlan['id'], 'monthly');
                        }
                    }
                } catch (\PDOException $e) { /* ignore */ }

                $result = ['handled' => true, 'action' => 'subscription_cancelled', 'stripe_sub_id' => $stripeSubId];
            }
            break;

        case 'invoice.payment_succeeded':
            $stripeInvoiceId = $object['id']           ?? '';
            $stripeSubId     = $object['subscription'] ?? '';
            $amountPaid      = (int)($object['amount_paid'] ?? 0);
            $currency        = strtoupper($object['currency'] ?? 'USD');

            if ($stripeSubId) {
                try {
                    $subStmt = $db->prepare(
                        'SELECT id, supplier_id, plan_id FROM plan_subscriptions
                         WHERE stripe_subscription_id = ? LIMIT 1'
                    );
                    $subStmt->execute([$stripeSubId]);
                    $subRow = $subStmt->fetch();

                    if ($subRow) {
                        require_once __DIR__ . '/plans.php';
                        renewPlan((int)$subRow['supplier_id'], $stripeInvoiceId);
                    }
                } catch (\PDOException $e) { /* ignore */ }

                $result = ['handled' => true, 'action' => 'subscription_renewed', 'stripe_invoice_id' => $stripeInvoiceId];
            }
            break;

        case 'invoice.payment_failed':
            $stripeSubId = $object['subscription'] ?? '';
            if ($stripeSubId) {
                try {
                    $db->prepare(
                        'UPDATE plan_subscriptions SET status = "past_due", updated_at = NOW()
                         WHERE stripe_subscription_id = ?'
                    )->execute([$stripeSubId]);

                    // Notify supplier — grace period before downgrade
                    $graceDays = defined('PLAN_PAYMENT_GRACE_PERIOD_DAYS') ? (int)PLAN_PAYMENT_GRACE_PERIOD_DAYS : 3;
                    $subStmt = $db->prepare(
                        "UPDATE plan_subscriptions
                         SET grace_period_ends_at = DATE_ADD(NOW(), INTERVAL $graceDays DAY)
                         WHERE stripe_subscription_id = ?"
                    );
                    $subStmt->execute([$stripeSubId]);
                } catch (\PDOException $e) { /* ignore */ }

                $result = ['handled' => true, 'action' => 'payment_failed_subscription', 'stripe_sub_id' => $stripeSubId];
            }
            break;
    }

    // Mark webhook as processed
    _markWebhookProcessed($db, $logId, $result['handled']);

    return $result;
}

// ---------------------------------------------------------------------------
// Internal notification helpers
// ---------------------------------------------------------------------------

function _notifyOrderPaid(PDO $db, int $orderId): void
{
    try {
        require_once __DIR__ . '/notifications.php';
        $stmt = $db->prepare(
            'SELECT o.order_number, o.buyer_id, o.supplier_id
             FROM orders o WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        $title = 'Order Confirmed';
        $msg   = 'Your order #' . $order['order_number'] . ' has been confirmed and payment received.';

        createNotification($db, (int)$order['buyer_id'], 'payment_received', $title, $msg,
            ['order_id' => $orderId], 'normal', '/pages/order/detail.php?id=' . $orderId);

        if ($order['supplier_id']) {
            createNotification($db, (int)$order['supplier_id'], 'order_placed',
                'New Order Received',
                'New order #' . $order['order_number'] . ' has been placed.',
                ['order_id' => $orderId], 'normal', '/pages/supplier/orders.php');
        }
    } catch (Throwable $e) {
        error_log('_notifyOrderPaid error: ' . $e->getMessage());
    }
}

function _notifyPaymentFailed(PDO $db, int $orderId): void
{
    try {
        require_once __DIR__ . '/notifications.php';
        $stmt = $db->prepare('SELECT order_number, buyer_id FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        createNotification($db, (int)$order['buyer_id'], 'payment_failed',
            'Payment Failed',
            'Payment for order #' . $order['order_number'] . ' failed. Please try again.',
            ['order_id' => $orderId], 'high', '/pages/checkout/payment-failed.php?order_id=' . $orderId);
    } catch (Throwable $e) {
        error_log('_notifyPaymentFailed error: ' . $e->getMessage());
    }
}

function _notifyRefund(PDO $db, int $orderId): void
{
    try {
        require_once __DIR__ . '/notifications.php';
        $stmt = $db->prepare('SELECT order_number, buyer_id FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        createNotification($db, (int)$order['buyer_id'], 'order_refunded',
            'Refund Processed',
            'Your refund for order #' . $order['order_number'] . ' has been processed.',
            ['order_id' => $orderId]);
    } catch (Throwable $e) {
        error_log('_notifyRefund error: ' . $e->getMessage());
    }
}

function _notifyDispute(PDO $db, int $orderId): void
{
    try {
        require_once __DIR__ . '/notifications.php';
        $stmt = $db->prepare('SELECT order_number, buyer_id FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        // Notify admin (user_id=1 as placeholder — real admins fetched from users table)
        $admins = $db->query(
            "SELECT id FROM users WHERE role='admin' LIMIT 5"
        )->fetchAll();
        foreach ($admins as $admin) {
            createNotification($db, (int)$admin['id'], 'system',
                'Dispute Filed',
                'A dispute has been filed for order #' . $order['order_number'] . '.',
                ['order_id' => $orderId], 'high');
        }
    } catch (Throwable $e) {
        error_log('_notifyDispute error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Internal DB helpers
// ---------------------------------------------------------------------------

function _logWebhookEvent(PDO $db, string $eventType, ?string $eventId, array $event): int
{
    try {
        $stmt = $db->prepare(
            'INSERT INTO webhook_logs (source, event_type, event_id, payload, status)
             VALUES ("stripe", ?, ?, ?, "received")'
        );
        $stmt->execute([$eventType, $eventId, json_encode($event)]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log('_logWebhookEvent error: ' . $e->getMessage());
        return 0;
    }
}

function _markWebhookProcessed(PDO $db, int $logId, bool $success): void
{
    if ($logId <= 0) return;
    try {
        $status = $success ? 'processed' : 'failed';
        $db->prepare(
            'UPDATE webhook_logs SET status=?, processed_at=NOW() WHERE id=?'
        )->execute([$status, $logId]);
    } catch (PDOException $e) {
        error_log('_markWebhookProcessed error: ' . $e->getMessage());
    }
}

function _stripeRecordPayment(PDO $db, int $orderId, string $intentId, int $amountCents, string $currency, string $status): void
{
    try {
        $amount = $amountCents / 100.0;
        $pstatus = $status === 'success' ? 'success' : 'failed';
        $db->prepare(
            'INSERT INTO payments (order_id, transaction_id, amount, currency, method, gateway, status, paid_at)
             VALUES (?, ?, ?, ?, "card", "stripe", ?, IF(? = "success", NOW(), NULL))
             ON DUPLICATE KEY UPDATE status=VALUES(status)'
        )->execute([
            $orderId, $intentId, $amount, strtoupper($currency), $pstatus, $pstatus
        ]);
    } catch (PDOException $e) {
        error_log('_recordPayment error: ' . $e->getMessage());
    }
}

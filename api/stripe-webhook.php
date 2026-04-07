<?php
/**
 * api/stripe-webhook.php — Stripe Webhook Endpoint (PR #6)
 *
 * Receives POST events from Stripe.
 * - Verifies Stripe-Signature header (no CSRF — Stripe doesn't send it)
 * - Handles: payment_intent.succeeded, payment_intent.payment_failed,
 *            charge.refunded, charge.dispute.created
 * - Logs all events to webhook_logs table
 * - Returns 200 OK to Stripe on success
 */

// No session / CSRF for webhook — Stripe uses signature verification
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/stripe-handler.php';
require_once __DIR__ . '/../config/stripe.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read raw payload
$rawPayload = file_get_contents('php://input');
$sigHeader  = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// Verify signature and parse event
try {
    $event = verifyStripeWebhook($rawPayload, $sigHeader);
} catch (RuntimeException $e) {
    error_log('Stripe webhook signature error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Webhook signature verification failed']);
    exit;
}

// Process the event
try {
    $result = handleWebhook($event);
    http_response_code(200);
    echo json_encode(['received' => true, 'result' => $result]);
} catch (Throwable $e) {
    error_log('Stripe webhook handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook handler failed']);
}

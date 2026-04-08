<?php
/**
 * GlobexSky — Order Confirmed by Supplier Template (Buyer)
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order-confirmed email.
 *
 * @param string $buyerName  Buyer display name
 * @param int    $orderId    Order ID
 * @param string $orderUrl   Link to order detail page
 * @param string $eta        Estimated delivery / dispatch date
 * @return string Complete HTML email
 */
function emailOrderConfirmed(string $buyerName, int $orderId, string $orderUrl, string $eta = ''): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($buyerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEta  = htmlspecialchars($eta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $etaLine = $safeEta
        ? "<p style=\"margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;\">
             Estimated dispatch: <strong>{$safeEta}</strong>
           </p>"
        : '';

    $content = <<<HTML
<div style="background-color:#d4edda;border-left:4px solid #28a745;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#155724;">✓ Order Confirmed</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Order Has Been Confirmed!
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Great news, {$safeName}! The supplier has confirmed your order
  <strong>#{$orderId}</strong> and will prepare it for shipment.
</p>
{$etaLine}
HTML;

    $content .= _emailButton($orderUrl, 'Track Your Order');
    $content .= _emailHelperText("You will receive a shipping notification once your order is dispatched.");

    return emailBase($content, "Order #{$orderId} confirmed — {$appName}");
}

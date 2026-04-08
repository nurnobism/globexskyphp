<?php
/**
 * GlobexSky — Order Delivered Template (Buyer)
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order-delivered email with a review prompt.
 *
 * @param string $buyerName  Buyer display name
 * @param int    $orderId    Order ID
 * @param string $reviewUrl  URL to leave a review
 * @return string Complete HTML email
 */
function emailOrderDelivered(string $buyerName, int $orderId, string $reviewUrl): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($buyerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Order Has Been Delivered! 📦
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, great news! Your order <strong>#{$orderId}</strong> has been
  marked as delivered. We hope everything arrived in perfect condition.
</p>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Got a moment? Share your experience — your review helps other buyers on
  {$appName} make informed decisions.
</p>
HTML;

    $content .= _emailButton($reviewUrl, 'Leave a Review ⭐');
    $content .= _emailHelperText("If there is an issue with your order, you can open a dispute from your order page.");

    return emailBase($content, "Order #{$orderId} delivered — {$appName}");
}

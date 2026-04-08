<?php
/**
 * GlobexSky — Order Cancelled Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order-cancelled email.
 *
 * @param string $recipientName  Recipient display name (buyer or supplier)
 * @param int    $orderId        Order ID
 * @param string $reason         Cancellation reason
 * @param string $orderUrl       Link to order detail / support
 * @return string Complete HTML email
 */
function emailOrderCancelled(string $recipientName, int $orderId, string $reason, string $orderUrl): string
{
    $appName    = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName   = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeReason = htmlspecialchars($reason,        ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#f8d7da;border-left:4px solid #dc3545;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#721c24;">✕ Order Cancelled</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Order #{$orderId} Has Been Cancelled
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, we are sorry to inform you that order <strong>#{$orderId}</strong>
  has been cancelled.
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f8f9fa;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="font-size:14px;color:#6c757d;font-weight:600;">Reason:</td>
  </tr>
  <tr>
    <td style="padding-top:6px;font-size:14px;color:#212529;">{$safeReason}</td>
  </tr>
</table>
<p style="margin:0 0 20px;font-size:14px;color:#495057;line-height:1.6;">
  Any payments already made will be refunded within 3–5 business days.
</p>
HTML;

    $content .= _emailButton($orderUrl, 'View Order Details');

    return emailBase($content, "Order #{$orderId} cancelled — {$appName}");
}

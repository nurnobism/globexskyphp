<?php
/**
 * GlobexSky — Order Shipped Template (Buyer)
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order-shipped email.
 *
 * @param string $buyerName    Buyer display name
 * @param int    $orderId      Order ID
 * @param string $carrier      Carrier name (e.g. DHL, FedEx)
 * @param string $trackingNum  Tracking number
 * @param string $trackingUrl  URL to track parcel
 * @param string $eta          Estimated delivery date
 * @return string Complete HTML email
 */
function emailOrderShipped(
    string $buyerName,
    int    $orderId,
    string $carrier,
    string $trackingNum,
    string $trackingUrl,
    string $eta = ''
): string {
    $appName     = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName    = htmlspecialchars($buyerName,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCarrier = htmlspecialchars($carrier,     ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeTrkNum  = htmlspecialchars($trackingNum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEta     = htmlspecialchars($eta,         ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $etaRow = $safeEta
        ? "<tr><td style=\"padding:6px 0;font-size:14px;color:#6c757d;\">Estimated Delivery</td>
               <td style=\"padding:6px 0;font-size:14px;color:#212529;font-weight:600;\">{$safeEta}</td></tr>"
        : '';

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Order Is On Its Way! 🚚
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, your order <strong>#{$orderId}</strong> has been shipped and is
  now on its way to you.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Carrier</td>
    <td style="padding:6px 0;font-size:14px;color:#212529;font-weight:600;">{$safeCarrier}</td>
  </tr>
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Tracking Number</td>
    <td style="padding:6px 0;font-size:14px;color:#1a73e8;font-weight:600;">{$safeTrkNum}</td>
  </tr>
  {$etaRow}
</table>
HTML;

    $content .= _emailButton($trackingUrl, 'Track Your Shipment');
    $content .= _emailHelperText("Tracking information may take up to 24 hours to update.");

    return emailBase($content, "Order #{$orderId} shipped — {$appName}");
}

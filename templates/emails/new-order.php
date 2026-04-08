<?php
/**
 * GlobexSky — New Order Received Template (Supplier)
 */

require_once __DIR__ . '/base.php';

/**
 * Build the new-order notification email for the supplier.
 *
 * @param string $supplierName Supplier display name
 * @param int    $orderId      Order ID
 * @param string $buyerName    Buyer display name
 * @param array  $items        Order items (product_name, quantity, unit_price)
 * @param string $currency     Currency code
 * @param float  $total        Order total
 * @param string $ordersUrl    Link to supplier orders dashboard
 * @return string Complete HTML email
 */
function emailNewOrder(
    string $supplierName,
    int    $orderId,
    string $buyerName,
    array  $items,
    string $currency,
    float  $total,
    string $ordersUrl
): string {
    $appName      = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeSup      = htmlspecialchars($supplierName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeBuyer    = htmlspecialchars($buyerName,    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCurrency = htmlspecialchars($currency,     ENT_QUOTES, 'UTF-8');
    $safeTotal    = number_format($total, 2);

    $itemRows = '';
    foreach ($items as $item) {
        $pName = htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $qty   = (int)($item['quantity'] ?? 1);
        $price = number_format((float)($item['unit_price'] ?? 0), 2);
        $itemRows .= <<<HTML
<tr>
  <td style="padding:8px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;">{$pName}</td>
  <td style="padding:8px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:center;">{$qty}</td>
  <td style="padding:8px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:right;">{$safeCurrency} {$price}</td>
</tr>
HTML;
    }

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  New Order Received! 🛒
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeSup}, you have received a new order <strong>#{$orderId}</strong>
  from buyer <strong>{$safeBuyer}</strong>. Please confirm and prepare it for
  shipment as soon as possible.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border:1px solid #e9ecef;border-radius:6px;padding:0 16px;margin:0 0 24px;">
  <thead>
    <tr>
      <th style="padding:10px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:left;">Product</th>
      <th style="padding:10px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:center;">Qty</th>
      <th style="padding:10px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:right;">Price</th>
    </tr>
  </thead>
  <tbody>
    {$itemRows}
    <tr>
      <td colspan="2" style="padding:10px 0;font-size:15px;font-weight:700;color:#1a1a2e;text-align:right;">Order Total:</td>
      <td style="padding:10px 0;font-size:15px;font-weight:700;color:#1a73e8;text-align:right;">{$safeCurrency} {$safeTotal}</td>
    </tr>
  </tbody>
</table>
HTML;

    $content .= _emailButton($ordersUrl, 'View & Confirm Order');
    $content .= _emailHelperText("Please confirm within 24 hours to avoid automatic cancellation.");

    return emailBase($content, "New order #{$orderId} — {$appName}");
}

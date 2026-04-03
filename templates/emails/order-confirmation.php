<?php
/**
 * GlobexSky Order Confirmation Email Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order confirmation email.
 *
 * Expected keys in $orderData:
 *  id, order_number (or id), buyer_name, placed_at (or created_at),
 *  total (or total_amount), shipping_address,
 *  items[]  => [ product_name, sku, quantity, unit_price ]
 *
 * @param array $orderData Associative array of order details
 * @return string Complete HTML email
 */
function emailOrderConfirmation(array $orderData): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $appUrl  = defined('APP_URL')  ? APP_URL  : '';

    $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $orderId     = (int) ($orderData['id'] ?? 0);
    $orderNum    = $e((string) ($orderData['order_number'] ?? '#' . $orderId));
    $buyerName   = $e((string) ($orderData['buyer_name'] ?? 'Customer'));
    $placedAt    = $orderData['placed_at'] ?? ($orderData['created_at'] ?? date('Y-m-d H:i:s'));
    $total       = (float) ($orderData['total'] ?? $orderData['total_amount'] ?? 0);
    $shippingAddr = $e((string) ($orderData['shipping_address'] ?? '—'));
    $placedAtTs    = strtotime($placedAt);
    $formattedDate = $placedAtTs !== false ? date('F j, Y g:i A', $placedAtTs) : $e($placedAt);
    $formattedTotal = '$' . number_format($total, 2);

    // Build items rows
    $itemsHtml = '';
    $items = $orderData['items'] ?? [];
    foreach ($items as $item) {
        $productName = $e((string) ($item['product_name'] ?? 'Product'));
        $sku         = $e((string) ($item['sku'] ?? ''));
        $qty         = (int) ($item['quantity'] ?? 1);
        $unitPrice   = (float) ($item['unit_price'] ?? 0);
        $lineTotal   = '$' . number_format($qty * $unitPrice, 2);
        $unitFmt     = '$' . number_format($unitPrice, 2);

        $skuLine = $sku ? "<br><span style='font-size:12px;color:#6c757d;'>SKU: {$sku}</span>" : '';
        $itemsHtml .= <<<HTML
<tr>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;">
    {$productName}{$skuLine}
  </td>
  <td style="padding:10px 8px;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:center;">{$qty}</td>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:right;">{$unitFmt}</td>
  <td style="padding:10px 0 10px 8px;border-bottom:1px solid #e9ecef;font-size:14px;font-weight:600;color:#212529;text-align:right;">{$lineTotal}</td>
</tr>
HTML;
    }

    if ($itemsHtml === '') {
        $itemsHtml = '<tr><td colspan="4" style="padding:12px 0;font-size:14px;color:#6c757d;">No items found.</td></tr>';
    }

    $orderUrl = $appUrl . '/pages/orders.php?id=' . $orderId;

    $content = <<<HTML
<h1 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#1a1a2e;">Order Confirmed ✓</h1>
<p style="margin:0 0 24px;font-size:15px;color:#495057;">
  Hi {$buyerName}, thank you for your order! Here's your summary.
</p>

<!-- Order meta -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:16px 20px;margin-bottom:24px;">
  <tr>
    <td style="font-size:13px;color:#6c757d;padding-bottom:6px;">Order Number</td>
    <td style="font-size:13px;color:#6c757d;padding-bottom:6px;text-align:right;">Date Placed</td>
  </tr>
  <tr>
    <td style="font-size:16px;font-weight:700;color:#1a73e8;">{$orderNum}</td>
    <td style="font-size:14px;color:#212529;text-align:right;">{$formattedDate}</td>
  </tr>
</table>

<!-- Items table -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
  <thead>
    <tr style="border-bottom:2px solid #dee2e6;">
      <th style="font-size:12px;color:#6c757d;font-weight:600;text-transform:uppercase;padding-bottom:8px;text-align:left;">Product</th>
      <th style="font-size:12px;color:#6c757d;font-weight:600;text-transform:uppercase;padding-bottom:8px;text-align:center;">Qty</th>
      <th style="font-size:12px;color:#6c757d;font-weight:600;text-transform:uppercase;padding-bottom:8px;text-align:right;">Unit Price</th>
      <th style="font-size:12px;color:#6c757d;font-weight:600;text-transform:uppercase;padding-bottom:8px;text-align:right;padding-left:8px;">Total</th>
    </tr>
  </thead>
  <tbody>
    {$itemsHtml}
  </tbody>
</table>

<!-- Order total -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
  <tr>
    <td style="font-size:15px;font-weight:700;color:#1a1a2e;padding-top:12px;">Order Total</td>
    <td style="font-size:18px;font-weight:700;color:#1a73e8;text-align:right;padding-top:12px;">{$formattedTotal}</td>
  </tr>
</table>

<!-- Shipping address -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f8f9fa;border-left:3px solid #1a73e8;padding:14px 18px;margin-bottom:8px;border-radius:0 6px 6px 0;">
  <tr>
    <td>
      <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">Shipping To</p>
      <p style="margin:0;font-size:14px;color:#212529;">{$shippingAddr}</p>
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($orderUrl, 'View Order Details');
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        Questions? Reply to this email or contact
        <a href="mailto:support@globexsky.com" style="color:#1a73e8;text-decoration:none;">support@globexsky.com</a>.
      </p>';

    return emailBase($content, "Order Confirmation {$orderNum}");
}

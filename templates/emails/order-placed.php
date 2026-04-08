<?php
/**
 * GlobexSky — Order Placed Confirmation Template (Buyer)
 */

require_once __DIR__ . '/base.php';

/**
 * Build the order-placed email for the buyer.
 *
 * @param array $order  Keys: id, buyer_name, total_amount, currency, items[], order_url
 * @return string Complete HTML email
 */
function emailOrderPlaced(array $order): string
{
    $appName   = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName  = htmlspecialchars($order['buyer_name'] ?? 'Customer', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $orderId   = htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $currency  = htmlspecialchars($order['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8');
    $total     = number_format((float)($order['total_amount'] ?? 0), 2);
    $orderUrl  = $order['order_url'] ?? (defined('APP_URL') ? APP_URL . '/pages/account/orders.php' : '#');

    $itemRows = '';
    foreach ($order['items'] ?? [] as $item) {
        $pName = htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $qty   = (int)($item['quantity'] ?? 1);
        $price = number_format((float)($item['unit_price'] ?? 0), 2);
        $sub   = number_format($qty * (float)($item['unit_price'] ?? 0), 2);
        $itemRows .= <<<HTML
<tr>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;">{$pName}</td>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:center;">{$qty}</td>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:right;">{$currency} {$price}</td>
  <td style="padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px;color:#212529;text-align:right;">{$currency} {$sub}</td>
</tr>
HTML;
    }

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Order Placed Successfully! 🎉
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Thank you, {$safeName}! Your order <strong>#{$orderId}</strong> has been received
  and is being processed.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border:1px solid #e9ecef;border-radius:6px;padding:0 16px;margin:0 0 24px;">
  <thead>
    <tr>
      <th style="padding:12px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:left;">Product</th>
      <th style="padding:12px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:center;">Qty</th>
      <th style="padding:12px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:right;">Unit Price</th>
      <th style="padding:12px 0;border-bottom:2px solid #e9ecef;font-size:13px;color:#6c757d;text-align:right;">Subtotal</th>
    </tr>
  </thead>
  <tbody>
    {$itemRows}
    <tr>
      <td colspan="3" style="padding:12px 0;font-size:15px;font-weight:700;color:#1a1a2e;text-align:right;">Total:</td>
      <td style="padding:12px 0;font-size:15px;font-weight:700;color:#1a73e8;text-align:right;">{$currency} {$total}</td>
    </tr>
  </tbody>
</table>
HTML;

    $content .= _emailButton($orderUrl, 'View Your Order');
    $content .= _emailHelperText("You will receive another email when your order is confirmed by the supplier.");

    return emailBase($content, "Order #{$orderId} placed — {$appName}");
}

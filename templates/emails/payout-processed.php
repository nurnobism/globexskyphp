<?php
/**
 * GlobexSky — Payout Processed Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the payout-processed notification email.
 *
 * @param string $name       Recipient display name
 * @param string $amount     Formatted payout amount (e.g. "$1,250.00")
 * @param string $method     Payment method (e.g. "Bank Transfer", "PayPal")
 * @param string $reference  Transaction / payout reference ID
 * @param string $dashUrl    Link to earnings/payout dashboard
 * @return string Complete HTML email
 */
function emailPayoutProcessed(string $name, string $amount, string $method, string $reference, string $dashUrl): string
{
    $appName   = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName  = htmlspecialchars($name,      ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeAmt   = htmlspecialchars($amount,    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMeth  = htmlspecialchars($method,    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeRef   = htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#d4edda;border-left:4px solid #28a745;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#155724;">✓ Payout Sent</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Payout Has Been Processed
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, your payout has been successfully processed. Funds should
  appear in your account within 1–3 business days.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Amount</td>
    <td style="padding:6px 0;font-size:18px;font-weight:700;color:#28a745;text-align:right;">{$safeAmt}</td>
  </tr>
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Method</td>
    <td style="padding:6px 0;font-size:14px;color:#212529;text-align:right;">{$safeMeth}</td>
  </tr>
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Reference</td>
    <td style="padding:6px 0;font-size:14px;color:#212529;text-align:right;">{$safeRef}</td>
  </tr>
</table>
HTML;

    $content .= _emailButton($dashUrl, 'View Earnings Dashboard');

    return emailBase($content, "Payout processed — {$appName}");
}

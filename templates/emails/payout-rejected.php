<?php
/**
 * GlobexSky — Payout Rejected Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the payout-rejected notification email.
 *
 * @param string $name      Recipient display name
 * @param string $amount    Formatted payout amount
 * @param string $reason    Rejection reason from admin
 * @param string $dashUrl   Link to payout/support dashboard
 * @return string Complete HTML email
 */
function emailPayoutRejected(string $name, string $amount, string $reason, string $dashUrl): string
{
    $appName   = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName  = htmlspecialchars($name,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeAmt   = htmlspecialchars($amount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeReas  = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#f8d7da;border-left:4px solid #dc3545;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#721c24;">✕ Payout Rejected</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Payout Request Was Rejected
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, unfortunately your payout request of <strong>{$safeAmt}</strong>
  could not be processed at this time.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#fff3cd;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="font-size:14px;color:#856404;font-weight:600;">Reason for rejection:</td>
  </tr>
  <tr>
    <td style="padding-top:8px;font-size:14px;color:#212529;">{$safeReas}</td>
  </tr>
</table>
<p style="margin:0 0 20px;font-size:14px;color:#495057;line-height:1.6;">
  Please resolve the issue above and submit a new payout request, or contact
  our support team if you believe this is an error.
</p>
HTML;

    $content .= _emailButton($dashUrl, 'View Payout Dashboard');

    return emailBase($content, "Payout rejected — {$appName}");
}

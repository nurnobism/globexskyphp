<?php
/**
 * GlobexSky — Dispute Opened Notification Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the dispute-opened notification email.
 *
 * @param string $recipientName  Recipient display name
 * @param int    $orderId        Order ID
 * @param int    $disputeId      Dispute ID
 * @param string $description    Short dispute description
 * @param string $disputeUrl     Link to the dispute detail page
 * @return string Complete HTML email
 */
function emailDisputeOpened(
    string $recipientName,
    int    $orderId,
    int    $disputeId,
    string $description,
    string $disputeUrl
): string {
    $appName   = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName  = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeDesc  = htmlspecialchars($description,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#fff3cd;border-left:4px solid #f0ad4e;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#856404;">⚠ Dispute Opened</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  A Dispute Has Been Opened
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, a dispute (#{$disputeId}) has been opened for order
  <strong>#{$orderId}</strong>. Please review the details and respond within
  <strong>3 business days</strong>.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f8f9fa;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="font-size:14px;color:#6c757d;font-weight:600;">Dispute reason:</td>
  </tr>
  <tr>
    <td style="padding-top:8px;font-size:14px;color:#212529;">{$safeDesc}</td>
  </tr>
</table>
HTML;

    $content .= _emailButton($disputeUrl, 'View Dispute');
    $content .= _emailHelperText("Disputes are resolved by the {$appName} team within 5–7 business days.");

    return emailBase($content, "Dispute #{$disputeId} opened — {$appName}");
}

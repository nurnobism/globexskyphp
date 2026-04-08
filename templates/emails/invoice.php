<?php
/**
 * GlobexSky — Invoice Email Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the invoice notification email.
 *
 * @param string $recipientName  Recipient display name
 * @param string $invoiceNumber  Invoice number
 * @param string $issuedAt       Issue date (formatted)
 * @param string $dueAt          Due date (formatted)
 * @param float  $total          Invoice total
 * @param string $currency       Currency code
 * @param string $invoiceUrl     Link to view / download invoice
 * @return string Complete HTML email
 */
function emailInvoice(
    string $recipientName,
    string $invoiceNumber,
    string $issuedAt,
    string $dueAt,
    float  $total,
    string $currency,
    string $invoiceUrl
): string {
    $appName      = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName     = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeInvNum   = htmlspecialchars($invoiceNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeIssued   = htmlspecialchars($issuedAt,      ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeDue      = htmlspecialchars($dueAt,         ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCurrency = htmlspecialchars($currency,      ENT_QUOTES, 'UTF-8');
    $safeTotal    = number_format($total, 2);

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Invoice {$safeInvNum}
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, please find your invoice details below.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Invoice Number</td>
    <td style="padding:6px 0;font-size:14px;font-weight:600;color:#212529;text-align:right;">{$safeInvNum}</td>
  </tr>
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Issued</td>
    <td style="padding:6px 0;font-size:14px;color:#212529;text-align:right;">{$safeIssued}</td>
  </tr>
  <tr>
    <td style="padding:6px 0;font-size:14px;color:#6c757d;">Due Date</td>
    <td style="padding:6px 0;font-size:14px;font-weight:600;color:#dc3545;text-align:right;">{$safeDue}</td>
  </tr>
  <tr>
    <td style="padding:10px 0 6px;font-size:16px;font-weight:700;color:#1a1a2e;border-top:1px solid #dee2e6;">Total</td>
    <td style="padding:10px 0 6px;font-size:18px;font-weight:700;color:#1a73e8;text-align:right;border-top:1px solid #dee2e6;">
      {$safeCurrency} {$safeTotal}
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($invoiceUrl, 'View & Download Invoice');
    $content .= _emailHelperText("Keep this email for your records. Contact us if you have billing questions.");

    return emailBase($content, "Invoice {$safeInvNum} — {$appName}");
}

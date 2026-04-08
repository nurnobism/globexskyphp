<?php
/**
 * GlobexSky — KYC Approved Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the KYC-approved notification email.
 *
 * @param string $name       User display name
 * @param string $dashUrl    Link to account dashboard
 * @return string Complete HTML email
 */
function emailKycApproved(string $name, string $dashUrl): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#d4edda;border-left:4px solid #28a745;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#155724;">✓ KYC Verified</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Identity Has Been Verified!
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Congratulations, {$safeName}! Your KYC (Know Your Customer) verification has
  been approved. You now have full access to all {$appName} features, including
  higher withdrawal limits and verified supplier status.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:16px 20px;margin:0 0 24px;">
  <tr>
    <td>
      <ul style="margin:0;padding-left:20px;font-size:14px;color:#495057;line-height:1.9;">
        <li>✓ Verified supplier badge on your profile</li>
        <li>✓ Increased withdrawal limits</li>
        <li>✓ Access to premium marketplace features</li>
      </ul>
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($dashUrl, 'Go to Dashboard');

    return emailBase($content, "KYC approved — {$appName}");
}

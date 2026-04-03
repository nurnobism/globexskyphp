<?php
/**
 * GlobexSky Password Reset Email Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the password-reset email.
 *
 * @param string $name        Recipient's display name
 * @param string $resetUrl    Full URL containing the reset token
 * @param int    $expiryHours Token validity in hours (shown in email copy)
 * @return string Complete HTML email
 */
function emailPasswordReset(string $name, string $resetUrl, int $expiryHours = 24): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1a1a2e;">Reset Your Password</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, we received a request to reset the password for your
  {$appName} account. Click the button below to choose a new password.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:14px 18px;margin-bottom:24px;">
  <tr>
    <td style="font-size:13px;color:#856404;">
      ⚠️ This link expires in <strong>{$expiryHours} hours</strong>.
      If you did not request a password reset, you can safely ignore this email —
      your password will not change.
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($safeUrl, 'Reset My Password', '#dc3545');
    $content .= _emailHelperText("Or copy and paste this URL into your browser:<br><span style='word-break:break-all;color:#1a73e8;'>{$safeUrl}</span>");
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        If you need help, contact us at
        <a href="mailto:support@globexsky.com" style="color:#1a73e8;text-decoration:none;">support@globexsky.com</a>.
      </p>';

    return emailBase($content, "Reset Your {$appName} Password");
}

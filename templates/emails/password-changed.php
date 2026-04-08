<?php
/**
 * GlobexSky — Password Changed Security Alert Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the password-changed security-alert email.
 *
 * @param string $name      User display name
 * @param string $changedAt Human-readable date/time of the change
 * @param string $supportUrl Link to contact support
 * @return string Complete HTML email
 */
function emailPasswordChanged(string $name, string $changedAt, string $supportUrl = ''): string
{
    $appName    = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName   = htmlspecialchars($name,      ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeAt     = htmlspecialchars($changedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeSup    = $supportUrl ?: (defined('APP_URL') ? APP_URL . '/pages/contact.php' : '#');

    $content = <<<HTML
<div style="background-color:#fff3cd;border-left:4px solid #f0ad4e;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#856404;">⚠ Security Alert</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Password Was Changed
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, your {$appName} account password was successfully changed on
  <strong>{$safeAt}</strong>.
</p>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  If you made this change, no further action is needed.
  If you did <strong>not</strong> authorise this change, please contact our
  support team immediately to secure your account.
</p>
HTML;

    $content .= _emailButton($safeSup, 'Contact Support', '#dc3545');
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        If this was you, you can safely ignore this email.
      </p>';

    return emailBase($content, "Security alert — {$appName}");
}

<?php
/**
 * GlobexSky — Plan Expires Soon Reminder Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the plan-expires-soon reminder email.
 *
 * @param string $name       User display name
 * @param string $planName   Current plan name
 * @param string $expiresAt  Human-readable expiry date
 * @param string $renewUrl   Link to renew / upgrade
 * @return string Complete HTML email
 */
function emailPlanExpiresSoon(string $name, string $planName, string $expiresAt, string $renewUrl): string
{
    $appName    = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName   = htmlspecialchars($name,      ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safePlan   = htmlspecialchars($planName,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeExpiry = htmlspecialchars($expiresAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#fff3cd;border-left:4px solid #f0ad4e;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#856404;">⏰ Plan Expiring Soon</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Plan Expires Soon
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, your <strong>{$safePlan}</strong> plan on {$appName} will expire
  on <strong>{$safeExpiry}</strong>. Renew now to avoid any interruption to your
  business listings and features.
</p>
HTML;

    $content .= _emailButton($renewUrl, 'Renew Your Plan');
    $content .= _emailHelperText("After expiry your account will be downgraded to the free tier.");

    return emailBase($content, "Your plan expires soon — {$appName}");
}

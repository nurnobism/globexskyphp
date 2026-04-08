<?php
/**
 * GlobexSky — Plan Expired Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the plan-expired notification email.
 *
 * @param string $name     User display name
 * @param string $planName Plan that expired
 * @param string $renewUrl Link to renew / upgrade
 * @return string Complete HTML email
 */
function emailPlanExpired(string $name, string $planName, string $renewUrl): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($name,     ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safePlan = htmlspecialchars($planName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<div style="background-color:#f8d7da;border-left:4px solid #dc3545;border-radius:4px;padding:14px 18px;margin:0 0 20px;">
  <p style="margin:0;font-size:14px;font-weight:600;color:#721c24;">Plan Expired</p>
</div>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Your Plan Has Expired
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, your <strong>{$safePlan}</strong> plan on {$appName} has expired.
  Your account has been moved to the free tier and some features may now be
  unavailable.
</p>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Renew or upgrade your plan to restore full access to all {$appName} features
  and continue growing your business.
</p>
HTML;

    $content .= _emailButton($renewUrl, 'Renew / Upgrade Now');

    return emailBase($content, "Your plan has expired — {$appName}");
}

<?php
/**
 * GlobexSky Welcome Email Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the welcome email for a new user.
 *
 * @param string $name     The user's display name
 * @param string $loginUrl URL to the login page
 * @return string Complete HTML email
 */
function emailWelcome(string $name, string $loginUrl): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Welcome to {$appName}, {$safeName}! 🎉
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Your account has been created successfully. You now have access to the
  {$appName} B2B marketplace — connect with suppliers, manage orders,
  and grow your business all in one place.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-radius:6px;padding:20px 24px;margin:0 0 24px;">
  <tr>
    <td>
      <p style="margin:0 0 12px;font-size:14px;font-weight:600;color:#1a1a2e;">
        Here's what you can do next:
      </p>
      <ul style="margin:0;padding-left:20px;font-size:14px;color:#495057;line-height:1.8;">
        <li>Browse thousands of verified suppliers</li>
        <li>Request quotes and place orders</li>
        <li>Track shipments in real time</li>
        <li>Manage invoices and payments</li>
      </ul>
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($loginUrl, 'Log In to Your Account');
    $content .= _emailHelperText("If you didn't create this account, please ignore this email.");
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        Need help? Contact us at
        <a href="mailto:support@globexsky.com" style="color:#1a73e8;text-decoration:none;">support@globexsky.com</a>
      </p>';

    return emailBase($content, "Welcome to {$appName}!");
}

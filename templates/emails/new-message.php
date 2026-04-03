<?php
/**
 * GlobexSky New Message Notification Email Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the "you have a new message" notification email.
 *
 * @param string $recipientName Display name of the email recipient
 * @param string $senderName    Display name of the message sender
 * @param string $preview       Short preview of the message body (will be truncated)
 * @param string $messageUrl    URL to open the conversation
 * @return string Complete HTML email
 */
function emailNewMessage(
    string $recipientName,
    string $senderName,
    string $preview,
    string $messageUrl
): string {
    $appName       = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeRecipient = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeSender    = htmlspecialchars($senderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl       = htmlspecialchars($messageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Truncate preview to 160 chars for the email snippet
    $previewTruncated = mb_strlen($preview) > 160
        ? mb_substr($preview, 0, 157) . '…'
        : $preview;
    $safePreview = htmlspecialchars($previewTruncated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1a1a2e;">
  💬 New Message
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeRecipient}, you have a new message from
  <strong style="color:#1a1a2e;">{$safeSender}</strong> on {$appName}.
</p>

<!-- Message preview bubble -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f0f4f8;border-left:3px solid #1a73e8;border-radius:0 6px 6px 0;padding:16px 20px;margin-bottom:24px;">
  <tr>
    <td>
      <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:0.5px;">
        {$safeSender} says:
      </p>
      <p style="margin:0;font-size:15px;color:#212529;font-style:italic;line-height:1.6;">
        &ldquo;{$safePreview}&rdquo;
      </p>
    </td>
  </tr>
</table>
HTML;

    $content .= _emailButton($safeUrl, 'View & Reply');
    $content .= _emailHelperText('To manage your notification preferences, visit your account settings.');
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        This notification was sent by <strong>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong>.
        If you believe this is an error, contact
        <a href="mailto:support@globexsky.com" style="color:#1a73e8;text-decoration:none;">support@globexsky.com</a>.
      </p>';

    return emailBase($content, "New message from {$safeSender} — {$appName}");
}

<?php
/**
 * GlobexSky — Email Verification Template
 */

require_once __DIR__ . '/base.php';

/**
 * Build the email-verification email.
 *
 * @param string $name            User display name
 * @param string $verifyUrl       Verification link
 * @param string $otp             6-digit OTP (shown as alternative to link)
 * @return string Complete HTML email
 */
function emailEmailVerification(string $name, string $verifyUrl, string $otp = ''): string
{
    $appName  = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp  = htmlspecialchars($otp,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $otpBlock = $otp ? <<<HTML
<div style="background-color:#f0f4f8;border-radius:6px;padding:18px 24px;margin:20px 0;text-align:center;">
  <p style="margin:0 0 6px;font-size:13px;color:#6c757d;">Or use this one-time code:</p>
  <span style="font-size:32px;font-weight:700;letter-spacing:8px;color:#1a73e8;">{$safeOtp}</span>
</div>
HTML : '';

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a2e;">
  Verify Your Email Address
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#495057;line-height:1.6;">
  Hi {$safeName}, please verify your email address to activate your {$appName} account.
  Click the button below — this link expires in <strong>24 hours</strong>.
</p>
HTML;

    $content .= _emailButton($verifyUrl, 'Verify Email Address');
    $content .= $otpBlock;
    $content .= _emailHelperText("If you didn't create an account on {$appName}, you can safely ignore this email.");
    $content .= _emailDivider();
    $content .= '<p style="margin:0;font-size:13px;color:#6c757d;text-align:center;">
        Trouble clicking? Copy and paste this URL:<br>
        <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '"
           style="color:#1a73e8;word-break:break-all;">'
        . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</a>
      </p>';

    return emailBase($content, "Verify your email — {$appName}");
}

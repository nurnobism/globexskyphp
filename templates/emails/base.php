<?php
/**
 * GlobexSky Email Base Template
 *
 * Provides a responsive, inline-CSS HTML email shell used by all transactional
 * emails. Max-width 600 px, GlobexSky brand colours (#1a73e8 primary, #f8f9fa bg).
 */

/**
 * Wrap $content in a full HTML email document.
 *
 * @param string $content Inner HTML (pre-styled rows / sections)
 * @param string $title   <title> and hidden preheader text
 * @return string Complete HTML email string
 */
function emailBase(string $content, string $title = 'GlobexSky'): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'GlobexSky';
    $appUrl  = defined('APP_URL')  ? APP_URL  : 'https://globexsky.com';
    $year    = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$title}</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

  <!-- Outer wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f8;padding:32px 16px;">
    <tr>
      <td align="center">

        <!-- Card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background-color:#1a73e8;padding:28px 40px;text-align:center;">
              <a href="{$appUrl}" style="text-decoration:none;">
                <span style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">{$appName}</span>
                <span style="font-size:10px;color:#93c5fd;display:block;letter-spacing:2px;text-transform:uppercase;margin-top:2px;">B2B Marketplace</span>
              </a>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 40px 32px;">
              {$content}
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f8f9fa;padding:20px 40px;border-top:1px solid #e9ecef;text-align:center;">
              <p style="margin:0 0 8px;font-size:12px;color:#6c757d;">
                &copy; {$year} {$appName}. All rights reserved.
              </p>
              <p style="margin:0;font-size:12px;color:#adb5bd;">
                You received this email because you have an account on
                <a href="{$appUrl}" style="color:#1a73e8;text-decoration:none;">{$appName}</a>.
              </p>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>
  <!-- /Outer wrapper -->

</body>
</html>
HTML;
}

/**
 * Shared helper: a full-width call-to-action button.
 */
function _emailButton(string $url, string $label, string $color = '#1a73e8'): string
{
    return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 0;">
  <tr>
    <td align="center">
      <a href="{$url}"
         style="display:inline-block;background-color:{$color};color:#ffffff;font-size:15px;font-weight:600;
                text-decoration:none;padding:13px 36px;border-radius:6px;letter-spacing:0.3px;">
        {$label}
      </a>
    </td>
  </tr>
</table>
HTML;
}

/**
 * Shared helper: a divider line.
 */
function _emailDivider(): string
{
    return '<hr style="border:none;border-top:1px solid #e9ecef;margin:28px 0;">';
}

/**
 * Shared helper: small muted helper text below a button.
 */
function _emailHelperText(string $text): string
{
    return "<p style=\"margin:16px 0 0;font-size:12px;color:#6c757d;text-align:center;\">{$text}</p>";
}

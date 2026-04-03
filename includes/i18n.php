<?php
/**
 * includes/i18n.php — Internationalization Engine (Phase 10)
 * Usage: __('key') to translate, setLocale('fr') to switch language
 */

$_i18n_strings = [];
$_i18n_locale  = 'en';

/** Initialize i18n — call once, early */
function i18nInit(): void {
    global $_i18n_locale;
    // Priority: GET param > cookie > browser header > default
    $lang = $_GET['lang'] ?? ($_COOKIE['lang'] ?? null);
    if (!$lang) {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $parts  = explode(',', $accept);
        $lang   = strtolower(substr(trim($parts[0]), 0, 2));
    }
    $lang = preg_replace('/[^a-z]/', '', strtolower((string)$lang));
    $available = getAvailableLanguages();
    if (!isset($available[$lang])) {
        $lang = getenv('DEFAULT_LANGUAGE') ?: 'en';
    }
    setAppLocale($lang);
    // Store in cookie if explicitly set via GET
    if (isset($_GET['lang'])) {
        setcookie('lang', $lang, time() + 31536000, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

/** Set active locale and load strings */
function setAppLocale(string $lang): void {
    global $_i18n_locale, $_i18n_strings;
    $lang = preg_replace('/[^a-z]/', '', strtolower($lang));
    $file = __DIR__ . '/../lang/' . $lang . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../lang/en.php';
        $lang = 'en';
    }
    $_i18n_locale  = $lang;
    $_i18n_strings = include $file;
    if (!is_array($_i18n_strings)) {
        $_i18n_strings = [];
    }
}

/** Translate a key, with optional placeholder replacement */
function __(string $key, array $replace = []): string {
    global $_i18n_strings;
    $str = $_i18n_strings[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, (string)$v, $str);
    }
    return $str;
}

/** Get current locale code */
function getLocale(): string {
    global $_i18n_locale;
    return $_i18n_locale;
}

/** Check if current locale is RTL */
function isRTL(): bool {
    return in_array(getLocale(), ['ar', 'he', 'fa', 'ur'], true);
}

/** Get all available languages */
function getAvailableLanguages(): array {
    return [
        'en' => ['name' => 'English',    'native' => 'English',    'flag' => '🇺🇸', 'rtl' => false],
        'zh' => ['name' => 'Chinese',    'native' => '中文',         'flag' => '🇨🇳', 'rtl' => false],
        'ar' => ['name' => 'Arabic',     'native' => 'العربية',     'flag' => '🇸🇦', 'rtl' => true ],
        'es' => ['name' => 'Spanish',    'native' => 'Español',     'flag' => '🇪🇸', 'rtl' => false],
        'fr' => ['name' => 'French',     'native' => 'Français',    'flag' => '🇫🇷', 'rtl' => false],
        'de' => ['name' => 'German',     'native' => 'Deutsch',     'flag' => '🇩🇪', 'rtl' => false],
        'ja' => ['name' => 'Japanese',   'native' => '日本語',        'flag' => '🇯🇵', 'rtl' => false],
        'ko' => ['name' => 'Korean',     'native' => '한국어',        'flag' => '🇰🇷', 'rtl' => false],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português',   'flag' => '🇧🇷', 'rtl' => false],
        'ru' => ['name' => 'Russian',    'native' => 'Русский',     'flag' => '🇷🇺', 'rtl' => false],
        'hi' => ['name' => 'Hindi',      'native' => 'हिन्दी',       'flag' => '🇮🇳', 'rtl' => false],
        'bn' => ['name' => 'Bengali',    'native' => 'বাংলা',        'flag' => '🇧🇩', 'rtl' => false],
        'tr' => ['name' => 'Turkish',    'native' => 'Türkçe',      'flag' => '🇹🇷', 'rtl' => false],
        'it' => ['name' => 'Italian',    'native' => 'Italiano',    'flag' => '🇮🇹', 'rtl' => false],
        'nl' => ['name' => 'Dutch',      'native' => 'Nederlands',  'flag' => '🇳🇱', 'rtl' => false],
        'th' => ['name' => 'Thai',       'native' => 'ภาษาไทย',      'flag' => '🇹🇭', 'rtl' => false],
        'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'flag' => '🇻🇳', 'rtl' => false],
        'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia','flag'=>'🇮🇩','rtl'=>false],
        'ms' => ['name' => 'Malay',      'native' => 'Bahasa Melayu','flag' => '🇲🇾', 'rtl' => false],
        'pl' => ['name' => 'Polish',     'native' => 'Polski',      'flag' => '🇵🇱', 'rtl' => false],
    ];
}

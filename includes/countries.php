<?php
/**
 * includes/countries.php — Country Reference Data (PR #12)
 *
 * Provides ISO 3166-1 alpha-2 country codes, names, flag emojis,
 * and continent groupings for use in dropdowns and tax configuration.
 */

/**
 * Get the full list of countries with flag emoji.
 *
 * @return array[]  Each entry: ['code', 'name', 'flag', 'continent']
 */
function getCountryList(): array
{
    static $countries = null;
    if ($countries !== null) return $countries;

    $countries = [
        // North America
        ['code' => 'US', 'name' => 'United States',        'flag' => '🇺🇸', 'continent' => 'North America'],
        ['code' => 'CA', 'name' => 'Canada',               'flag' => '🇨🇦', 'continent' => 'North America'],
        ['code' => 'MX', 'name' => 'Mexico',               'flag' => '🇲🇽', 'continent' => 'North America'],
        // South America
        ['code' => 'BR', 'name' => 'Brazil',               'flag' => '🇧🇷', 'continent' => 'South America'],
        ['code' => 'AR', 'name' => 'Argentina',            'flag' => '🇦🇷', 'continent' => 'South America'],
        ['code' => 'CL', 'name' => 'Chile',                'flag' => '🇨🇱', 'continent' => 'South America'],
        ['code' => 'CO', 'name' => 'Colombia',             'flag' => '🇨🇴', 'continent' => 'South America'],
        // Europe
        ['code' => 'GB', 'name' => 'United Kingdom',       'flag' => '🇬🇧', 'continent' => 'Europe'],
        ['code' => 'DE', 'name' => 'Germany',              'flag' => '🇩🇪', 'continent' => 'Europe'],
        ['code' => 'FR', 'name' => 'France',               'flag' => '🇫🇷', 'continent' => 'Europe'],
        ['code' => 'IT', 'name' => 'Italy',                'flag' => '🇮🇹', 'continent' => 'Europe'],
        ['code' => 'ES', 'name' => 'Spain',                'flag' => '🇪🇸', 'continent' => 'Europe'],
        ['code' => 'NL', 'name' => 'Netherlands',          'flag' => '🇳🇱', 'continent' => 'Europe'],
        ['code' => 'BE', 'name' => 'Belgium',              'flag' => '🇧🇪', 'continent' => 'Europe'],
        ['code' => 'AT', 'name' => 'Austria',              'flag' => '🇦🇹', 'continent' => 'Europe'],
        ['code' => 'CH', 'name' => 'Switzerland',          'flag' => '🇨🇭', 'continent' => 'Europe'],
        ['code' => 'SE', 'name' => 'Sweden',               'flag' => '🇸🇪', 'continent' => 'Europe'],
        ['code' => 'NO', 'name' => 'Norway',               'flag' => '🇳🇴', 'continent' => 'Europe'],
        ['code' => 'DK', 'name' => 'Denmark',              'flag' => '🇩🇰', 'continent' => 'Europe'],
        ['code' => 'FI', 'name' => 'Finland',              'flag' => '🇫🇮', 'continent' => 'Europe'],
        ['code' => 'PL', 'name' => 'Poland',               'flag' => '🇵🇱', 'continent' => 'Europe'],
        ['code' => 'PT', 'name' => 'Portugal',             'flag' => '🇵🇹', 'continent' => 'Europe'],
        ['code' => 'IE', 'name' => 'Ireland',              'flag' => '🇮🇪', 'continent' => 'Europe'],
        ['code' => 'GR', 'name' => 'Greece',               'flag' => '🇬🇷', 'continent' => 'Europe'],
        ['code' => 'CZ', 'name' => 'Czech Republic',       'flag' => '🇨🇿', 'continent' => 'Europe'],
        ['code' => 'HU', 'name' => 'Hungary',              'flag' => '🇭🇺', 'continent' => 'Europe'],
        ['code' => 'RO', 'name' => 'Romania',              'flag' => '🇷🇴', 'continent' => 'Europe'],
        ['code' => 'RU', 'name' => 'Russia',               'flag' => '🇷🇺', 'continent' => 'Europe'],
        ['code' => 'TR', 'name' => 'Turkey',               'flag' => '🇹🇷', 'continent' => 'Europe'],
        ['code' => 'UA', 'name' => 'Ukraine',              'flag' => '🇺🇦', 'continent' => 'Europe'],
        // Asia
        ['code' => 'CN', 'name' => 'China',                'flag' => '🇨🇳', 'continent' => 'Asia'],
        ['code' => 'JP', 'name' => 'Japan',                'flag' => '🇯🇵', 'continent' => 'Asia'],
        ['code' => 'KR', 'name' => 'South Korea',          'flag' => '🇰🇷', 'continent' => 'Asia'],
        ['code' => 'IN', 'name' => 'India',                'flag' => '🇮🇳', 'continent' => 'Asia'],
        ['code' => 'BD', 'name' => 'Bangladesh',           'flag' => '🇧🇩', 'continent' => 'Asia'],
        ['code' => 'PK', 'name' => 'Pakistan',             'flag' => '🇵🇰', 'continent' => 'Asia'],
        ['code' => 'SG', 'name' => 'Singapore',            'flag' => '🇸🇬', 'continent' => 'Asia'],
        ['code' => 'MY', 'name' => 'Malaysia',             'flag' => '🇲🇾', 'continent' => 'Asia'],
        ['code' => 'TH', 'name' => 'Thailand',             'flag' => '🇹🇭', 'continent' => 'Asia'],
        ['code' => 'PH', 'name' => 'Philippines',          'flag' => '🇵🇭', 'continent' => 'Asia'],
        ['code' => 'ID', 'name' => 'Indonesia',            'flag' => '🇮🇩', 'continent' => 'Asia'],
        ['code' => 'VN', 'name' => 'Vietnam',              'flag' => '🇻🇳', 'continent' => 'Asia'],
        ['code' => 'HK', 'name' => 'Hong Kong',            'flag' => '🇭🇰', 'continent' => 'Asia'],
        ['code' => 'TW', 'name' => 'Taiwan',               'flag' => '🇹🇼', 'continent' => 'Asia'],
        // Middle East
        ['code' => 'AE', 'name' => 'United Arab Emirates', 'flag' => '🇦🇪', 'continent' => 'Middle East'],
        ['code' => 'SA', 'name' => 'Saudi Arabia',         'flag' => '🇸🇦', 'continent' => 'Middle East'],
        ['code' => 'IL', 'name' => 'Israel',               'flag' => '🇮🇱', 'continent' => 'Middle East'],
        ['code' => 'KW', 'name' => 'Kuwait',               'flag' => '🇰🇼', 'continent' => 'Middle East'],
        ['code' => 'QA', 'name' => 'Qatar',                'flag' => '🇶🇦', 'continent' => 'Middle East'],
        // Oceania
        ['code' => 'AU', 'name' => 'Australia',            'flag' => '🇦🇺', 'continent' => 'Oceania'],
        ['code' => 'NZ', 'name' => 'New Zealand',          'flag' => '🇳🇿', 'continent' => 'Oceania'],
        // Africa
        ['code' => 'ZA', 'name' => 'South Africa',         'flag' => '🇿🇦', 'continent' => 'Africa'],
        ['code' => 'NG', 'name' => 'Nigeria',              'flag' => '🇳🇬', 'continent' => 'Africa'],
        ['code' => 'EG', 'name' => 'Egypt',                'flag' => '🇪🇬', 'continent' => 'Africa'],
        ['code' => 'KE', 'name' => 'Kenya',                'flag' => '🇰🇪', 'continent' => 'Africa'],
        ['code' => 'GH', 'name' => 'Ghana',                'flag' => '🇬🇭', 'continent' => 'Africa'],
        ['code' => 'MA', 'name' => 'Morocco',              'flag' => '🇲🇦', 'continent' => 'Africa'],
    ];
    return $countries;
}

/**
 * Get a country name by its ISO code.
 */
function getCountryName(string $code): string
{
    $code = strtoupper(trim($code));
    foreach (getCountryList() as $c) {
        if ($c['code'] === $code) return $c['name'];
    }
    return $code;
}

/**
 * Get country flag emoji by ISO code.
 */
function getCountryFlag(string $code): string
{
    $code = strtoupper(trim($code));
    foreach (getCountryList() as $c) {
        if ($c['code'] === $code) return $c['flag'];
    }
    return '';
}

/**
 * EU member country codes (for VAT reverse-charge logic).
 */
function getEuCountryCodes(): array
{
    return ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
            'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
            'NL','PL','PT','RO','SE','SI','SK'];
}

/**
 * Check if a country is an EU member.
 */
function isEuCountry(string $code): bool
{
    return in_array(strtoupper(trim($code)), getEuCountryCodes(), true);
}

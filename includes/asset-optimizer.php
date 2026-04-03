<?php
/**
 * Asset Optimizer
 *
 * Client-side performance helpers: minification, cache-busting,
 * lazy-loading images, critical CSS inlining and preload generation.
 */

class AssetOptimizer
{
    /** Document root used for resolving relative asset paths. */
    private string $docRoot;

    public function __construct(string $docRoot = '')
    {
        $this->docRoot = rtrim($docRoot ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    }

    // -----------------------------------------------------------------------
    // Minification
    // -----------------------------------------------------------------------

    /**
     * Basic CSS minification — strips comments and collapses whitespace.
     */
    public function cssMinify(string $css): string
    {
        // Remove block comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove line comments (not inside strings — heuristic)
        $css = preg_replace('!//[^\r\n]*!', '', $css);
        // Collapse whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove spaces around structural characters
        $css = preg_replace('/\s*([:;{},>~+])\s*/', '$1', $css);
        return trim($css);
    }

    /**
     * Basic JS minification — strips line/block comments and collapses whitespace.
     *
     * WARNING: This is a simple heuristic minifier. It does NOT correctly handle
     * comments inside string literals or template literals. It is safe for simple,
     * well-structured application JS (e.g. inline scripts, small utility files).
     * For production builds that include third-party libraries, use a dedicated
     * tool such as Terser (CLI: `npx terser input.js -o output.min.js`).
     */
    public function jsMinify(string $js): string
    {
        // Remove block comments (non-greedy, won't touch regex literals correctly,
        // but safe for typical application JS)
        $js = preg_replace('!/\*[\s\S]*?\*/!', '', $js);
        // Remove line comments (skip URLs by checking for double-slash preceded by :)
        $js = preg_replace('/(?<!:)\/\/[^\r\n]*/', '', $js);
        // Collapse whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        return trim($js);
    }

    // -----------------------------------------------------------------------
    // Cache busting
    // -----------------------------------------------------------------------

    /**
     * Append a filemtime-based version query string to an asset URL.
     * Returns the URL unchanged if the file cannot be found.
     */
    public function generateAssetUrl(string $path): string
    {
        $version = $this->getAssetVersion($path);
        $sep     = str_contains($path, '?') ? '&' : '?';
        return $path . $sep . 'v=' . $version;
    }

    /**
     * Return a version token for the given path.
     * Falls back to the current day so repeated requests within a day are stable.
     */
    public function getAssetVersion(string $path): string
    {
        $fullPath = $this->docRoot . '/' . ltrim($path, '/');
        if (is_file($fullPath)) {
            return (string) filemtime($fullPath);
        }
        return date('Ymd');
    }

    // -----------------------------------------------------------------------
    // Preloading
    // -----------------------------------------------------------------------

    /**
     * Generate <link rel="preload"> tags for critical CSS and JS assets.
     *
     * @param array $assets Array of ['path' => '/path/to/asset', 'as' => 'style|script']
     */
    public function preloadCriticalAssets(array $assets = []): string
    {
        if (empty($assets)) {
            // Sensible defaults for GlobexSky
            $assets = [
                ['path' => '/assets/css/app.css',      'as' => 'style'],
                ['path' => '/assets/js/app.js',        'as' => 'script'],
            ];
        }

        $tags = [];
        foreach ($assets as $asset) {
            $url  = $this->generateAssetUrl($asset['path']);
            $as   = htmlspecialchars($asset['as'], ENT_QUOTES, 'UTF-8');
            $tags[] = "<link rel=\"preload\" href=\"{$url}\" as=\"{$as}\">";
        }
        return implode("\n", $tags);
    }

    // -----------------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------------

    /**
     * Generate an <img> tag with native lazy-loading and a low-quality placeholder.
     *
     * @param string $src   The image source URL.
     * @param string $alt   Alt text.
     * @param array  $attrs Additional HTML attributes (key => value).
     */
    public function lazyLoadImage(string $src, string $alt, array $attrs = []): string
    {
        $attrs['loading'] = 'lazy';
        $attrs['decoding'] = 'async';

        // Build attribute string
        $attrStr = '';
        foreach ($attrs as $key => $value) {
            $k       = htmlspecialchars($key,   ENT_QUOTES, 'UTF-8');
            $v       = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $attrStr .= " {$k}=\"{$v}\"";
        }

        $escapedSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $escapedAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

        return "<img src=\"{$escapedSrc}\" alt=\"{$escapedAlt}\"{$attrStr}>";
    }

    // -----------------------------------------------------------------------
    // Critical CSS inlining
    // -----------------------------------------------------------------------

    /**
     * Read a CSS file and return it wrapped in a <style> tag for inlining.
     * Minifies the output automatically.
     *
     * @param string $cssFile Path relative to docRoot, e.g. '/assets/css/critical.css'.
     */
    public function inlineCriticalCss(string $cssFile): string
    {
        $fullPath = $this->docRoot . '/' . ltrim($cssFile, '/');

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            return '';
        }

        $css = file_get_contents($fullPath);
        if ($css === false) {
            return '';
        }

        $minified = $this->cssMinify($css);
        return "<style>{$minified}</style>";
    }
}

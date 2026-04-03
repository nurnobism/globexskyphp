<?php
/**
 * Security Validator & Sanitizer
 *
 * Comprehensive input validation and sanitisation helpers.
 * All methods are static — no instantiation required.
 */

class SecurityValidator
{
    // -----------------------------------------------------------------------
    // Sanitisers
    // -----------------------------------------------------------------------

    /**
     * Strip tags, trim whitespace and HTML-encode a general string.
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim(strip_tags($input)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitise an email address (remove illegal characters).
     */
    public static function sanitizeEmail(string $input): string
    {
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Cast input to integer.
     */
    public static function sanitizeInt(mixed $input): int
    {
        return intval($input);
    }

    /**
     * Sanitise a URL.
     */
    public static function sanitizeUrl(string $input): string
    {
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }

    // -----------------------------------------------------------------------
    // Validators
    // -----------------------------------------------------------------------

    /**
     * Validate an email address.
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a password.
     * Requirements: ≥8 chars, upper, lower, digit, special character.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[\W_]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate an international phone number (E.164-ish, 7–15 digits with optional +).
     */
    public static function validatePhone(string $phone): bool
    {
        return (bool) preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/[\s\-\(\)]/', '', $phone));
    }

    /**
     * Validate a file upload.
     *
     * @param array    $file         $_FILES['field'] element
     * @param string[] $allowedTypes Allowed MIME types, e.g. ['image/jpeg', 'image/png']
     * @param int      $maxSize      Maximum size in bytes
     * @return array{valid: bool, errors: string[]}
     */
    public static function validateFileUpload(array $file, array $allowedTypes, int $maxSize): array
    {
        $errors = [];

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No valid file uploaded.';
            return ['valid' => false, 'errors' => $errors];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error code: ' . $file['error'];
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'File exceeds maximum size of ' . round($maxSize / 1048576, 1) . ' MB.';
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes, true)) {
            $errors[] = 'File type "' . $mimeType . '" is not allowed.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a CSRF token against the value stored in the session.
     */
    public static function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        $stored = $_SESSION['csrf_token'] ?? '';
        return hash_equals($stored, $token);
    }

    // -----------------------------------------------------------------------
    // Escape helpers
    // -----------------------------------------------------------------------

    /**
     * Escape a value for safe embedding inside a JavaScript string literal.
     */
    public static function escapeForJs(string $input): string
    {
        return addslashes(
            str_replace(
                ['</script>', "\r\n", "\r", "\n"],
                ['<\/script>', '\n', '\n', '\n'],
                $input
            )
        );
    }

    /**
     * Escape a value for safe use inside an HTML attribute.
     */
    public static function escapeForAttribute(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitise a filename — strip path traversal and special characters.
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove any directory components
        $filename = basename($filename);
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);
        // Allow only safe characters
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        // Prevent leading dots (hidden files)
        $filename = ltrim($filename, '.');
        return $filename ?: 'file';
    }

    // -----------------------------------------------------------------------
    // Detection helpers
    // -----------------------------------------------------------------------

    /**
     * Detect common XSS patterns in a string.
     * Returns true if suspicious content is found.
     */
    public static function detectXss(string $input): bool
    {
        $patterns = [
            '/<script[\s>]/i',
            '/javascript\s*:/i',
            '/on\w+\s*=/i',         // e.g. onclick=, onerror=
            '/<\s*iframe/i',
            '/<\s*object/i',
            '/<\s*embed/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/vbscript\s*:/i',
            '/data\s*:\s*text\/html/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect common SQL injection patterns in a string.
     * Returns true if suspicious content is found.
     *
     * NOTE: This is a heuristic aid — always use prepared statements as the
     * primary defence against SQL injection.
     */
    public static function detectSqlInjection(string $input): bool
    {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/--\s/',
            '/;\s*(drop|alter|create|insert|update|delete)\b/i',
            "/'\s*or\s+'?\d/i",
            '/\bexec\s*\(/i',
            '/\bxp_cmdshell\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }
}

<?php
// ================================================================
//  OPTMS Invoice Manager — config/env.php
//  Minimal .env loader (no Composer/vlucas dependency needed).
//  Reads KEY=VALUE pairs from a .env file into getenv()/$_ENV,
//  WITHOUT overwriting any real environment variables the host
//  (cPanel/Apache) may already provide.
// ================================================================

function loadEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        error_log("loadEnv: .env file not found or not readable at {$path}");
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) continue;

        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes: "value" or 'value'
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Don't clobber a variable that's already set at the OS/webserver level
        if (getenv($key) !== false) continue;

        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

// Small typed helper so callers don't have to keep casting getenv() output.
function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value === false) return $default;

    return match (strtolower($value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
}

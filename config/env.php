<?php
declare(strict_types=1);

use Dotenv\Dotenv;

if (!function_exists('skilltrust_env_parse_file')) {
    function skilltrust_env_parse_file(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('skilltrust_env_load')) {
    function skilltrust_env_load(?string $rootPath = null): void
    {
        static $loaded = [];

        $basePath = $rootPath ?? dirname(__DIR__);
        $realBasePath = realpath($basePath) ?: $basePath;
        if (isset($loaded[$realBasePath])) {
            return;
        }

        $autoloadPath = $realBasePath . '/vendor/autoload.php';
        if (is_file($autoloadPath) && is_readable($autoloadPath)) {
            require_once $autoloadPath;
        }

        $envPath = $realBasePath . '/.env';
        if (is_file($envPath) && is_readable($envPath)) {
            if (class_exists(Dotenv::class)) {
                try {
                    Dotenv::createUnsafeImmutable($realBasePath)->safeLoad();
                } catch (Throwable $exception) {
                    skilltrust_env_parse_file($envPath);
                }
            } else {
                skilltrust_env_parse_file($envPath);
            }
        }

        $loaded[$realBasePath] = true;
    }
}

if (!function_exists('skilltrust_env_get')) {
    function skilltrust_env_get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : $default;
    }
}

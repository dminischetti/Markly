<?php
declare(strict_types=1);

namespace Markly;

/**
 * Csrf
 * Issues and validates short-lived tokens for state-changing requests.
 */
final class Csrf
{
    private const KEY = '_csrf_tokens';
    private const TTL = 1800;

    public static function issue(): string
    {
        self::ensureSession();
        $tokens = &$_SESSION[self::KEY];
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = time() + self::TTL;

        if (count($tokens) > 5) {
            asort($tokens);
            $tokens = array_slice($tokens, -5, null, true);
        }

        return $token;
    }

    public static function validate(?string $token): bool
    {
        self::ensureSession();
        if ($token === null || $token === '') {
            return false;
        }

        $tokens = &$_SESSION[self::KEY];
        $now = time();

        foreach ($tokens as $stored => $expiry) {
            if ($expiry < $now) {
                unset($tokens[$stored]);
            }
        }

        if (!isset($tokens[$token])) {
            return false;
        }

        if ($tokens[$token] < $now) {
            unset($tokens[$token]);
            return false;
        }

        unset($tokens[$token]);

        return true;
    }

    public static function require(?string $token): void
    {
        if (!self::validate($token)) {
            Response::json(['error' => 'invalid_csrf'], 400);
        }
    }

    public static function tokenFromRequest(): ?string
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($header) && $header !== '') {
            return $header;
        }

        if (isset($_POST['_token']) && is_string($_POST['_token'])) {
            return $_POST['_token'];
        }

        return null;
    }

    public static function reset(): void
    {
        self::ensureSession();
        $_SESSION[self::KEY] = [];
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::KEY]) || !is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
    }
}

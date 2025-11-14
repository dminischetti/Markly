<?php
declare(strict_types=1);

namespace Markly;

use PDO;
use RuntimeException;

/**
 * Auth
 * Manages session lifecycle, login/logout, and production hosting-safe cookie handling.
 */
final class Auth
{
    private PDO $pdo;
    private string $sessionName;
    private bool $sessionStarted = false;
    private int $regenInterval;
    private bool $demoMode;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->sessionName = (string)($config['session_name'] ?? 'markly_session');
        $this->regenInterval = max(0, (int)($config['session_regen_interval'] ?? 300));
        $this->demoMode = (bool)($config['demo_mode'] ?? false);
        if ($this->demoMode) {
            $this->regenInterval = 0;
        }
        $this->startSession();
    }

    public function startSession(): void
    {
        if ($this->sessionStarted) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->sessionStarted = true;
            return;
        }

        session_save_path(sys_get_temp_dir());

        $isHttps = $this->isHttps();
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name($this->sessionName);
        if (!session_start()) {
            throw new RuntimeException('Unable to start session.');
        }

        $this->sessionStarted = true;

        if (!isset($_SESSION['initiated'])) {
            $_SESSION['initiated'] = true;
            session_regenerate_id(true);
        }
    }

    public function check(): bool
    {
        $this->maybeRegenerate();
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    /**
     * @return array{id:int,email:string}|null
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        /** @var array{id:int,email:string} $user */
        $user = $_SESSION['user'];
        return $user;
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, email, pw_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, (string)$row['pw_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id'    => (int)$row['id'],
            'email' => (string)$row['email'],
        ];
        $_SESSION['last_regen'] = time();
        session_regenerate_id(true);
        Csrf::reset();

        return true;
    }

    public function logout(): void
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }

        $_SESSION = [];
        Csrf::reset();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        $this->sessionStarted = false;
    }

    private function maybeRegenerate(): void
    {
        if ($this->regenInterval <= 0) {
            return;
        }

        $last = (int)($_SESSION['last_regen'] ?? 0);
        if ($last === 0 || (time() - $last) > $this->regenInterval) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
            return true;
        }

        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }
}

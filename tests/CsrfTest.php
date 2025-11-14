<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Markly\Csrf;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        Csrf::reset();
    }

    public function testIssueAndValidate(): void
    {
        $token = Csrf::issue();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes hex-encoded
        $this->assertTrue(Csrf::validate($token));
    }

    public function testTokenExpiration(): void
    {
        $token = Csrf::issue();

        // Manually expire token
        $_SESSION[Csrf::class]['_csrf_tokens'][$token] = time() - 1;

        $this->assertFalse(Csrf::validate($token));
    }

    public function testTokenPool(): void
    {
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokens[] = Csrf::issue();
        }

        // All tokens should be valid
        foreach ($tokens as $token) {
            $this->assertTrue(Csrf::validate($token));
        }
    }

    public function testInvalidToken(): void
    {
        $this->assertFalse(Csrf::validate('invalid_token'));
        $this->assertFalse(Csrf::validate(null));
        $this->assertFalse(Csrf::validate(''));
    }
}

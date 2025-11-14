<?php
declare(strict_types=1);

namespace Markly;

/**
 * Response
 * Emits JSON responses with production hosting-safe caching directives.
 */
final class Response
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    public static function json(array $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

<?php
declare(strict_types=1);

namespace Markly;

use PDO;

/**
 * LinksRepo
 * Computes backlinks and lightweight knowledge graph summaries.
 * Falls back to safe stubs when optional features are disabled.
 */
final class LinksRepo
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return list<array{id:int,slug:string,title:string}>
     */
    public function backlinks(int $userId, string $slug): array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, title FROM notes WHERE user_id = :uid AND slug <> :slug AND content LIKE :needle ORDER BY updated_at DESC LIMIT 10');
        $stmt->execute([
            'uid'    => $userId,
            'slug'   => $slug,
            'needle' => '%'.$slug.'%',
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{id:int,slug:string,title:string,score:float}>
     */
    public function related(int $userId, int $noteId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, title, content FROM notes WHERE user_id = :uid AND id = :id LIMIT 1');
        $stmt->execute(['uid' => $userId, 'id' => $noteId]);
        $note = $stmt->fetch();
        if (!$note) {
            return [];
        }

        $baseTokens = array_unique(TextUtil::tokenize((string)$note['title'] . ' ' . (string)$note['content']));
        if ($baseTokens === []) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT id, slug, title, content FROM notes WHERE user_id = :uid AND id <> :id');
        $stmt->execute(['uid' => $userId, 'id' => $noteId]);

        $candidates = [];
        while ($row = $stmt->fetch()) {
            $tokens = array_unique(TextUtil::tokenize((string)$row['title'] . ' ' . (string)$row['content']));
            if ($tokens === []) {
                continue;
            }
            $intersection = count(array_intersect($baseTokens, $tokens));
            $union = count(array_unique(array_merge($baseTokens, $tokens)));
            if ($union === 0) {
                continue;
            }
            $score = $intersection / $union;
            if ($score <= 0.05) {
                continue;
            }
            $candidates[] = [
                'id'    => (int)$row['id'],
                'slug'  => (string)$row['slug'],
                'title' => (string)$row['title'],
                'score' => round($score, 3),
            ];
        }

        usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, 5);
    }

    /**
     * @return array{nodes:list<array{id:int,slug:string,title:string}>,edges:list<array{source:string,target:string}>}
     */
    public function graph(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, title, content FROM notes WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll() ?: [];

        $nodes = [];
        $slugMap = [];
        foreach ($rows as $row) {
            $slug = (string)$row['slug'];
            $nodes[] = [
                'id'    => (int)$row['id'],
                'slug'  => $slug,
                'title' => (string)$row['title'],
            ];
            $slugMap[$slug] = true;
        }

        $edges = [];
        foreach ($rows as $row) {
            $source = (string)$row['slug'];
            $content = (string)$row['content'];
            if ($content === '') {
                continue;
            }
            $matches = [];
            preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $content, $matches);
            foreach ($matches[1] ?? [] as $url) {
                $url = trim($url);
                if ($url === '') {
                    continue;
                }
                $targetSlug = self::extractSlugFromUrl($url);
                if ($targetSlug !== null && isset($slugMap[$targetSlug])) {
                    $edges[] = [
                        'source' => $source,
                        'target' => $targetSlug,
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    private static function extractSlugFromUrl(string $url): ?string
    {
        if (str_starts_with($url, '#/n/')) {
            return substr($url, 4);
        }
        if (str_starts_with($url, '/')) {
            return ltrim($url, '/');
        }
        if (preg_match('/^[a-z0-9\-]+$/i', $url) === 1) {
            return $url;
        }

        return null;
    }
}

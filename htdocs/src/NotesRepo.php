<?php
declare(strict_types=1);

namespace Markly;

use PDO;
use PDOException;
use RuntimeException;

/**
 * NotesRepo
 * Handles CRUD operations for Markdown notes.
 * Ensures production hosting-safe PDO transactions with optimistic locking.
 */
final class NotesRepo
{
    private PDO $pdo;
    private bool $hasFulltext;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->hasFulltext = $this->detectFulltext();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, title, tags, is_public, version, updated_at FROM notes WHERE user_id = :uid ORDER BY updated_at DESC');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<string>
     */
    public function tagsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT tags FROM notes WHERE user_id = :uid AND tags <> ""');
        $stmt->execute(['uid' => $userId]);
        $tags = [];
        while ($row = $stmt->fetch()) {
            foreach (explode(',', (string)$row['tags']) as $tag) {
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }
                $tags[$tag] = true;
            }
        }

        ksort($tags);
        return array_keys($tags);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notes WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $note = $stmt->fetch();

        return $note ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notes WHERE slug = :slug AND user_id = :uid LIMIT 1');
        $stmt->execute(['slug' => $slug, 'uid' => $userId]);
        $note = $stmt->fetch();

        return $note ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublicBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notes WHERE slug = :slug AND is_public = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $note = $stmt->fetch();

        return $note ?: null;
    }

    /**
     * @param array{title:string,content:string,tags?:string,slug?:string} $data
     * @return array<string, mixed>
     */
    public function create(int $userId, array $data): array
    {
        $title = trim($data['title']);
        $content = $data['content'];
        $tags = TextUtil::normalizeTags($data['tags'] ?? '');
        $slug = $data['slug'] ?? TextUtil::slugify($title);
        $slug = $this->ensureUniqueSlug($slug);

        $stmt = $this->pdo->prepare('INSERT INTO notes (user_id, slug, title, content, tags) VALUES (:uid, :slug, :title, :content, :tags)');
        $stmt->execute([
            'uid'     => $userId,
            'slug'    => $slug,
            'title'   => $title,
            'content' => $content,
            'tags'    => $tags,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $note = $this->findById($id, $userId);
        if ($note === null) {
            throw new RuntimeException('failed_to_create_note');
        }

        return $note;
    }

    /**
     * @param array{title:string,content:string,tags?:string,slug?:string} $data
     * @return array<string, mixed>
     */
    public function update(int $id, int $userId, array $data, int $expectedVersion): array
    {
        $title = trim($data['title']);
        $content = $data['content'];
        $tags = TextUtil::normalizeTags($data['tags'] ?? '');
        $slug = $data['slug'] ?? TextUtil::slugify($title);
        $slug = $this->ensureUniqueSlug($slug, $id);

        $stmt = $this->pdo->prepare('UPDATE notes SET title = :title, slug = :slug, content = :content, tags = :tags, version = version + 1 WHERE id = :id AND user_id = :uid AND version = :version');
        $stmt->execute([
            'title'   => $title,
            'slug'    => $slug,
            'content' => $content,
            'tags'    => $tags,
            'id'      => $id,
            'uid'     => $userId,
            'version' => $expectedVersion,
        ]);

        if ($stmt->rowCount() === 0) {
            $existing = $this->findById($id, $userId);
            if ($existing === null) {
                throw new RuntimeException('not_found');
            }
            throw new RuntimeException('version_conflict');
        }

        $note = $this->findById($id, $userId);
        if ($note === null) {
            throw new RuntimeException('not_found');
        }

        return $note;
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notes WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function togglePublish(int $id, int $userId, bool $public): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notes SET is_public = :public WHERE id = :id AND user_id = :uid');
        $stmt->execute([
            'public' => $public ? 1 : 0,
            'id'     => $id,
            'uid'    => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(int $userId, string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if ($this->hasFulltext) {
            $sql = 'SELECT id, slug, title, tags, is_public, updated_at, MATCH(title, content) AGAINST (:term IN NATURAL LANGUAGE MODE) AS score FROM notes WHERE user_id = :uid AND MATCH(title, content) AGAINST (:term IN NATURAL LANGUAGE MODE) ORDER BY score DESC LIMIT 20';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['term' => $term, 'uid' => $userId]);
        } else {
            $like = '%' . $term . '%';
            $sql = 'SELECT id, slug, title, tags, is_public, updated_at FROM notes WHERE user_id = :uid AND (title LIKE :like OR content LIKE :like) ORDER BY updated_at DESC LIMIT 20';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['like' => $like, 'uid' => $userId]);
        }

        $results = $stmt->fetchAll() ?: [];
        foreach ($results as &$row) {
            if (!isset($row['score'])) {
                $row['score'] = 1.0;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByTag(int $userId, string $tag): array
    {
        $tag = trim($tag);
        if ($tag === '') {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT id, slug, title, tags, is_public, version, updated_at FROM notes WHERE user_id = :uid AND FIND_IN_SET(:tag, tags) > 0 ORDER BY updated_at DESC');
        $stmt->execute(['tag' => $tag, 'uid' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    private function ensureUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $suffix = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            ++$suffix;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM notes WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    private function detectFulltext(): bool
    {
        try {
            $this->pdo->query('SELECT MATCH(title, content) AGAINST("test") FROM notes LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}

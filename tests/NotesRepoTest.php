<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Markly\NotesRepo;

class NotesRepoTest extends TestCase
{
    private PDO $pdo;
    private NotesRepo $repo;

    protected function setUp(): void
    {
        // Use SQLite in-memory for tests
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create schema
        $this->pdo->exec('
            CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, pw_hash TEXT);
            CREATE TABLE notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                slug TEXT UNIQUE,
                title TEXT,
                content TEXT,
                tags TEXT DEFAULT "",
                is_public INTEGER DEFAULT 0,
                version INTEGER DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ');

        // Insert test user
        $this->pdo->exec('INSERT INTO users (id, email, pw_hash) VALUES (1, "test@test.com", "hash")');

        $this->repo = new NotesRepo($this->pdo);
    }

    public function testCreateNote(): void
    {
        $note = $this->repo->create(1, [
            'title' => 'Test Note',
            'content' => '# Test Content',
            'tags' => 'tag1,tag2',
        ]);

        $this->assertIsArray($note);
        $this->assertEquals('Test Note', $note['title']);
        $this->assertEquals(1, $note['version']);
        $this->assertNotEmpty($note['slug']);
    }

    public function testUpdateNoteWithVersionConflict(): void
    {
        $note = $this->repo->create(1, [
            'title' => 'Original',
            'content' => 'Content',
        ]);

        // Simulate concurrent update
        $this->pdo->exec("UPDATE notes SET version = 2 WHERE id = {$note['id']}");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('version_conflict');

        $this->repo->update($note['id'], 1, [
            'title' => 'Updated',
            'content' => 'New content',
        ], 1); // Old version
    }

    public function testSearchNotes(): void
    {
        $this->repo->create(1, [
            'title' => 'PHP Tutorial',
            'content' => 'Learn PHP basics',
        ]);

        $this->repo->create(1, [
            'title' => 'JavaScript Guide',
            'content' => 'Learn JavaScript',
        ]);

        $results = $this->repo->search(1, 'PHP');

        $this->assertCount(1, $results);
        $this->assertEquals('PHP Tutorial', $results[0]['title']);
    }

    public function testListByTag(): void
    {
        $this->repo->create(1, [
            'title' => 'Note 1',
            'content' => 'Content',
            'tags' => 'php,backend',
        ]);

        $this->repo->create(1, [
            'title' => 'Note 2',
            'content' => 'Content',
            'tags' => 'javascript,frontend',
        ]);

        $results = $this->repo->listByTag(1, 'php');

        $this->assertCount(1, $results);
        $this->assertEquals('Note 1', $results[0]['title']);
    }

    public function testSlugGeneration(): void
    {
        $note1 = $this->repo->create(1, [
            'title' => 'Test Note',
            'content' => 'Content',
        ]);

        $note2 = $this->repo->create(1, [
            'title' => 'Test Note', // Same title
            'content' => 'Different content',
        ]);

        $this->assertEquals('test-note', $note1['slug']);
        $this->assertEquals('test-note-2', $note2['slug']);
    }
}

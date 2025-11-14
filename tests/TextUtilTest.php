<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Markly\TextUtil;

class TextUtilTest extends TestCase
{
    public function testSlugify(): void
    {
        $this->assertEquals('hello-world', TextUtil::slugify('Hello World'));
        $this->assertEquals('cafe', TextUtil::slugify('Café'));
        $this->assertEquals('test-123', TextUtil::slugify('Test!@#123'));
    }

    public function testSlugifyEmpty(): void
    {
        $slug = TextUtil::slugify('');
        $this->assertStringStartsWith('note-', $slug);
    }

    public function testNormalizeTags(): void
    {
        $this->assertEquals('php,javascript', TextUtil::normalizeTags('PHP, JavaScript'));
        $this->assertEquals('tag1,tag2', TextUtil::normalizeTags('tag1;tag2'));
        $this->assertEquals('unique', TextUtil::normalizeTags('unique,unique,unique'));
    }

    public function testTokenize(): void
    {
        $tokens = TextUtil::tokenize('The quick brown fox jumps');

        // Should remove stopwords like 'the'
        $this->assertNotContains('the', $tokens);

        // Should stem words
        $this->assertContains('quick', $tokens);
        $this->assertContains('brown', $tokens);
    }
}

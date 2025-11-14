<?php
declare(strict_types=1);

namespace Markly;

/**
 * TextUtil
 * Provides slugification, tag normalisation, and lightweight stemming helpers.
 */
final class TextUtil
{
    private const DEFAULT_SLUG = 'note';

    /** @var array<string, true> */
    private static array $stopwords;

    /**
     * Create a URL-friendly slug from a string.
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return self::DEFAULT_SLUG . '-' . substr(bin2hex(random_bytes(6)), 0, 6);
        }

        $text = strtolower($text);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if (is_string($transliterated)) {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($text === '') {
            return self::DEFAULT_SLUG . '-' . substr(bin2hex(random_bytes(6)), 0, 6);
        }

        return $text;
    }

    /**
     * @param string|array<int, string> $tags
     */
    public static function normalizeTags(string|array $tags): string
    {
        $raw = [];
        if (is_string($tags)) {
            $raw = preg_split('/[,;]+/', $tags) ?: [];
        } else {
            $raw = $tags;
        }

        $normalized = [];
        foreach ($raw as $tag) {
            $tag = trim(mb_strtolower((string)$tag));
            if ($tag === '') {
                continue;
            }
            $normalized[$tag] = true;
        }

        return implode(',', array_keys($normalized));
    }

    /**
     * Tokenise text while removing stopwords and applying a light stemmer.
     *
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        $stopwords = self::stopwords();
        foreach ($parts as $word) {
            $stem = self::stem($word);
            if ($stem === '' || isset($stopwords[$stem])) {
                continue;
            }
            $tokens[] = $stem;
        }

        return $tokens;
    }

    private static function stem(string $word): string
    {
        $word = trim($word);
        if ($word === '') {
            return '';
        }

        $patterns = [
            '/(mente|azione|azioni|amente|isti|ista|ando|endo)$/u' => '',
            '/(ing|ed|es|er|ly|ness|ment)$/u' => '',
            '/(ie|ia|io|are|ere|ire)$/u' => '',
            '/[^\p{L}\p{N}]+/u' => '',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $word = preg_replace($pattern, $replacement, $word) ?? $word;
        }

        return $word;
    }

    /**
     * @return array<string, true>
     */
    private static function stopwords(): array
    {
        if (isset(self::$stopwords)) {
            return self::$stopwords;
        }

        $words = [
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'because',
            'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'could', 'did', 'do', 'does', 'doing', 'down', 'during',
            'each', 'few', 'for', 'from', 'further', 'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him',
            'himself', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'just', 'me', 'more', 'most', 'my', 'myself',
            'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over',
            'own', 'same', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their', 'theirs', 'them', 'themselves',
            'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'we',
            'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'you', 'your', 'yours', 'yourself',
            'yourselves',
            'ad', 'al', 'alla', 'alle', 'allo', 'agli', 'ai', 'anche', 'ancora', 'avere', 'aveva', 'avevano', 'ben', 'buono', 'che',
            'chi', 'cinque', 'comprare', 'con', 'cosa', 'cui', 'da', 'del', 'della', 'delle', 'dello', 'dei', 'degli', 'di', 'dopo',
            'dove', 'due', 'e', 'fare', 'fatto', 'fra', 'gli', 'ha', 'hai', 'hanno', 'ho', 'il', 'in', 'indietro', 'io', 'lavoro',
            'lei', 'lo', 'loro', 'lui', 'ma', 'meglio', 'molto', 'ne', 'nei', 'nella', 'nelle', 'nello', 'no', 'noi', 'nostro', 'nove',
            'o', 'oltre', 'ora', 'otto', 'per', 'perche', 'pero', 'piu', 'poco', 'primo', 'qua', 'quarto', 'quasi', 'quattro', 'quello',
            'questo', 'quindi', 'sei', 'se', 'sara', 'sarebbe', 'secondo', 'sei', 'si', 'sia', 'siamo', 'siete', 'solo', 'sono',
            'sopra', 'sotto', 'stato', 'stesso', 'su', 'sua', 'sue', 'suo', 'tu', 'tua', 'tue', 'tuo', 'tutti', 'un', 'una', 'uno',
            'va', 'via', 'voi',
        ];

        self::$stopwords = array_fill_keys($words, true);

        return self::$stopwords;
    }
}

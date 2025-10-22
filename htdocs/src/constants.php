<?php
declare(strict_types=1);

namespace Markly;

/**
 * Centralised application constants shared across the stack.
 */
final class Constants
{
    public const APP_NAME = 'Markly';
    public const APP_TAGLINE = 'Your local-first writing space.';
    public const APP_VERSION = '1.2.0';
    public const BASE_PATH = '/';
    public const THEME_STORAGE_KEY = 'mdpro_theme';
    public const STORAGE_PREFIX = 'mdpro_';
    public const CACHE_VERSION = 'v1.2.0';

    private function __construct()
    {
    }
}

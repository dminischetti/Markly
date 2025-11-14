<?php
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

spl_autoload_register(static function (string $class): void {
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    $appPrefix = 'Markly\\';
    if (str_starts_with($class, $appPrefix)) {
        $relative = substr($class, strlen($appPrefix));
        $path = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
        return;
    }

    $componentPrefix = 'dminischetti\\Markly\\';
    if (str_starts_with($class, $componentPrefix)) {
        $relative = substr($class, strlen($componentPrefix));
        $path = $baseDir . 'Markly' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

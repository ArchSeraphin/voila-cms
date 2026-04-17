<?php
declare(strict_types=1);

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    if (glob("$dir/*") === []) @rmdir($dir);
}

$base = dirname(__DIR__) . '/storage/cache';
foreach (glob($base . '/*') ?: [] as $sub) rrmdir($sub);
echo "Cache cleared.\n";

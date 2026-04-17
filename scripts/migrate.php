<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Core\{Config, DB, Migrator};

Config::load(__DIR__ . '/..');
$m = new Migrator(DB::conn(), __DIR__ . '/../database/migrations');
$applied = $m->run();
if (!$applied) {
    echo "Nothing to migrate.\n";
    exit(0);
}
echo "Applied:\n";
foreach ($applied as $v) echo "  - $v\n";

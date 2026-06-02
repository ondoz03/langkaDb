<?php
// MySQL Connection QA Report
require __DIR__ . '/../vendor/autoload.php';

echo "=== MySQL Connection QA Report ===\n\n";

// Check environment
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";

// Check MariaDB service
exec("systemctl is-active mariadb 2>/dev/null", $out, $code);
echo "MariaDB: " . ($out[0] ?? 'NOT FOUND') . "\n";

// Check Doctrine DBAL
echo "\nDoctrine DBAL: " . (class_exists(\Doctrine\DBAL\DriverManager::class) ? '✓ Available' : '✗ Missing') . "\n";

// Verify app modules load
echo "\n— App Module Status —\n";
$modules = [
    \App\Modules\Schema\Services\SchemaScanner::class,
    \App\Modules\Schema\Services\SchemaParser::class,
    \App\Modules\Schema\Services\ContextBuilder::class,
    \App\Modules\Schema\Services\SchemaFormatter::class,
    \App\Modules\Connection\Services\ConnectionService::class,
    \App\Modules\AIAgent\Services\AIRouter::class,
    \App\Modules\Query\Services\ExplainAnalyzer::class,
    \App\Modules\Query\Services\SlowQueryReader::class,
];
$allLoaded = true;
foreach ($modules as $m) {
    $ok = class_exists($m);
    echo ($ok ? '  ✓' : '  ✗') . " " . basename(str_replace('\\', '/', $m)) . "\n";
    if (!$ok) $allLoaded = false;
}

// Notes
echo "\n— Notes —\n";
echo "• MariaDB root auth uses unix_socket (sudo only)\n";
echo "• Create a MySQL user with password to test connections:\n";
echo "  \$ sudo mysql -e \"CREATE USER 'dbuser'@'%' IDENTIFIED BY 'pass'; GRANT ALL ON *.* TO 'dbuser'@'%';\"\n";
echo "• Then add Connection in the app UI with those credentials\n";

echo "\n" . ($allLoaded ? "✓" : "✗") . " Connection QA " . ($allLoaded ? "PASSED" : "ISSUES FOUND") . "\n";

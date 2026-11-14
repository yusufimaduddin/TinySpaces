<?php
/**
 * Migrate legacy uploads to the neutral .dat extension.
 *
 * Usage (from the project root):
 *   php scripts/migrate-uploads.php --dry-run
 *   php scripts/migrate-uploads.php
 *
 * The original filename and MIME type stay in the database. Only the physical
 * file name and the stored file_path are changed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/database.php';

$dryRun = in_array('--dry-run', $argv, true);
$root = dirname(__DIR__);
$db = Database::getInstance();

$files = $db->exec("SELECT id, space_id, original_name, file_path FROM files");
$renamed = 0;
$skipped = 0;
$missing = 0;
$failed = 0;

foreach ($files as $file) {
    $relative = ltrim((string) $file['file_path'], '/\\');
    $physical = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $extension = strtolower((string) pathinfo($physical, PATHINFO_EXTENSION));

    if ($extension === 'dat') {
        $skipped++;
        continue;
    }

    if (!is_file($physical)) {
        echo "MISSING  {$file['file_path']} (original: {$file['original_name']})\n";
        $missing++;
        continue;
    }

    $directory = dirname($physical);
    $baseName = pathinfo($physical, PATHINFO_FILENAME);
    $candidate = $baseName . '.dat';
    $counter = 1;

    while (file_exists($directory . DIRECTORY_SEPARATOR . $candidate)) {
        $candidate = $baseName . '-' . $counter . '.dat';
        $counter++;
    }

    $newPhysical = $directory . DIRECTORY_SEPARATOR . $candidate;
    $newRelative = '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($newPhysical, strlen($root) + 1));

    echo ($dryRun ? '[dry-run] ' : '') . "RENAME   {$file['file_path']} -> {$newRelative}\n";

    if ($dryRun) {
        $renamed++;
        continue;
    }

    if (!rename($physical, $newPhysical)) {
        echo "FAILED   {$file['file_path']}\n";
        $failed++;
        continue;
    }

    $db->exec(
        "UPDATE files SET file_path = ?, updated_at = ? WHERE id = ?",
        [$newRelative, date('Y-m-d H:i:s'), $file['id']]
    );
    $renamed++;
}

echo "\n";
echo "Renamed: {$renamed}\n";
echo "Already .dat: {$skipped}\n";
echo "Missing: {$missing}\n";
echo "Failed: {$failed}\n";

if ($dryRun) {
    echo "Dry run only. Re-run without --dry-run to apply.\n";
}

exit($failed > 0 ? 1 : 0);

<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find the demo by a fragment in processed filename
$pattern = '%test_bigbox%';
$row = DB::select('select id, file_path, processed_filename, file_size from uploaded_demos where processed_filename like ? limit 1', [$pattern]);
if (empty($row)) {
    echo json_encode(['found' => false]);
    exit(0);
}
$d = $row[0];
$full = storage_path('app/' . $d->file_path);
$exists = file_exists($full);
$size = $exists ? filesize($full) : null;
$perms = $exists ? substr(sprintf('%o', fileperms($full)), -4) : null;
$real = $exists ? realpath($full) : null;

echo json_encode([
    'found' => true,
    'id' => $d->id,
    'file_path' => $d->file_path,
    'processed_filename' => $d->processed_filename,
    'db_file_size' => $d->file_size,
    'exists' => $exists,
    'fs_size' => $size,
    'perms' => $perms,
    'realpath' => $real,
]);

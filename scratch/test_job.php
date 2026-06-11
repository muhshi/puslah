<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recordIds = \App\Models\SuratTugas::take(2)->pluck('id')->toArray();
if (empty($recordIds)) {
    echo "No SuratTugas records found to test.\n";
    exit;
}

\App\Jobs\GenerateBulkSuratTugasZip::dispatch($recordIds, 'pdf', 1);
echo "Job dispatched locally.\n";

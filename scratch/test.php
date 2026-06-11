<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$surveys = \App\Models\Survey::all(['id', 'name', 'start_date', 'end_date']);
foreach($surveys as $s) {
    echo $s->name . " | start: " . $s->start_date . " | end: " . $s->end_date . "\n";
}

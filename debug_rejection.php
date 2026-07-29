<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Submission;

header('Content-Type: application/json');

// PAKSA UPDATE id: 2 dengan pesan khusus
$sub = Submission::find(2);
if ($sub) {
    $sub->fill([
        'status' => 'rejected',
        'rejection_note' => 'TESTING DARI DEBUG SCRIPT: MOHON MAAF PENELITIAN ANDA KAMI TOLAK KARENA ALASAN TERTENTU.'
    ]);
    $sub->save();
}

// Ambil 5 submission terakhir apa pun statusnya
$recentSubs = Submission::select('id', 'type', 'status', 'rejection_note', 'updated_at')
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

echo json_encode([
    'message' => 'Berhasil memaksa update id 2 dengan rejection_note baru',
    'recent_submissions' => $recentSubs,
], JSON_PRETTY_PRINT);

<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../../src/Repos/VoicesRepo.php';

use App\Response;
use App\Session;
use Repos\UserFeatureAccessRepo;
use Repos\VoicesRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

$voicesRepo = new VoicesRepo();
$accessRepo = new UserFeatureAccessRepo();
$voices = [];

foreach ($voicesRepo->listPublished() as $voice) {
    if (!$accessRepo->hasVoiceAccess((int)$user['id'], $voice['slug'])) {
        continue;
    }
    $voices[] = [
        'id' => $voice['slug'],
        'slug' => $voice['slug'],
        'name' => $voice['name'],
        'description' => $voice['role'] ?: ($voice['description'] ?: 'Specialized voice'),
        'icon' => $voice['icon'] ?: 'iconoir-voice-square',
        'href' => $voice['slug'] === 'lex' ? '/voices/lex.php' : '/voices/view.php?voice=' . urlencode($voice['slug']),
    ];
}

Response::json([
    'success' => true,
    'voices' => $voices,
]);

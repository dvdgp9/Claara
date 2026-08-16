<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../../src/Repos/VoicesRepo.php';

use App\Response;
use App\Session;
use I18n\I18n;
use Repos\UserFeatureAccessRepo;
use Repos\VoicesRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', I18n::translate('auth.error.unauthorized'), 401);
}

$voicesRepo = new VoicesRepo();
$voiceResolver = new \Voices\VoiceAccessResolver();
$voices = [];

foreach ($voicesRepo->listPublished() as $voice) {
    if (!$voiceResolver->hasVoiceAccess((int)$user['id'], $voice)) {
        continue;
    }
    $voices[] = [
        'id' => $voice['slug'],
        'slug' => $voice['slug'],
        'name' => $voice['name'],
        'description' => $voice['role'] ?: ($voice['description'] ?: I18n::translate('voice.specialized')),
        'icon' => $voice['icon'] ?: 'iconoir-voice-square',
        'href' => $voice['slug'] === 'lex' ? '/voices/lex.php' : '/voices/view.php?voice=' . urlencode($voice['slug']),
    ];
}

Response::json([
    'success' => true,
    'voices' => $voices,
]);

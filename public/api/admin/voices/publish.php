<?php
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use App\Session;
use Repos\VoicesRepo;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

require_voice_editor();
Session::requireCsrf();

$input = read_json_body();
$slug = clean_voice_slug($input);
$repo = new VoicesRepo();
$voice = $repo->findBySlug($slug, true);
if (!$voice) {
    respond_voice_not_found();
}
if (trim((string)$voice['instructions']) === '') {
    Response::error('publish_blocked', 'Añade instrucciones antes de publicar la voz', 400);
}
$docsRepo = new ContextDocsRepo();
$processedDocs = array_filter(
    $docsRepo->listByVoice($slug),
    static fn(array $doc): bool => ($doc['rag_status'] ?? '') === 'processed'
);
if (count($processedDocs) === 0) {
    Response::error('publish_blocked', 'Procesa al menos un documento de conocimiento antes de publicar la voz', 400);
}

try {
    $repo->publish($slug);
    $repo->syncAvailableFeature($slug);
    Response::json([
        'success' => true,
        'voice' => $repo->findBySlug($slug, true),
    ]);
} catch (\Throwable $e) {
    Response::serverError('voice_publish_failed', $e, 'No se pudo publicar la voz');
}

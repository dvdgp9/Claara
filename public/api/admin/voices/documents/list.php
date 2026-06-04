<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

[, $voice] = require_voice_document_context();
$repo = new ContextDocsRepo();

Response::json([
    'success' => true,
    'voice' => $voice,
    'documents' => $repo->listByVoice($voice['slug']),
    'stats' => [
        'total_documents' => count($repo->listByVoice($voice['slug'])),
    ],
]);

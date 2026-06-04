<?php
use App\Response;
use Auth\AuthService;
use Auth\VoiceEditorGuard;
use Repos\VoicesRepo;

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/ContextDocsRepo.php';
require_once __DIR__ . '/../../../../../src/Rag/RagProcessor.php';
require_once __DIR__ . '/../../../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../../../src/Rag/EmbeddingService.php';

function require_voice_document_context(): array
{
    $user = AuthService::requireAuth();
    VoiceEditorGuard::requireCanEdit($user);

    $slug = strtolower(trim((string)($_REQUEST['slug'] ?? $_GET['slug'] ?? $_POST['slug'] ?? '')));
    if ($slug === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        Response::error('validation_error', 'slug inválido', 400);
    }

    $voices = new VoicesRepo();
    $voice = $voices->findBySlug($slug, true);
    if (!$voice) {
        Response::error('voice_not_found', 'Voz no encontrada', 404);
    }

    return [$user, $voice];
}

function voice_documents_path(string $slug): string
{
    $path = \Repos\ContextDocsRepo::getVoicePath($slug);
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        Response::error('target_path_error', 'No se pudo crear la carpeta de conocimiento', 500);
    }
    if (!is_writable($path)) {
        Response::error('target_not_writable', 'La carpeta de conocimiento no es escribible', 500);
    }
    return $path;
}

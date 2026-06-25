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
        Response::error('validation_error', 'invalid slug', 400);
    }

    $voices = new VoicesRepo();
    $voice = $voices->findBySlug($slug, true);
    if (!$voice) {
        Response::error('voice_not_found', 'Voice not found', 404);
    }

    return [$user, $voice];
}

function voice_documents_path(string $slug): string
{
    $path = \Repos\ContextDocsRepo::getVoicePath($slug);
    $parent = dirname($path);

    if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
        $phpUser = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user()) : get_current_user();
        Response::error('target_path_error', "Could not create the voice folder ({$parent}) as user {$phpUser}", 500);
    }

    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        clearstatcache(true, $parent);
        $phpUser = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user()) : get_current_user();
        $parentWritable = is_writable($parent) ? 'yes' : 'no';
        Response::error('target_path_error', "Could not create the knowledge folder ({$path}). PHP user: {$phpUser}. Parent writable: {$parentWritable}", 500);
    }
    clearstatcache(true, $path);
    if (!is_writable($path)) {
        $phpUser = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user()) : get_current_user();
        Response::error('target_not_writable', "The knowledge folder is not writable ({$path}). PHP user: {$phpUser}", 500);
    }
    return $path;
}

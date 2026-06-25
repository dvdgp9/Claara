<?php
use App\Env;
use App\Session;

// Autoloader de Composer (para PhpSpreadsheet y otras dependencias)
$vendorAutoload = dirname(dirname(__DIR__)) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/SecurityHeaders.php';

// Enviar security headers en todas las respuestas
\App\SecurityHeaders::send();

// Gestures
require_once dirname(__DIR__) . '/Gestures/GestureExecutionsRepo.php';

// Jobs (Background processing)
require_once dirname(__DIR__) . '/Jobs/BackgroundJobsRepo.php';

// Repos
require_once dirname(__DIR__) . '/Repos/UsageLogRepo.php';
require_once dirname(__DIR__) . '/Repos/UserFeatureAccessRepo.php';
require_once dirname(__DIR__) . '/Repos/VoicesRepo.php';
require_once dirname(__DIR__) . '/Repos/ContextDocsRepo.php';
require_once dirname(__DIR__) . '/Repos/OrganizationResponsibilityRepo.php';
require_once dirname(__DIR__) . '/Repos/ConversationAccessRepo.php';
require_once dirname(__DIR__) . '/Repos/VoiceFoldersRepo.php';
require_once dirname(__DIR__) . '/Repos/VoiceProfilesRepo.php';

// Claara internal capabilities
require_once dirname(__DIR__) . '/Claara/CapabilityCatalogService.php';

// Chat (LLM)
require_once dirname(__DIR__) . '/Chat/OpenRouterClient.php';

// RAG
require_once dirname(__DIR__) . '/Rag/QdrantClient.php';
require_once dirname(__DIR__) . '/Rag/EmbeddingService.php';
require_once dirname(__DIR__) . '/Rag/LexRetriever.php';

// Voices
require_once dirname(__DIR__) . '/Voices/VoiceAccessResolver.php';
require_once dirname(__DIR__) . '/Voices/VoiceContextBuilder.php';
require_once dirname(__DIR__) . '/Voices/VoiceQueryService.php';

// Audio (Podcast)
require_once dirname(__DIR__) . '/Audio/GeminiTtsClient.php';
require_once dirname(__DIR__) . '/Audio/ContentExtractor.php';
require_once dirname(__DIR__) . '/Audio/PodcastScriptGenerator.php';

// Content (Repurposer, Course Generator)
require_once dirname(__DIR__) . '/Content/ContentRepurposer.php';
require_once dirname(__DIR__) . '/Content/CourseGenerator.php';

// SOP Generator
require_once dirname(__DIR__) . '/Sop/AudioTranscriber.php';
require_once dirname(__DIR__) . '/Sop/ImageDescriber.php';
require_once dirname(__DIR__) . '/Sop/SopGenerator.php';

// Lead Finder
require_once dirname(__DIR__) . '/LeadFinder/LeadSearchProvider.php';
require_once dirname(__DIR__) . '/LeadFinder/MockLeadSearchProvider.php';
require_once dirname(__DIR__) . '/LeadFinder/ApifyLeadSearchProvider.php';
require_once dirname(__DIR__) . '/LeadFinder/LeadFinderRepo.php';

// External connectors
require_once dirname(__DIR__) . '/Connectors/ConnectorProviderInterface.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorItemImporterInterface.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorTokenCrypto.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorProvidersRepo.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorAccountsRepo.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorTokensRepo.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorItemsRepo.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorImportsRepo.php';

// Utils
require_once dirname(__DIR__) . '/Utils/DocumentGenerator.php';

// Cargar .env desde la raíz del proyecto
$root = dirname(dirname(__DIR__));
Env::load($root . '/.env');

// Iniciar sesión y CSRF
Session::start();

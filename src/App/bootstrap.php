<?php
use App\Env;
use App\Session;
use Instances\InstanceConfigurationException;
use Instances\InstanceResolver;
use Instances\InstanceUnavailableException;
use Instances\UnknownInstanceException;

// Autoloader de Composer (para PhpSpreadsheet y otras dependencias)
$vendorAutoload = dirname(dirname(__DIR__)) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Response.php';
require_once dirname(__DIR__) . '/Instances/InstanceException.php';
require_once dirname(__DIR__) . '/Modules/ModuleConfigurationException.php';
require_once dirname(__DIR__) . '/Modules/ModuleDefinition.php';
require_once dirname(__DIR__) . '/Modules/ModuleRegistry.php';
require_once dirname(__DIR__) . '/Modules/ModuleEntitlementService.php';
require_once dirname(__DIR__) . '/Modules/ModulePresentationState.php';
require_once dirname(__DIR__) . '/Modules/ModuleStateResolver.php';
require_once dirname(__DIR__) . '/Modules/ModuleCatalogPresenter.php';
require_once dirname(__DIR__) . '/Modules/CoreRouteRegistry.php';
require_once dirname(__DIR__) . '/Modules/CoreModuleGuard.php';
require_once dirname(__DIR__) . '/Instances/InstanceManifest.php';
require_once dirname(__DIR__) . '/Instances/InstanceResources.php';
require_once dirname(__DIR__) . '/Instances/InstanceContext.php';
require_once dirname(__DIR__) . '/Instances/InstanceResolver.php';
require_once dirname(__DIR__) . '/I18n/LocaleResolver.php';
require_once dirname(__DIR__) . '/I18n/Translator.php';
require_once dirname(__DIR__) . '/I18n/I18n.php';
require_once __DIR__ . '/CookieScope.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/SecurityHeaders.php';

// Gestures
require_once dirname(__DIR__) . '/Gestures/GestureExecutionsRepo.php';

// Jobs (Background processing)
require_once dirname(__DIR__) . '/Jobs/BackgroundJobsRepo.php';

// Repos
require_once dirname(__DIR__) . '/Repos/UsageLogRepo.php';
require_once dirname(__DIR__) . '/Repos/UserFeatureAccessRepo.php';
require_once dirname(__DIR__) . '/Gestures/GestureAccessGuard.php';
require_once dirname(__DIR__) . '/Repos/VoicesRepo.php';
require_once dirname(__DIR__) . '/Repos/ContextDocsRepo.php';
require_once dirname(__DIR__) . '/Repos/OrganizationResponsibilityRepo.php';
require_once dirname(__DIR__) . '/Repos/ConversationAccessRepo.php';
require_once dirname(__DIR__) . '/Repos/VoiceFoldersRepo.php';
require_once dirname(__DIR__) . '/Repos/VoiceProfilesRepo.php';
require_once dirname(__DIR__) . '/Repos/AccessLevelsRepo.php';
require_once dirname(__DIR__) . '/Repos/VoiceAccessListRepo.php';

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
require_once dirname(__DIR__) . '/Connectors/ConnectorOAuthException.php';
require_once dirname(__DIR__) . '/Connectors/GoogleOAuthException.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorImportException.php';
require_once dirname(__DIR__) . '/Connectors/ConnectorTokenService.php';
require_once dirname(__DIR__) . '/Connectors/GoogleDriveProvider.php';
require_once dirname(__DIR__) . '/Connectors/GoogleTokenService.php';
require_once dirname(__DIR__) . '/Connectors/GoogleDriveImporter.php';
require_once dirname(__DIR__) . '/Connectors/MicrosoftOneDriveProvider.php';
require_once dirname(__DIR__) . '/Connectors/OneDriveImporter.php';

// Utils
require_once dirname(__DIR__) . '/Utils/DocumentGenerator.php';

// Load deployment secrets before resolving the non-secret instance manifest.
$root = dirname(dirname(__DIR__));
Env::load($root . '/.env');

// Security headers are present even when instance resolution fails closed.
\App\SecurityHeaders::send();

try {
    InstanceResolver::bootFromEnvironment($root);
} catch (UnknownInstanceException $error) {
    \App\Response::error('instance_not_found', 'This domain is not assigned to an active Claara instance', 421);
} catch (InstanceUnavailableException $error) {
    \App\Response::error('instance_unavailable', 'This Claara instance is temporarily unavailable', 503);
} catch (InstanceConfigurationException $error) {
    error_log('Instance configuration error: ' . $error->getMessage());
    \App\Response::error('instance_configuration_error', 'Claara instance configuration is unavailable', 500);
}

// Iniciar sesión y CSRF
Session::start();

// Resolve one deterministic UI locale: user preference, then instance default,
// then the English safety fallback enforced by LocaleResolver.
\I18n\I18n::boot(
    \Instances\InstanceContext::current(),
    $root . '/resources/i18n',
    $_SESSION['user']['locale'] ?? null
);

// Enforce the active instance's module contract at the shared HTTP boundary.
\Modules\CoreModuleGuard::enforceCurrentRequest();

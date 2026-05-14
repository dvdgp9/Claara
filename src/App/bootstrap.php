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

// Chat (LLM)
require_once dirname(__DIR__) . '/Chat/OpenRouterClient.php';

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

// Utils
require_once dirname(__DIR__) . '/Utils/DocumentGenerator.php';

// Cargar .env desde la raíz del proyecto
$root = dirname(dirname(__DIR__));
Env::load($root . '/.env');

// Iniciar sesión y CSRF
Session::start();

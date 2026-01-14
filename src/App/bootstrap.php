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

// Content (Repurposer)
require_once dirname(__DIR__) . '/Content/ContentRepurposer.php';

// SOP Generator
require_once dirname(__DIR__) . '/Sop/AudioTranscriber.php';
require_once dirname(__DIR__) . '/Sop/ImageDescriber.php';
require_once dirname(__DIR__) . '/Sop/SopGenerator.php';

// Utils
require_once dirname(__DIR__) . '/Utils/DocumentGenerator.php';

// Cargar .env desde la raíz del proyecto
$root = dirname(dirname(__DIR__));
Env::load($root . '/.env');

// Iniciar sesión y CSRF
Session::start();

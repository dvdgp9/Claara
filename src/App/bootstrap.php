<?php
use App\Env;
use App\Session;

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

// Chat (LLM)
require_once dirname(__DIR__) . '/Chat/OpenRouterClient.php';

// Audio (Podcast)
require_once dirname(__DIR__) . '/Audio/GeminiTtsClient.php';
require_once dirname(__DIR__) . '/Audio/ContentExtractor.php';
require_once dirname(__DIR__) . '/Audio/PodcastScriptGenerator.php';

// Cargar .env desde la raíz del proyecto
$root = dirname(dirname(__DIR__));
Env::load($root . '/.env');

// Iniciar sesión y CSRF
Session::start();

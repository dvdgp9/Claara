<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';
$userId = (int)$user['id'];
$accessRepo = new UserFeatureAccessRepo();

$headerBackUrl = '/';
$headerBackText = 'Home';
$headerTitle = 'Gestures';
$headerSubtitle = 'Automated workflows';
$headerIcon = 'iconoir-magic-wand';
$headerIconColor = 'from-cyan-500 to-teal-600';
?><!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-mesh text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>
    
    <!-- Main content -->
    <main class="flex-1 flex flex-col overflow-hidden">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <!-- Content area -->
      <div class="flex-1 overflow-auto p-4 lg:p-6 pb-20 lg:pb-6">
        <div class="max-w-6xl mx-auto">
          
          <!-- Hero section -->
          <div class="text-center mb-6 lg:mb-10">
            <div class="w-14 h-14 lg:w-20 lg:h-20 rounded-2xl lg:rounded-3xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center mx-auto mb-4 lg:mb-6 shadow-xl animate-float">
              <i class="iconoir-magic-wand text-2xl lg:text-4xl text-white"></i>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-slate-900 mb-2 lg:mb-3">Gestures</h1>
            <p class="text-sm lg:text-base text-slate-600 max-w-2xl mx-auto px-4 lg:px-0">
              Guided workflows for common tasks. Each gesture gives you the right structure to produce high-quality work faster.
            </p>
          </div>

          <!-- Gestos grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-8">
            
            <?php if ($accessRepo->hasGestureAccess($userId, 'write-article')): ?>
            <!-- Gesto: Escribir contenido -->
            <a href="/gestos/escribir-articulo.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-page-edit text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Write content</h3>
                  <p class="text-sm text-slate-500">Articles and blogs</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Active</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Generate informative articles, blog posts, or press notes. Set the tone, length, and style you need.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-cyan-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'social-media')): ?>
            <!-- Gesto: Redes Sociales -->
            <a href="/gestos/redes-sociales.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-send-diagonal text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Social media</h3>
                  <p class="text-sm text-slate-500">Posts for social channels</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Active</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Build posts with guided editorial decisions. Choose intent, channel, and style, then generate variants in one step.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-violet-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'podcast-from-article')): ?>
            <!-- Gesto: Podcast desde artículo -->
            <a href="/gestos/podcast-articulo.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-orange-500 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-podcast text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Generate podcast</h3>
                  <p class="text-sm text-slate-500">With Iris and Bruno</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Turn any web article or document into a podcast with two hosts.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-rose-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'image-editor')): ?>
            <!-- Gesto: Editor de imágenes -->
            <a href="/gestos/editor-imagenes.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-media-image text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Image editor <span class="text-base">🍌</span></h3>
                  <p class="text-sm text-slate-500">With Nanobanana</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Generate brand-ready images with AI. Control style, lighting, composition, and color palette.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-amber-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'content-repurposer')): ?>
            <!-- Gesto: Transformador de contenido -->
            <a href="/gestos/transformador-contenido.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-refresh-double text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Content transformer</h3>
                  <p class="text-sm text-slate-500">Adapt content</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Convert any source content into social posts, blogs, landing pages, newsletters, or FAQs.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-indigo-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'sop-generator')): ?>
            <!-- Gesto: Generador de SOPs -->
            <a href="/gestos/sop-generator.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-clipboard-check text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Process generator</h3>
                  <p class="text-sm text-slate-500">Operating procedures</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Turn text, audio, or images into structured procedures with flowcharts and downloadable documents.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-emerald-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'audio-transcriber')): ?>
            <!-- Gesto: Transcriptor de audio -->
            <a href="/gestos/transcriptor-audio.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-microphone text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Audio transcriber</h3>
                  <p class="text-sm text-slate-500">Audio to text</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Active</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Convert voice recordings, interviews, or meetings into text. Supports MP3, WAV, M4A, and more.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-purple-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'course-creator')): ?>
            <!-- Gesto: Creador de cursos -->
            <a href="/gestos/creador-cursos.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-graduation-cap text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Course creator</h3>
                  <p class="text-sm text-slate-500">Training material</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Generate full course material from a PDF: syllabus, handouts, quizzes, flashcards, podcasts, and exams.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-emerald-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <?php if ($accessRepo->hasGestureAccess($userId, 'project-admin')): ?>
            <!-- Gesto: Análisis Eco Proy. -->
            <a href="/gestos/admin-proyectos.php" class="glass-strong rounded-3xl p-6 border border-slate-200/50 card-hover block">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg">
                  <i class="iconoir-folder-settings text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Project analysis</h3>
                  <p class="text-sm text-slate-500">Tender document review</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">New</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Upload tender documents and extract non-staff costs, work hours, and other key information automatically.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-emerald-600 font-medium">
                  <span>Use gesture</span>
                  <i class="iconoir-arrow-right"></i>
                </div>
              </div>
            </a>
            <?php endif; ?>

            <!-- Gesto: Analizar documento (próximamente) -->
            <div class="glass-strong rounded-3xl p-6 border border-slate-200/50 opacity-60">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-200 flex items-center justify-center text-slate-400">
                  <i class="iconoir-search-window text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-500 mb-1">Analyze document</h3>
                  <p class="text-sm text-slate-400">Information extraction</p>
                </div>
                <span class="px-2 py-1 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
              </div>
              
              <p class="text-sm text-slate-400 mb-4">
                Upload a document and get summaries, key points, or answers to specific questions about the content.
              </p>
              
              <div class="flex items-center justify-between text-xs text-slate-300 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-1">
                  <i class="iconoir-clock"></i>
                  <span>Coming soon</span>
                </div>
              </div>
            </div>

            <!-- Gesto: Generar email (próximamente) -->
            <div class="glass-strong rounded-3xl p-6 border border-slate-200/50 opacity-60">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-200 flex items-center justify-center text-slate-400">
                  <i class="iconoir-mail text-2xl"></i>
                </div>
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-slate-500 mb-1">Generate email</h3>
                  <p class="text-sm text-slate-400">Professional communication</p>
                </div>
                <span class="px-2 py-1 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
              </div>
              
              <p class="text-sm text-slate-400 mb-4">
                Create professional emails from an idea or context. Adjust the tone for internal or external communication.
              </p>
              
              <div class="flex items-center justify-between text-xs text-slate-300 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-1">
                  <i class="iconoir-clock"></i>
                  <span>Coming soon</span>
                </div>
              </div>
            </div>

          </div>

          <!-- Info section -->
          <div class="glass rounded-3xl p-6 border border-slate-200/50">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-2xl bg-cyan-100 flex items-center justify-center flex-shrink-0">
                <i class="iconoir-info-circle text-2xl text-cyan-600"></i>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800 mb-2">What are gestures?</h3>
                <p class="text-sm text-slate-600 mb-3">
                  Gestures are guided workflows that help you complete complex tasks step by step. Unlike open chat, each gesture is optimized for a specific goal and asks only for the information it needs.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Step-by-step guidance</div>
                      <div class="text-xs text-slate-500">No extra friction</div>
                    </div>
                  </div>
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Consistent results</div>
                      <div class="text-xs text-slate-500">Reliable quality</div>
                    </div>
                  </div>
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Saved history</div>
                      <div class="text-xs text-slate-500">Reuse and edit</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  </div>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>

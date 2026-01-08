<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';

use App\Session;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = 'gestures';

// Configuración del header unificado
$headerBackUrl = '/';
$headerBackText = 'Inicio';
$headerTitle = 'Gestos';
$headerSubtitle = 'Flujos automatizados';
$headerIcon = 'iconoir-magic-wand';
$headerIconColor = 'from-cyan-500 to-teal-600';
?><!DOCTYPE html>
<html lang="es">
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
            <h1 class="text-2xl lg:text-3xl font-bold text-slate-900 mb-2 lg:mb-3">Gestos</h1>
            <p class="text-sm lg:text-base text-slate-600 max-w-2xl mx-auto px-4 lg:px-0">
              Flujos de trabajo automatizados para tareas comunes. Cada gesto te guía paso a paso para generar contenido de calidad.
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
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Escribir contenido</h3>
                  <p class="text-sm text-slate-500">Artículos y blogs</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Activo</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Genera artículos informativos, posts de blog o notas de prensa. Configura el tono, extensión y estilo que necesites.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-cyan-600 font-medium">
                  <span>Usar gesto</span>
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
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Redes Sociales</h3>
                  <p class="text-sm text-slate-500">Publicaciones para RRSS</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Activo</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Construye publicaciones con decisiones editoriales guiadas. Selecciona intención, canal y estilo. Genera variantes con un clic.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-violet-600 font-medium">
                  <span>Usar gesto</span>
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
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Generar podcast</h3>
                  <p class="text-sm text-slate-500">Con Iris y Bruno</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Nuevo</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Convierte cualquier artículo web o documento en un podcast con dos presentadores.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-rose-600 font-medium">
                  <span>Usar gesto</span>
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
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Editor de imágenes <span class="text-base">🍌</span></h3>
                  <p class="text-sm text-slate-500">Con Nanobanana</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Nuevo</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Genera imágenes corporativas con IA. Controla estilo, iluminación, composición y paleta de colores.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-amber-600 font-medium">
                  <span>Usar gesto</span>
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
                  <h3 class="text-lg font-bold text-slate-900 mb-1">Transformador</h3>
                  <p class="text-sm text-slate-500">Adapta contenido</p>
                </div>
                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full font-medium">Nuevo</span>
              </div>
              
              <p class="text-sm text-slate-600 mb-4">
                Convierte cualquier contenido en posts para redes, blogs, landing pages, newsletters o FAQs.
              </p>
              
              <div class="flex items-center justify-end text-xs text-slate-400 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-2 text-indigo-600 font-medium">
                  <span>Usar gesto</span>
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
                  <h3 class="text-lg font-bold text-slate-500 mb-1">Analizar documento</h3>
                  <p class="text-sm text-slate-400">Extracción de información</p>
                </div>
                <span class="px-2 py-1 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
              </div>
              
              <p class="text-sm text-slate-400 mb-4">
                Sube un documento y obtén resúmenes, puntos clave o respuestas a preguntas específicas sobre el contenido.
              </p>
              
              <div class="flex items-center justify-between text-xs text-slate-300 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-1">
                  <i class="iconoir-clock"></i>
                  <span>Próximamente</span>
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
                  <h3 class="text-lg font-bold text-slate-500 mb-1">Generar email</h3>
                  <p class="text-sm text-slate-400">Comunicación profesional</p>
                </div>
                <span class="px-2 py-1 text-xs bg-slate-100 text-slate-400 rounded-full">Soon</span>
              </div>
              
              <p class="text-sm text-slate-400 mb-4">
                Crea emails profesionales a partir de una idea o contexto. Ajusta el tono para comunicación interna o externa.
              </p>
              
              <div class="flex items-center justify-between text-xs text-slate-300 pt-4 border-t border-slate-200/50">
                <div class="flex items-center gap-1">
                  <i class="iconoir-clock"></i>
                  <span>Próximamente</span>
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
                <h3 class="font-semibold text-slate-800 mb-2">¿Qué son los gestos?</h3>
                <p class="text-sm text-slate-600 mb-3">
                  Los gestos son flujos de trabajo guiados que te ayudan a completar tareas complejas paso a paso. A diferencia del chat libre, cada gesto está optimizado para un objetivo específico y te pide solo la información necesaria.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Guiado paso a paso</div>
                      <div class="text-xs text-slate-500">Sin complicaciones</div>
                    </div>
                  </div>
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Resultados consistentes</div>
                      <div class="text-xs text-slate-500">Calidad garantizada</div>
                    </div>
                  </div>
                  <div class="flex items-start gap-2">
                    <i class="iconoir-check text-cyan-600 mt-0.5"></i>
                    <div>
                      <div class="font-medium text-sm text-slate-700">Historial guardado</div>
                      <div class="text-xs text-slate-500">Reutiliza y edita</div>
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

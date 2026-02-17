<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/App/Session.php';

use App\Session;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Verificar si es superadmin
$isSuperadmin = in_array('admin', $user['roles'] ?? [], true);
if (!$isSuperadmin) {
    header('Location: /');
    exit;
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gestor de Contexto — Ebonia</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <style>
    .gradient-brand { background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%); }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .tab-active { border-bottom: 2px solid #23AAC5; color: #23AAC5; }
    .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-processing { background: #dbeafe; color: #1e40af; }
    .status-error { background: #fee2e2; color: #991b1b; }
    .status-processed { background: #d1fae5; color: #065f46; }
    .rag-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = '';
    $pageTitle = 'Gestor de Contexto';
    include __DIR__ . '/../includes/left-tabs.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-7xl mx-auto p-4 lg:p-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 lg:mb-8 mt-4 lg:mt-6">
            <div>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Gestor de Contexto</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">Administrar documentos de contexto y RAG</p>
            </div>
          </div>

          <!-- Tabs -->
          <div class="bg-white rounded-t-2xl border border-b-0 border-slate-200">
            <div class="flex border-b border-slate-200">
              <button data-target="lex" class="tab-btn flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors border-b-2 border-transparent tab-active">
                <i class="iconoir-scale mr-2"></i>Lex (Legal)
              </button>
              <button data-target="eboniato" class="tab-btn flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors border-b-2 border-transparent">
                <i class="iconoir-chat-bubble-question mr-2"></i>Eboniato (FAQ)
              </button>
              <button data-target="ebonia" class="tab-btn flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors border-b-2 border-transparent">
                <i class="iconoir-message-text mr-2"></i>Ebonia (Chat)
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="bg-white rounded-b-2xl shadow-sm border border-t-0 border-slate-200 overflow-hidden">
            <!-- Stats Bar -->
            <div id="stats-bar" class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center gap-6">
              <div class="flex items-center gap-2">
                <i class="iconoir-folder text-slate-400"></i>
                <span class="text-sm text-slate-600">Documentos: <strong id="stat-docs" class="text-slate-800">0</strong></span>
              </div>
              <div class="flex items-center gap-2">
                <i class="iconoir-data-transfer-both text-slate-400"></i>
                <span class="text-sm text-slate-600">Tamaño: <strong id="stat-size" class="text-slate-800">0 KB</strong></span>
              </div>
              <div id="stat-chunks-container" class="flex items-center gap-2">
                <i class="iconoir-cube text-slate-400"></i>
                <span class="text-sm text-slate-600">Chunks RAG: <strong id="stat-chunks" class="text-slate-800">0</strong></span>
              </div>
              <div class="flex-1"></div>
              <button id="create-btn" class="px-4 py-2 border border-[#23AAC5] text-[#23AAC5] rounded-lg font-medium hover:bg-[#23AAC5]/10 transition-all flex items-center gap-2 text-sm">
                <i class="iconoir-edit-pencil"></i>
                <span>Crear documento</span>
              </button>
              <button id="upload-btn" class="px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 hover:shadow-lg transition-all flex items-center gap-2 shadow-md text-sm">
                <i class="iconoir-upload"></i>
                <span>Subir archivo</span>
              </button>
            </div>

            <!-- Loading -->
            <div id="docs-loading" class="text-center py-12">
              <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#23AAC5] border-r-transparent"></div>
              <p class="text-sm text-slate-500 mt-3">Cargando documentos...</p>
            </div>

            <!-- Documents List -->
            <div id="docs-container" class="hidden">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Documento</th>
                      <th id="col-type" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden">Tipo</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tamaño</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
                      <th id="col-rag" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">RAG</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fecha</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="docs-list" class="divide-y divide-slate-200">
                  </tbody>
                </table>
              </div>
              <div id="no-docs" class="hidden text-center py-12">
                <i class="iconoir-folder-warning text-4xl text-slate-300"></i>
                <p class="text-slate-500 mt-3">No hay documentos en este target</p>
                <p class="text-slate-400 text-sm mt-1">Sube el primer documento para comenzar</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Upload -->
  <div id="upload-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-slate-800">Subir documento</h3>
        <button id="close-upload-modal" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="upload-form" enctype="multipart/form-data">
        <div class="space-y-4">
          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Target</label>
            <select id="upload-target" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-700" disabled>
              <option value="lex">Lex (Legal)</option>
              <option value="eboniato">Eboniato (FAQ)</option>
              <option value="ebonia">Ebonia (Chat)</option>
            </select>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Archivo</label>
            <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:border-[#23AAC5] transition-colors cursor-pointer" id="drop-zone">
              <input type="file" id="file-input" class="hidden" accept=".pdf,.txt,.md">
              <i class="iconoir-upload text-3xl text-slate-400 mb-2"></i>
              <p class="text-sm text-slate-600">Arrastra un archivo aquí o <span class="text-[#23AAC5] font-medium">haz clic para seleccionar</span></p>
              <p id="allowed-formats" class="text-xs text-slate-400 mt-1">Formatos permitidos: .pdf, .txt, .md</p>
            </div>
            <p id="selected-file" class="hidden text-sm text-[#23AAC5] mt-2 flex items-center gap-2">
              <i class="iconoir-check-circle"></i>
              <span id="selected-file-name"></span>
            </p>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Descripción (opcional)</label>
            <textarea id="upload-description" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors resize-none" placeholder="Descripción del documento..."></textarea>
          </div>

          <div id="upload-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>

          <div class="flex gap-3 pt-2">
            <button type="submit" id="upload-submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md disabled:opacity-50">
              Subir documento
            </button>
            <button type="button" id="cancel-upload" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
              Cancelar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Create Document -->
  <div id="create-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 h-[85vh] flex flex-col">
      <div class="flex items-center justify-between mb-4 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
            <i class="iconoir-page-plus text-xl"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-800">Crear documento</h3>
            <p class="text-xs text-slate-500">Nuevo documento de contexto para <span id="create-target-name" class="font-medium">Lex</span></p>
          </div>
        </div>
        <button id="close-create-modal" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-4 flex-shrink-0">
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Nombre del archivo</label>
          <div class="flex">
            <input type="text" id="create-filename" class="flex-1 px-3 py-2 border border-slate-200 rounded-l-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" placeholder="mi_documento">
            <span id="create-extension" class="px-3 py-2 bg-slate-100 border border-l-0 border-slate-200 rounded-r-lg text-slate-600 text-sm">.md</span>
          </div>
        </div>
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Descripción (opcional)</label>
          <input type="text" id="create-description" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" placeholder="Breve descripción...">
        </div>
      </div>

      <div class="flex-1 overflow-hidden flex flex-col min-h-0">
        <label class="text-sm font-medium text-slate-700 block mb-2">Contenido</label>
        <textarea id="create-content" class="flex-1 w-full px-4 py-3 bg-white border border-slate-200 rounded-lg font-mono text-sm focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors resize-none" placeholder="Pega o escribe el contenido del documento aquí..." spellcheck="false"></textarea>
      </div>

      <div class="flex items-center gap-4 pt-3 text-xs text-slate-500 flex-shrink-0">
        <span id="create-char-count">0 caracteres</span>
        <span>•</span>
        <span id="create-line-count">0 líneas</span>
      </div>

      <div id="create-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-3 flex-shrink-0"></div>

      <div class="flex gap-3 pt-4 border-t border-slate-100 mt-4 flex-shrink-0">
        <button type="button" id="create-submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md disabled:opacity-50">
          <i class="iconoir-plus mr-1"></i>
          Crear documento
        </button>
        <button type="button" id="cancel-create" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
          Cancelar
        </button>
      </div>
    </div>
  </div>

  <!-- Modal View/Edit -->
  <div id="edit-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full p-6 h-[90vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
            <i id="edit-doc-icon" class="iconoir-page text-xl"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-slate-800" id="edit-modal-title">Ver documento</h3>
            <p class="text-xs text-slate-500" id="edit-doc-name">documento.md</p>
          </div>
        </div>
        <button id="close-edit-modal" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <!-- Info bar -->
      <div class="flex items-center gap-4 px-4 py-3 bg-slate-50 rounded-lg mb-4 text-sm flex-shrink-0">
        <div class="flex items-center gap-2">
          <i class="iconoir-folder text-slate-400"></i>
          <span class="text-slate-600">Target: <strong class="text-slate-800" id="edit-doc-target">lex</strong></span>
        </div>
        <div class="h-4 w-px bg-slate-300"></div>
        <div class="flex items-center gap-2">
          <i class="iconoir-file-not-found text-slate-400"></i>
          <span class="text-slate-600">Tamaño: <strong class="text-slate-800" id="edit-doc-size">0 KB</strong></span>
        </div>
        <div class="h-4 w-px bg-slate-300"></div>
        <div class="flex items-center gap-2">
          <i class="iconoir-calendar text-slate-400"></i>
          <span class="text-slate-600">Creado: <strong class="text-slate-800" id="edit-doc-date">-</strong></span>
        </div>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2 text-xs">
          <span class="text-slate-500" id="edit-char-count">0 caracteres</span>
          <span class="text-slate-300">•</span>
          <span class="text-slate-500" id="edit-line-count">0 líneas</span>
        </div>
      </div>

      <!-- Content editor -->
      <div class="flex-1 overflow-hidden flex flex-col min-h-0 bg-slate-50 rounded-lg border border-slate-200">
        <textarea id="edit-content" class="flex-1 w-full px-4 py-3 bg-white font-mono text-sm focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors resize-none border-0" placeholder="Contenido del documento..." spellcheck="false"></textarea>
      </div>

      <!-- Notices -->
      <div id="edit-readonly-notice" class="hidden mt-3 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 flex items-center gap-2 flex-shrink-0">
        <i class="iconoir-info-circle"></i>
        <span>Los archivos PDF no se pueden editar inline. Descarga el archivo para modificarlo.</span>
      </div>

      <div id="edit-rag-notice" class="hidden mt-3 text-sm text-blue-600 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 flex items-center gap-2 flex-shrink-0">
        <i class="iconoir-info-circle"></i>
        <span>Al guardar cambios en un documento de Lex, deberás <strong>reprocesar el RAG</strong> para actualizar los vectores.</span>
      </div>

      <div id="edit-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-3 flex-shrink-0"></div>

      <!-- Footer actions -->
      <div class="flex gap-3 pt-4 border-t border-slate-100 mt-4 flex-shrink-0">
        <button type="button" id="save-content-btn" class="flex-1 px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md disabled:opacity-50">
          <i class="iconoir-floppy-disk mr-1"></i>
          Guardar cambios
        </button>
        <button type="button" id="close-edit-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
          Cerrar
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Delete Confirm -->
  <div id="delete-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="h-12 w-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
          <i class="iconoir-warning-triangle text-red-600 text-2xl"></i>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-slate-800">Eliminar documento</h3>
          <p class="text-sm text-slate-600 mt-0.5">Esta acción no se puede deshacer</p>
        </div>
      </div>

      <p class="text-slate-700 mb-2">
        ¿Estás seguro de que deseas eliminar <strong id="delete-doc-name" class="text-slate-900"></strong>?
      </p>
      <p id="delete-rag-warning" class="text-sm text-amber-600 mb-6 hidden">
        <i class="iconoir-warning-circle mr-1"></i>
        También se eliminarán los vectores asociados en Qdrant.
      </p>

      <div class="flex gap-3">
        <button id="confirm-delete-btn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors text-sm">
          Sí, eliminar
        </button>
        <button id="cancel-delete-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
          Cancelar
        </button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div id="toast" class="hidden fixed bottom-6 right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 z-[100]">
    <i id="toast-icon" class="iconoir-check-circle text-green-400"></i>
    <span id="toast-message" class="text-sm font-medium">Operación completada</span>
  </div>

  <script>
    const csrf = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    let currentTarget = 'lex';
    let documents = [];
    let selectedDoc = null;

    // Formatos permitidos por target
    const allowedExtensions = {
      lex: ['pdf', 'txt', 'md'],
      eboniato: ['md'],
      ebonia: ['md']
    };

    // API helper
    async function api(path, opts = {}) {
      const headers = { ...(csrf ? { 'X-CSRF-Token': csrf } : {}) };
      
      let body = opts.body;
      if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
      }

      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers,
        body,
        credentials: 'include'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    // Format bytes
    function formatBytes(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    // Format date
    function formatDate(dateStr) {
      if (!dateStr) return '-';
      const d = new Date(dateStr);
      return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Escape HTML
    function escapeHtml(str) {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    // Show toast
    function showToast(message, isError = false) {
      const toast = document.getElementById('toast');
      const icon = document.getElementById('toast-icon');
      const msg = document.getElementById('toast-message');
      
      icon.className = isError ? 'iconoir-xmark-circle text-red-400' : 'iconoir-check-circle text-green-400';
      msg.textContent = message;
      toast.classList.remove('hidden');
      
      setTimeout(() => toast.classList.add('hidden'), 3000);
    }

    // Load documents
    async function loadDocuments() {
      document.getElementById('docs-loading').classList.remove('hidden');
      document.getElementById('docs-container').classList.add('hidden');

      try {
        const data = await api(`/api/admin/context/list.php?target=${currentTarget}`);
        documents = data.documents || [];
        
        // Update stats
        const stats = data.stats || {};
        document.getElementById('stat-docs').textContent = stats.total_documents || 0;
        document.getElementById('stat-size').textContent = formatBytes(stats.total_size || 0);
        document.getElementById('stat-chunks').textContent = stats.total_chunks || 0;
        
        // Show/hide RAG and Type columns
        const isLex = currentTarget === 'lex';
        document.getElementById('col-rag').classList.toggle('hidden', !isLex);
        document.getElementById('col-type').classList.toggle('hidden', !isLex);
        document.getElementById('stat-chunks-container').classList.toggle('hidden', !isLex);
        
        renderDocuments();
      } catch (err) {
        showToast('Error al cargar documentos: ' + err.message, true);
      } finally {
        document.getElementById('docs-loading').classList.add('hidden');
        document.getElementById('docs-container').classList.remove('hidden');
      }
    }

    // Render documents
    function renderDocuments() {
      const container = document.getElementById('docs-list');
      const noResults = document.getElementById('no-docs');
      const isLex = currentTarget === 'lex';

      if (documents.length === 0) {
        container.innerHTML = '';
        noResults.classList.remove('hidden');
        return;
      }

      noResults.classList.add('hidden');
      container.innerHTML = documents.map(doc => {
        const ext = doc.file_extension?.toLowerCase() || '';
        const icon = ext === 'pdf' ? 'iconoir-page' : ext === 'md' ? 'iconoir-page-edit' : 'iconoir-notes';
        
        const statusClass = {
          active: 'status-active',
          pending: 'status-pending',
          processing: 'status-processing',
          error: 'status-error'
        }[doc.status] || 'status-active';
        
        const ragStatusHtml = isLex ? getRagStatusHtml(doc) : '';
        const ragColClass = isLex ? '' : 'hidden';
        
        // Type badge for Lex (PDF = original, TXT = extracted)
        const typeHtml = isLex ? getDocTypeHtml(ext) : '';

        const canEdit = ['md', 'txt'].includes(ext);
        const editBtn = canEdit 
          ? `<button onclick="openEditModal(${doc.id})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-[#23AAC5] hover:bg-[#23AAC5]/5 rounded transition-colors"><i class="iconoir-edit-pencil"></i>Editar</button>`
          : `<button onclick="openEditModal(${doc.id})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 rounded transition-colors"><i class="iconoir-eye"></i>Ver</button>`;

        const processBtn = isLex && doc.rag_status !== 'processed' 
          ? `<button onclick="processRag(${doc.id})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-violet-600 hover:bg-violet-50 rounded transition-colors"><i class="iconoir-refresh"></i>Procesar</button>`
          : isLex && doc.rag_status === 'processed'
          ? `<button onclick="processRag(${doc.id})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-400 hover:bg-slate-100 rounded transition-colors"><i class="iconoir-refresh"></i>Reprocesar</button>`
          : '';

        return `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
                  <i class="${icon} text-lg"></i>
                </div>
                <div class="min-w-0">
                  <div class="font-medium text-slate-800 truncate max-w-xs" title="${escapeHtml(doc.filename)}">${escapeHtml(doc.filename)}</div>
                  <div class="text-xs text-slate-400 truncate max-w-xs">${escapeHtml(doc.description || doc.original_filename)}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 ${ragColClass}">${typeHtml}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${formatBytes(doc.file_size || 0)}</td>
            <td class="px-6 py-4"><span class="status-badge ${statusClass}">${doc.status}</span></td>
            <td class="px-6 py-4 ${ragColClass}">${ragStatusHtml}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${formatDate(doc.created_at)}</td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-1">
                ${editBtn}
                ${processBtn}
                <button onclick="confirmDelete(${doc.id}, '${escapeHtml(doc.filename)}')" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded transition-colors"><i class="iconoir-trash"></i>Eliminar</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    // Get RAG status HTML
    function getRagStatusHtml(doc) {
      const status = doc.rag_status || 'not_applicable';
      const chunks = doc.rag_chunk_count || 0;
      
      const configs = {
        not_applicable: { class: 'bg-slate-100 text-slate-500', icon: 'iconoir-minus', text: 'N/A' },
        pending: { class: 'bg-amber-100 text-amber-700', icon: 'iconoir-clock', text: 'Pendiente' },
        processing: { class: 'bg-blue-100 text-blue-700', icon: 'iconoir-refresh animate-spin', text: 'Procesando' },
        processed: { class: 'bg-emerald-100 text-emerald-700', icon: 'iconoir-check', text: `${chunks} chunks` },
        error: { class: 'bg-red-100 text-red-700', icon: 'iconoir-warning-triangle', text: 'Error' }
      };
      
      const cfg = configs[status] || configs.not_applicable;
      return `<span class="rag-badge ${cfg.class}"><i class="${cfg.icon}"></i>${cfg.text}</span>`;
    }

    // Get document type HTML for Lex
    function getDocTypeHtml(ext) {
      const configs = {
        pdf: { 
          class: 'bg-orange-100 text-orange-700', 
          icon: 'iconoir-page', 
          text: 'Original',
          tooltip: 'Documento original PDF para procesar'
        },
        txt: { 
          class: 'bg-blue-100 text-blue-700', 
          icon: 'iconoir-notes', 
          text: 'Extraído',
          tooltip: 'Texto extraído del PDF original'
        },
        md: { 
          class: 'bg-purple-100 text-purple-700', 
          icon: 'iconoir-page-edit', 
          text: 'Manual',
          tooltip: 'Documento creado manualmente'
        }
      };
      
      const cfg = configs[ext] || { class: 'bg-slate-100 text-slate-500', icon: 'iconoir-file', text: ext.toUpperCase(), tooltip: '' };
      return `<span class="rag-badge ${cfg.class}" title="${cfg.tooltip}"><i class="${cfg.icon}"></i>${cfg.text}</span>`;
    }

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
        btn.classList.add('tab-active');
        currentTarget = btn.dataset.target;
        loadDocuments();
        
        // Update allowed formats
        const formats = allowedExtensions[currentTarget] || [];
        document.getElementById('allowed-formats').textContent = 'Formatos permitidos: ' + formats.map(f => '.' + f).join(', ');
        document.getElementById('file-input').accept = formats.map(f => '.' + f).join(',');
      });
    });

    // Upload modal
    document.getElementById('upload-btn').addEventListener('click', () => {
      document.getElementById('upload-target').value = currentTarget;
      document.getElementById('file-input').value = '';
      document.getElementById('upload-description').value = '';
      document.getElementById('selected-file').classList.add('hidden');
      document.getElementById('upload-error').classList.add('hidden');
      document.getElementById('upload-modal').classList.remove('hidden');
    });

    document.getElementById('close-upload-modal').addEventListener('click', () => {
      document.getElementById('upload-modal').classList.add('hidden');
    });
    document.getElementById('cancel-upload').addEventListener('click', () => {
      document.getElementById('upload-modal').classList.add('hidden');
    });

    // Drag and drop
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('border-[#23AAC5]', 'bg-[#23AAC5]/5');
    });
    dropZone.addEventListener('dragleave', () => {
      dropZone.classList.remove('border-[#23AAC5]', 'bg-[#23AAC5]/5');
    });
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('border-[#23AAC5]', 'bg-[#23AAC5]/5');
      if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        updateSelectedFile();
      }
    });

    fileInput.addEventListener('change', updateSelectedFile);

    function updateSelectedFile() {
      const file = fileInput.files[0];
      if (file) {
        document.getElementById('selected-file-name').textContent = file.name + ' (' + formatBytes(file.size) + ')';
        document.getElementById('selected-file').classList.remove('hidden');
      } else {
        document.getElementById('selected-file').classList.add('hidden');
      }
    }

    // Upload form submit
    document.getElementById('upload-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errorEl = document.getElementById('upload-error');
      errorEl.classList.add('hidden');

      const file = fileInput.files[0];
      if (!file) {
        errorEl.textContent = 'Selecciona un archivo';
        errorEl.classList.remove('hidden');
        return;
      }

      const formData = new FormData();
      formData.append('target', currentTarget);
      formData.append('file', file);
      formData.append('description', document.getElementById('upload-description').value);

      const submitBtn = document.getElementById('upload-submit');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Subiendo...';

      try {
        const result = await api('/api/admin/context/upload.php', {
          method: 'POST',
          body: formData
        });

        document.getElementById('upload-modal').classList.add('hidden');
        showToast('Documento subido correctamente');
        
        if (result.needs_rag_processing) {
          showToast('Documento subido. Recuerda procesarlo para RAG.', false);
        }
        
        await loadDocuments();
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Subir documento';
      }
    });

    // Create document modal
    const targetNames = { lex: 'Lex', eboniato: 'Eboniato', ebonia: 'Ebonia' };
    const createContent = document.getElementById('create-content');

    document.getElementById('create-btn').addEventListener('click', () => {
      document.getElementById('create-target-name').textContent = targetNames[currentTarget] || currentTarget;
      document.getElementById('create-filename').value = '';
      document.getElementById('create-description').value = '';
      document.getElementById('create-content').value = '';
      document.getElementById('create-error').classList.add('hidden');
      
      // Set extension based on target (only md for eboniato/ebonia, allow txt/md for lex)
      const ext = currentTarget === 'lex' ? '.txt' : '.md';
      document.getElementById('create-extension').textContent = ext;
      
      updateCreateCharCount('');
      document.getElementById('create-modal').classList.remove('hidden');
    });

    document.getElementById('close-create-modal').addEventListener('click', () => {
      document.getElementById('create-modal').classList.add('hidden');
    });
    document.getElementById('cancel-create').addEventListener('click', () => {
      document.getElementById('create-modal').classList.add('hidden');
    });

    function updateCreateCharCount(text) {
      const chars = text.length;
      const lines = text.split('\n').length;
      document.getElementById('create-char-count').textContent = `${chars.toLocaleString()} caracteres`;
      document.getElementById('create-line-count').textContent = `${lines.toLocaleString()} líneas`;
    }

    createContent.addEventListener('input', (e) => {
      updateCreateCharCount(e.target.value);
    });

    document.getElementById('create-submit').addEventListener('click', async () => {
      const errorEl = document.getElementById('create-error');
      errorEl.classList.add('hidden');

      const filename = document.getElementById('create-filename').value.trim();
      const content = document.getElementById('create-content').value;
      const description = document.getElementById('create-description').value.trim();

      if (!filename) {
        errorEl.textContent = 'El nombre del archivo es obligatorio';
        errorEl.classList.remove('hidden');
        return;
      }

      if (!content) {
        errorEl.textContent = 'El contenido no puede estar vacío';
        errorEl.classList.remove('hidden');
        return;
      }

      const ext = document.getElementById('create-extension').textContent;
      const fullFilename = filename.replace(/[^a-zA-Z0-9_\-]/g, '_') + ext;

      const submitBtn = document.getElementById('create-submit');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="iconoir-refresh animate-spin mr-1"></i>Creando...';

      try {
        // Create file as blob and upload
        const blob = new Blob([content], { type: 'text/plain' });
        const file = new File([blob], fullFilename, { type: 'text/plain' });

        const formData = new FormData();
        formData.append('target', currentTarget);
        formData.append('file', file);
        formData.append('description', description);

        await api('/api/admin/context/upload.php', {
          method: 'POST',
          body: formData
        });

        document.getElementById('create-modal').classList.add('hidden');
        showToast('Documento creado correctamente');
        await loadDocuments();
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="iconoir-plus mr-1"></i>Crear documento';
      }
    });

    // Edit modal
    window.openEditModal = async function(docId) {
      const doc = documents.find(d => d.id === docId);
      if (!doc) return;
      
      selectedDoc = doc;
      const canEdit = ['md', 'txt'].includes(doc.file_extension?.toLowerCase());
      const ext = doc.file_extension?.toLowerCase() || '';
      
      // Update header info
      document.getElementById('edit-modal-title').textContent = canEdit ? 'Editar documento' : 'Ver documento';
      document.getElementById('edit-doc-name').textContent = doc.filename || 'documento';
      
      // Icon según tipo
      const iconClass = ext === 'pdf' ? 'iconoir-page' : ext === 'md' ? 'iconoir-page-edit' : 'iconoir-notes';
      document.getElementById('edit-doc-icon').className = iconClass + ' text-xl';
      
      // Info bar
      document.getElementById('edit-doc-target').textContent = doc.target || '-';
      document.getElementById('edit-doc-size').textContent = formatBytes(doc.file_size || 0);
      document.getElementById('edit-doc-date').textContent = formatDate(doc.created_at);
      
      // Config UI
      document.getElementById('edit-content').disabled = !canEdit;
      document.getElementById('save-content-btn').classList.toggle('hidden', !canEdit);
      document.getElementById('edit-readonly-notice').classList.toggle('hidden', canEdit);
      document.getElementById('edit-rag-notice').classList.toggle('hidden', !(canEdit && doc.target === 'lex'));
      document.getElementById('edit-error').classList.add('hidden');
      
      // Load content
      document.getElementById('edit-content').value = 'Cargando contenido...';
      try {
        const data = await api(`/api/admin/context/view.php?id=${docId}`);
        const content = data.content || '';
        document.getElementById('edit-content').value = content;
        updateCharCount(content);
      } catch (err) {
        document.getElementById('edit-content').value = 'Error al cargar contenido: ' + err.message;
        updateCharCount('');
      }
      
      document.getElementById('edit-modal').classList.remove('hidden');
    };

    // Update character and line count
    function updateCharCount(text) {
      const chars = text.length;
      const lines = text.split('\n').length;
      document.getElementById('edit-char-count').textContent = `${chars.toLocaleString()} caracteres`;
      document.getElementById('edit-line-count').textContent = `${lines.toLocaleString()} líneas`;
    }

    // Update count on input
    document.getElementById('edit-content').addEventListener('input', (e) => {
      updateCharCount(e.target.value);
    });

    document.getElementById('close-edit-modal').addEventListener('click', () => {
      document.getElementById('edit-modal').classList.add('hidden');
    });
    document.getElementById('close-edit-btn').addEventListener('click', () => {
      document.getElementById('edit-modal').classList.add('hidden');
    });

    document.getElementById('save-content-btn').addEventListener('click', async () => {
      if (!selectedDoc) return;
      
      const errorEl = document.getElementById('edit-error');
      errorEl.classList.add('hidden');
      
      const content = document.getElementById('edit-content').value;
      const btn = document.getElementById('save-content-btn');
      btn.disabled = true;
      btn.textContent = 'Guardando...';

      try {
        await api('/api/admin/context/update.php', {
          method: 'PUT',
          body: { id: selectedDoc.id, content }
        });

        document.getElementById('edit-modal').classList.add('hidden');
        showToast('Documento guardado correctamente');
        await loadDocuments();
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
      }
    });

    // Process RAG
    window.processRag = async function(docId) {
      const doc = documents.find(d => d.id === docId);
      if (!doc) return;

      showToast('Procesando documento... esto puede tardar unos segundos');

      try {
        await api(`/api/admin/context/process-rag.php?id=${docId}`, { method: 'POST' });
        showToast('Documento procesado correctamente');
        await loadDocuments();
      } catch (err) {
        showToast('Error al procesar: ' + err.message, true);
        await loadDocuments(); // Reload to show error status
      }
    };

    // Delete
    let docToDelete = null;

    window.confirmDelete = function(docId, docName) {
      const doc = documents.find(d => d.id === docId);
      docToDelete = docId;
      document.getElementById('delete-doc-name').textContent = docName;
      document.getElementById('delete-rag-warning').classList.toggle('hidden', 
        !(doc && doc.rag_status === 'processed' && doc.rag_chunk_count > 0));
      document.getElementById('delete-modal').classList.remove('hidden');
    };

    document.getElementById('cancel-delete-btn').addEventListener('click', () => {
      document.getElementById('delete-modal').classList.add('hidden');
      docToDelete = null;
    });

    document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
      if (!docToDelete) return;

      const btn = document.getElementById('confirm-delete-btn');
      btn.disabled = true;
      btn.textContent = 'Eliminando...';

      try {
        await api(`/api/admin/context/delete.php?id=${docToDelete}`, { method: 'DELETE' });
        document.getElementById('delete-modal').classList.add('hidden');
        showToast('Documento eliminado');
        docToDelete = null;
        await loadDocuments();
      } catch (err) {
        showToast('Error al eliminar: ' + err.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Sí, eliminar';
      }
    });

    // Init
    loadDocuments();
  </script>
</body>
</html>

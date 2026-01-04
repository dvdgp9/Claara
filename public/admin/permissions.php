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

// Verificar si es superadmin (soporta sesiones antiguas sin is_superadmin)
$isSuperadmin = !empty($user['is_superadmin']) || in_array('admin', $user['roles'] ?? [], true);
if (!$isSuperadmin) {
    header('Location: /');
    exit;
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Permisos de funcionalidades — Ebonia</title>
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
    
    /* Toggle switch moderno */
    .toggle-switch {
      position: relative;
      width: 44px;
      height: 24px;
      cursor: pointer;
    }
    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .toggle-slider {
      position: absolute;
      inset: 0;
      background: #e2e8f0;
      border-radius: 24px;
      transition: all 0.2s ease;
    }
    .toggle-slider:before {
      content: "";
      position: absolute;
      left: 2px;
      top: 2px;
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      transition: all 0.2s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .toggle-switch input:checked + .toggle-slider {
      background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%);
    }
    .toggle-switch input:checked + .toggle-slider:before {
      transform: translateX(20px);
    }
    .toggle-switch input:disabled + .toggle-slider {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    /* Animación de guardado */
    .saving {
      animation: pulse 1s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = '';
    $pageTitle = 'Permisos de funcionalidades';
    include __DIR__ . '/../includes/left-tabs.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-7xl mx-auto p-4 lg:p-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 lg:mb-8 mt-4 lg:mt-6">
            <div>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Permisos de funcionalidades</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">Controla el acceso a gestos, voces y generación de imágenes por usuario</p>
            </div>
          </div>

          <!-- Loading -->
          <div id="loading" class="text-center py-12">
            <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#23AAC5] border-r-transparent"></div>
            <p class="text-sm text-slate-500 mt-3">Cargando permisos...</p>
          </div>

          <!-- Contenido principal -->
          <div id="main-content" class="hidden space-y-6">
            <!-- Selector de usuario -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
              <label class="text-sm font-medium text-slate-700 block mb-3">Selecciona un usuario para gestionar sus permisos</label>
              <select id="user-select" class="w-full max-w-md px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors text-base">
                <option value="">-- Selecciona un usuario --</option>
              </select>
            </div>

            <!-- Panel de permisos (oculto hasta seleccionar usuario) -->
            <div id="permissions-panel" class="hidden">
              <!-- Info del usuario seleccionado -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                <div class="flex items-center gap-4">
                  <div id="user-avatar" class="h-14 w-14 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center text-white font-bold text-lg"></div>
                  <div class="flex-1">
                    <h2 id="user-name" class="text-lg font-semibold text-slate-800"></h2>
                    <p id="user-email" class="text-sm text-slate-500"></p>
                  </div>
                  <div id="superadmin-badge" class="hidden px-3 py-1.5 rounded-full bg-gradient-to-r from-[#23AAC5]/10 to-[#115c6c]/10 text-[#23AAC5] text-sm font-medium">
                    <i class="iconoir-crown mr-1"></i> Superadmin
                  </div>
                </div>
                <p id="superadmin-notice" class="hidden mt-4 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                  <i class="iconoir-info-circle mr-1"></i>
                  Los superadministradores tienen acceso a todas las funcionalidades automáticamente.
                </p>
              </div>

              <!-- Grid de secciones -->
              <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Sección Gestos -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                  <div class="p-5 border-b border-slate-100 bg-gradient-to-r from-[#23AAC5]/5 to-transparent">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center">
                          <i class="iconoir-magic-wand text-white text-lg"></i>
                        </div>
                        <div>
                          <h3 class="font-semibold text-slate-800">Gestos</h3>
                          <p class="text-xs text-slate-500">Acciones rápidas</p>
                        </div>
                      </div>
                      <div class="flex gap-2">
                        <button onclick="toggleAllOfType('gesture', true)" class="text-xs px-2.5 py-1 bg-[#23AAC5]/10 text-[#23AAC5] rounded-lg hover:bg-[#23AAC5]/20 transition-colors font-medium">
                          Todos
                        </button>
                        <button onclick="toggleAllOfType('gesture', false)" class="text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors font-medium">
                          Ninguno
                        </button>
                      </div>
                    </div>
                  </div>
                  <div id="gestures-list" class="divide-y divide-slate-100">
                    <!-- Se llena dinámicamente -->
                  </div>
                </div>

                <!-- Sección Voces -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                  <div class="p-5 border-b border-slate-100 bg-gradient-to-r from-violet-500/5 to-transparent">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center">
                          <i class="iconoir-voice-square text-white text-lg"></i>
                        </div>
                        <div>
                          <h3 class="font-semibold text-slate-800">Voces</h3>
                          <p class="text-xs text-slate-500">Asistentes especializados</p>
                        </div>
                      </div>
                      <div class="flex gap-2">
                        <button onclick="toggleAllOfType('voice', true)" class="text-xs px-2.5 py-1 bg-violet-100 text-violet-600 rounded-lg hover:bg-violet-200 transition-colors font-medium">
                          Todos
                        </button>
                        <button onclick="toggleAllOfType('voice', false)" class="text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors font-medium">
                          Ninguno
                        </button>
                      </div>
                    </div>
                  </div>
                  <div id="voices-list" class="divide-y divide-slate-100">
                    <!-- Se llena dinámicamente -->
                  </div>
                </div>

                <!-- Sección Features globales -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                  <div class="p-5 border-b border-slate-100 bg-gradient-to-r from-amber-500/5 to-transparent">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center">
                        <i class="iconoir-sparks text-white text-lg"></i>
                      </div>
                      <div>
                        <h3 class="font-semibold text-slate-800">Funcionalidades</h3>
                        <p class="text-xs text-slate-500">Características especiales</p>
                      </div>
                    </div>
                  </div>
                  <div id="features-list" class="divide-y divide-slate-100">
                    <!-- Se llena dinámicamente -->
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Toast de guardado -->
  <div id="save-toast" class="hidden fixed bottom-20 lg:bottom-6 right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 z-50">
    <div class="h-5 w-5 animate-spin rounded-full border-2 border-white border-r-transparent"></div>
    <span class="text-sm font-medium">Guardando...</span>
  </div>

  <script>
    const csrf = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    let allFeatures = { gesture: [], voice: [], feature: [] };
    let allUsers = [];
    let selectedUserId = null;
    let selectedUser = null;

    // API helper
    async function api(path, opts = {}) {
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    // Cargar datos iniciales
    async function loadData() {
      try {
        const data = await api('/api/admin/features/list.php');
        allFeatures = data.features || { gesture: [], voice: [], feature: [] };
        allUsers = data.users || [];
        
        renderUserSelect();
        
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('main-content').classList.remove('hidden');
      } catch (err) {
        document.getElementById('loading').innerHTML = `<p class="text-sm text-red-600">Error al cargar datos: ${err.message}</p>`;
      }
    }

    // Renderizar selector de usuarios
    function renderUserSelect() {
      const select = document.getElementById('user-select');
      const options = allUsers
        .filter(u => !u.is_superadmin) // Mostrar todos pero marcar superadmins
        .map(u => `<option value="${u.id}">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)} (${escapeHtml(u.email)})</option>`)
        .join('');
      
      // Añadir también superadmins al final con indicador
      const superadmins = allUsers
        .filter(u => u.is_superadmin)
        .map(u => `<option value="${u.id}">👑 ${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)} (Superadmin)</option>`)
        .join('');
      
      select.innerHTML = '<option value="">-- Selecciona un usuario --</option>' + options + superadmins;
    }

    // Manejar selección de usuario
    document.getElementById('user-select').addEventListener('change', (e) => {
      selectedUserId = e.target.value ? parseInt(e.target.value) : null;
      selectedUser = allUsers.find(u => u.id === selectedUserId) || null;
      
      if (selectedUser) {
        renderUserInfo();
        renderPermissions();
        document.getElementById('permissions-panel').classList.remove('hidden');
      } else {
        document.getElementById('permissions-panel').classList.add('hidden');
      }
    });

    // Renderizar info del usuario
    function renderUserInfo() {
      if (!selectedUser) return;
      
      document.getElementById('user-avatar').textContent = 
        (selectedUser.first_name[0] + selectedUser.last_name[0]).toUpperCase();
      document.getElementById('user-name').textContent = 
        `${selectedUser.first_name} ${selectedUser.last_name}`;
      document.getElementById('user-email').textContent = selectedUser.email;
      
      const isSuperadmin = selectedUser.is_superadmin;
      document.getElementById('superadmin-badge').classList.toggle('hidden', !isSuperadmin);
      document.getElementById('superadmin-notice').classList.toggle('hidden', !isSuperadmin);
    }

    // Renderizar permisos
    function renderPermissions() {
      renderFeatureList('gesture', 'gestures-list');
      renderFeatureList('voice', 'voices-list');
      renderFeatureList('feature', 'features-list');
    }

    // Renderizar lista de features de un tipo
    function renderFeatureList(type, containerId) {
      const container = document.getElementById(containerId);
      const features = allFeatures[type] || [];
      const isSuperadmin = selectedUser?.is_superadmin;
      
      if (features.length === 0) {
        container.innerHTML = '<p class="p-5 text-sm text-slate-400 text-center">No hay elementos disponibles</p>';
        return;
      }
      
      container.innerHTML = features.map(f => {
        const key = `${type}:${f.feature_slug}`;
        const isEnabled = isSuperadmin || (selectedUser?.access?.[key] === true);
        const disabled = isSuperadmin ? 'disabled' : '';
        
        return `
          <div class="p-4 flex items-center gap-4 hover:bg-slate-50/50 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
              <i class="${f.icon || 'iconoir-puzzle'} text-slate-500"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm text-slate-800">${escapeHtml(f.name)}</div>
              <div class="text-xs text-slate-500 truncate">${escapeHtml(f.description || '')}</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" 
                     data-type="${type}" 
                     data-slug="${f.feature_slug}"
                     ${isEnabled ? 'checked' : ''} 
                     ${disabled}
                     onchange="togglePermission(this)">
              <span class="toggle-slider"></span>
            </label>
          </div>
        `;
      }).join('');
    }

    // Toggle permiso individual
    async function togglePermission(checkbox) {
      if (!selectedUserId) return;
      
      const type = checkbox.dataset.type;
      const slug = checkbox.dataset.slug;
      const enabled = checkbox.checked;
      
      showSaveToast();
      checkbox.disabled = true;
      
      try {
        await api('/api/admin/features/update.php', {
          method: 'POST',
          body: {
            user_id: selectedUserId,
            feature_type: type,
            feature_slug: slug,
            enabled: enabled
          }
        });
        
        // Actualizar estado local
        const key = `${type}:${slug}`;
        if (!selectedUser.access) selectedUser.access = {};
        selectedUser.access[key] = enabled;
        
        hideSaveToast(true);
      } catch (err) {
        checkbox.checked = !enabled; // Revertir
        hideSaveToast(false, err.message);
      } finally {
        checkbox.disabled = false;
      }
    }

    // Toggle todos de un tipo
    async function toggleAllOfType(type, enable) {
      if (!selectedUserId || selectedUser?.is_superadmin) return;
      
      showSaveToast();
      
      // Deshabilitar todos los toggles de ese tipo
      const checkboxes = document.querySelectorAll(`input[data-type="${type}"]`);
      checkboxes.forEach(cb => cb.disabled = true);
      
      try {
        const result = await api('/api/admin/features/bulk-update.php', {
          method: 'POST',
          body: {
            user_id: selectedUserId,
            feature_type: type,
            action: enable ? 'enable_all' : 'disable_all'
          }
        });
        
        // Actualizar estado local
        selectedUser.access = result.access || {};
        
        // Actualizar UI
        checkboxes.forEach(cb => {
          const key = `${type}:${cb.dataset.slug}`;
          cb.checked = selectedUser.access[key] === true;
        });
        
        hideSaveToast(true);
      } catch (err) {
        hideSaveToast(false, err.message);
      } finally {
        checkboxes.forEach(cb => cb.disabled = false);
      }
    }

    // Toast de guardado
    function showSaveToast() {
      const toast = document.getElementById('save-toast');
      toast.innerHTML = `
        <div class="h-5 w-5 animate-spin rounded-full border-2 border-white border-r-transparent"></div>
        <span class="text-sm font-medium">Guardando...</span>
      `;
      toast.classList.remove('hidden', 'bg-green-600', 'bg-red-600');
      toast.classList.add('bg-slate-800');
    }

    function hideSaveToast(success, message = '') {
      const toast = document.getElementById('save-toast');
      
      if (success) {
        toast.innerHTML = `
          <i class="iconoir-check text-lg"></i>
          <span class="text-sm font-medium">Guardado</span>
        `;
        toast.classList.remove('bg-slate-800', 'bg-red-600');
        toast.classList.add('bg-green-600');
      } else {
        toast.innerHTML = `
          <i class="iconoir-warning-circle text-lg"></i>
          <span class="text-sm font-medium">Error: ${escapeHtml(message)}</span>
        `;
        toast.classList.remove('bg-slate-800', 'bg-green-600');
        toast.classList.add('bg-red-600');
      }
      
      setTimeout(() => {
        toast.classList.add('hidden');
      }, 2000);
    }

    // Escape HTML
    function escapeHtml(str) {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    // Iniciar
    loadData();
  </script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>

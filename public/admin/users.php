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
  <title>Gestión de usuarios — Ebonia</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
  <style>
    /* Estilos base para el layout */
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
  </style>
</head>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php 
    $activeTab = '';
    $pageTitle = 'Gestión de usuarios';
    include __DIR__ . '/../includes/left-tabs.php'; 
    ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-7xl mx-auto p-4 lg:p-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 lg:mb-8 mt-4 lg:mt-6">
            <div>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Gestión de usuarios</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">Crear, editar y gestionar cuentas de usuario</p>
            </div>
            <button id="create-user-btn" class="w-full sm:w-auto px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 hover:shadow-lg transition-all flex items-center justify-center gap-2 shadow-md">
              <i class="iconoir-plus-circle"></i>
              <span>Nuevo usuario</span>
            </button>
          </div>

          <!-- Búsqueda y filtros -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
      <div class="flex items-center gap-4">
        <div class="flex-1 relative">
          <i class="iconoir-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" id="search-input" placeholder="Buscar por nombre, email..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors">
        </div>
        <select id="status-filter" class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors">
          <option value="">Todos los estados</option>
          <option value="active">Activos</option>
          <option value="disabled">Deshabilitados</option>
        </select>
      </div>
    </div>

    <!-- Lista de usuarios -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <div id="users-loading" class="text-center py-12">
        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#23AAC5] border-r-transparent"></div>
        <p class="text-sm text-slate-500 mt-3">Cargando usuarios...</p>
      </div>

      <div id="users-container" class="hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Departamento</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Último acceso</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody id="users-list" class="divide-y divide-slate-200">
              <!-- Se llena dinámicamente -->
            </tbody>
          </table>
        </div>
        <div id="no-results" class="hidden text-center py-12">
          <i class="iconoir-search text-4xl text-slate-300"></i>
          <p class="text-slate-500 mt-3">No se encontraron usuarios</p>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</main>
</div>

  <!-- Modal confirmación eliminar -->
  <div id="delete-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="h-12 w-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
          <i class="iconoir-warning-triangle text-red-600 text-2xl"></i>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-slate-800">Eliminar usuario</h3>
          <p class="text-sm text-slate-600 mt-0.5">Esta acción no se puede deshacer</p>
        </div>
      </div>

      <p class="text-slate-700 mb-6">
        ¿Estás seguro de que deseas eliminar al usuario <strong id="delete-user-name" class="text-slate-900"></strong>?
        <br><br>
        Se eliminarán todas sus conversaciones, mensajes y datos asociados de forma permanente.
      </p>

      <div class="flex gap-3">
        <button id="confirm-delete-btn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors text-sm">
          Sí, eliminar usuario
        </button>
        <button id="cancel-delete-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
          Cancelar
        </button>
      </div>
    </div>
  </div>

  <!-- Modal crear/editar usuario -->
  <div id="user-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-slate-800" id="modal-title">Nuevo usuario</h3>
        <button id="close-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="user-form" class="space-y-4">
        <input type="hidden" id="user-id">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Nombre *</label>
            <input type="text" id="user-first-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Apellidos *</label>
            <input type="text" id="user-last-name" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
          </div>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Email *</label>
          <input type="email" id="user-email" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" required>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Departamento</label>
          <select id="user-department" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors">
            <option value="">Sin asignar</option>
            <!-- Se llena dinámicamente -->
          </select>
        </div>

        <div id="password-section">
          <label class="text-sm font-medium text-slate-700 block mb-2">
            <span id="password-label">Contraseña *</span>
          </label>
          <div class="relative">
            <input type="password" id="user-password" class="w-full pl-3 pr-10 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#23AAC5] focus:ring-2 focus:ring-[#23AAC5]/20 transition-colors" minlength="8">
            <button type="button" id="toggle-password-btn" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
              <i class="iconoir-eye text-lg" id="toggle-password-icon"></i>
            </button>
          </div>
          <p class="text-xs text-slate-500 mt-1" id="password-hint">Mínimo 8 caracteres</p>
        </div>

        <div class="flex items-center gap-4 pt-2 border-t border-slate-100">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="user-superadmin" class="w-4 h-4 text-[#23AAC5] border-slate-300 rounded focus:ring-[#23AAC5]">
            <span class="text-sm font-medium text-slate-700">Superadministrador</span>
          </label>
          
          <label class="flex items-center gap-2 cursor-pointer" id="status-toggle-container">
            <input type="checkbox" id="user-status" class="w-4 h-4 text-[#23AAC5] border-slate-300 rounded focus:ring-[#23AAC5]" checked>
            <span class="text-sm font-medium text-slate-700">Cuenta activa</span>
          </label>
        </div>

        <div id="user-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>

        <div class="flex gap-3 pt-2">
          <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-[#23AAC5] to-[#115c6c] text-white rounded-lg font-medium hover:opacity-90 transition-all text-sm shadow-md">
            <span id="submit-text">Crear usuario</span>
          </button>
          <button type="button" id="cancel-modal-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal permisos -->
  <div id="permissions-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 max-h-[90vh] overflow-hidden flex flex-col">
      <div class="flex items-center justify-between mb-6 flex-shrink-0">
        <div class="flex items-center gap-4">
          <div id="perm-user-avatar" class="h-12 w-12 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center text-white font-bold text-lg"></div>
          <div>
            <h3 class="text-lg font-semibold text-slate-800" id="perm-user-name">Permisos de usuario</h3>
            <p id="perm-user-email" class="text-sm text-slate-500"></p>
          </div>
        </div>
        <button id="close-permissions-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <div id="perm-superadmin-notice" class="hidden mb-6 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 flex-shrink-0">
        <i class="iconoir-info-circle mr-1"></i>
        Los superadministradores tienen acceso a todas las funcionalidades automáticamente.
      </div>

      <div class="flex-1 overflow-y-auto pr-2 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Gestos -->
          <div class="bg-slate-50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-4">
              <h4 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="iconoir-magic-wand text-[#23AAC5]"></i> Gestos
              </h4>
              <div class="flex gap-1">
                <button onclick="toggleAllOfType('gesture', true)" class="text-[10px] px-1.5 py-0.5 bg-[#23AAC5]/10 text-[#23AAC5] rounded-md hover:bg-[#23AAC5]/20 font-medium">All</button>
                <button onclick="toggleAllOfType('gesture', false)" class="text-[10px] px-1.5 py-0.5 bg-slate-200 text-slate-600 rounded-md hover:bg-slate-300 font-medium">None</button>
              </div>
            </div>
            <div id="gestures-list" class="space-y-3"></div>
          </div>

          <!-- Voces -->
          <div class="bg-slate-50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-4">
              <h4 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="iconoir-voice-square text-violet-500"></i> Voces
              </h4>
              <div class="flex gap-1">
                <button onclick="toggleAllOfType('voice', true)" class="text-[10px] px-1.5 py-0.5 bg-violet-100 text-violet-600 rounded-md hover:bg-violet-200 font-medium">All</button>
                <button onclick="toggleAllOfType('voice', false)" class="text-[10px] px-1.5 py-0.5 bg-slate-200 text-slate-600 rounded-md hover:bg-slate-300 font-medium">None</button>
              </div>
            </div>
            <div id="voices-list" class="space-y-3"></div>
          </div>

          <!-- Features -->
          <div class="bg-slate-50 rounded-xl p-4">
            <div class="mb-4">
              <h4 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="iconoir-sparks text-amber-500"></i> Funcionalidades
              </h4>
            </div>
            <div id="features-list" class="space-y-3"></div>
          </div>
        </div>
      </div>

      <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end flex-shrink-0">
        <button id="close-perm-modal-btn" class="px-6 py-2 bg-slate-900 text-white rounded-lg font-medium hover:bg-slate-800 transition-colors text-sm">
          Cerrar
        </button>
      </div>
    </div>
  </div>

  <!-- Toast de guardado -->
  <div id="save-toast" class="hidden fixed bottom-6 right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 z-[100]">
    <div class="h-5 w-5 animate-spin rounded-full border-2 border-white border-r-transparent"></div>
    <span class="text-sm font-medium">Guardando...</span>
  </div>

  <script>
    const csrf = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    let allUsers = [];
    let allDepartments = [];
    let allFeatures = { gesture: [], voice: [], feature: [] };
    let selectedUser = null;
    let isEditMode = false;
    
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

    // Cargar datos iniciales (usuarios, departamentos y catálogo de features)
    async function init() {
      await Promise.all([
        loadUsers(),
        loadDepartments(),
        loadFeatures()
      ]);
    }

    // Cargar catálogo de features
    async function loadFeatures() {
      try {
        const data = await api('/api/admin/features/list.php');
        allFeatures = data.features || { gesture: [], voice: [], feature: [] };
      } catch (err) {
        console.error('Error al cargar catálogo de features:', err);
      }
    }

    // Cargar usuarios
    async function loadUsers() {
      try {
        const data = await api('/api/admin/users/list.php');
        allUsers = data.users || [];
        renderUsers();
        document.getElementById('users-loading').classList.add('hidden');
        document.getElementById('users-container').classList.remove('hidden');
      } catch (err) {
        document.getElementById('users-loading').innerHTML = '<p class="text-sm text-red-600">Error al cargar usuarios</p>';
      }
    }

    // Cargar departamentos
    async function loadDepartments() {
      try {
        const data = await api('/api/admin/departments/list.php');
        allDepartments = data.departments || [];
        renderDepartmentOptions();
      } catch (err) {
        console.error('Error al cargar departamentos:', err);
      }
    }

    // Renderizar opciones de departamento
    function renderDepartmentOptions() {
      const select = document.getElementById('user-department');
      const currentOptions = select.innerHTML;
      const firstOption = '<option value="">Sin asignar</option>';
      
      const options = allDepartments.map(d => 
        `<option value="${d.id}">${escapeHtml(d.name)}</option>`
      ).join('');
      
      select.innerHTML = firstOption + options;
    }

    // Renderizar usuarios
    function renderUsers() {
      const container = document.getElementById('users-list');
      const noResults = document.getElementById('no-results');
      const searchTerm = document.getElementById('search-input').value.toLowerCase();
      const statusFilter = document.getElementById('status-filter').value;

      let filtered = allUsers.filter(u => {
        const matchesSearch = !searchTerm || 
          u.first_name.toLowerCase().includes(searchTerm) ||
          u.last_name.toLowerCase().includes(searchTerm) ||
          u.email.toLowerCase().includes(searchTerm);
        
        const matchesStatus = !statusFilter || u.status === statusFilter;
        
        return matchesSearch && matchesStatus;
      });

      if (filtered.length === 0) {
        container.innerHTML = '';
        noResults.classList.remove('hidden');
        return;
      }

      noResults.classList.add('hidden');
      container.innerHTML = filtered.map(u => {
        const statusBadge = u.status === 'active'
          ? '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Activo</span>'
          : '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Deshabilitado</span>';
        
        const lastLogin = u.last_login_at 
          ? new Date(u.last_login_at).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
          : '<span class="text-slate-400">Nunca</span>';
        
        const superadminBadge = u.is_superadmin 
          ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#23AAC5]/10 text-[#23AAC5] ml-2">Admin</span>'
          : '';

        return `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-[#23AAC5] to-[#115c6c] flex items-center justify-center text-white font-semibold text-sm">
                  ${escapeHtml(u.first_name[0] + u.last_name[0]).toUpperCase()}
                </div>
                <div>
                  <div class="font-medium text-slate-800">
                    ${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}
                    ${superadminBadge}
                  </div>
                  <div class="text-xs text-slate-500">ID: ${u.id}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${escapeHtml(u.email)}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${escapeHtml(u.department_name || 'Sin asignar')}</td>
            <td class="px-6 py-4">${statusBadge}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${lastLogin}</td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button onclick="openPermissions(${u.id})" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
                  <i class="iconoir-lock"></i>
                  <span>Permisos</span>
                </button>
                <button onclick="editUser(${u.id})" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-[#23AAC5] hover:text-[#115c6c] hover:bg-[#23AAC5]/5 rounded-lg transition-colors">
                  <i class="iconoir-edit-pencil"></i>
                  <span>Editar</span>
                </button>
                <button onclick="confirmDeleteUser(${u.id}, '${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                  <i class="iconoir-trash"></i>
                  <span>Eliminar</span>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    // Abrir modal crear
    document.getElementById('create-user-btn').addEventListener('click', () => {
      isEditMode = false;
      document.getElementById('modal-title').textContent = 'Nuevo usuario';
      document.getElementById('submit-text').textContent = 'Crear usuario';
      document.getElementById('user-form').reset();
      document.getElementById('user-id').value = '';
      document.getElementById('user-status').checked = true;
      document.getElementById('password-label').innerHTML = 'Contraseña *';
      document.getElementById('password-hint').textContent = 'Mínimo 8 caracteres';
      document.getElementById('user-password').required = true;
      document.getElementById('status-toggle-container').classList.add('hidden');
      document.getElementById('user-error').classList.add('hidden');
      document.getElementById('user-modal').classList.remove('hidden');
    });

    // Editar usuario
    window.editUser = function(userId) {
      const user = allUsers.find(u => u.id === userId);
      if (!user) return;
      
      isEditMode = true;
      document.getElementById('modal-title').textContent = 'Editar usuario';
      document.getElementById('submit-text').textContent = 'Guardar cambios';
      document.getElementById('user-id').value = user.id;
      document.getElementById('user-first-name').value = user.first_name;
      document.getElementById('user-last-name').value = user.last_name;
      document.getElementById('user-email').value = user.email;
      document.getElementById('user-department').value = user.department_id || '';
      document.getElementById('user-superadmin').checked = !!user.is_superadmin;
      document.getElementById('user-status').checked = user.status === 'active';
      document.getElementById('user-password').value = '';
      document.getElementById('user-password').required = false;
      document.getElementById('password-label').innerHTML = 'Nueva contraseña <span class="text-slate-400">(dejar vacío para mantener)</span>';
      document.getElementById('password-hint').textContent = 'Solo completar si deseas cambiar la contraseña';
      document.getElementById('status-toggle-container').classList.remove('hidden');
      document.getElementById('user-error').classList.add('hidden');
      document.getElementById('user-modal').classList.remove('hidden');
    };

    // Cerrar modal
    [document.getElementById('close-modal-btn'), document.getElementById('cancel-modal-btn')].forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('user-modal').classList.add('hidden');
      });
    });

    // Toggle mostrar/ocultar contraseña
    const togglePasswordBtn = document.getElementById('toggle-password-btn');
    const passwordInput = document.getElementById('user-password');
    const passwordIcon = document.getElementById('toggle-password-icon');

    togglePasswordBtn?.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      passwordIcon.className = isPassword ? 'iconoir-eye-closed text-lg' : 'iconoir-eye text-lg';
    });

    // Resetear tipo de contraseña al abrir modal
    document.getElementById('create-user-btn').addEventListener('click', () => {
      passwordInput.type = 'password';
      passwordIcon.className = 'iconoir-eye text-lg';
    });

    // También al editar
    const originalEditUser = window.editUser;
    window.editUser = function(userId) {
      passwordInput.type = 'password';
      passwordIcon.className = 'iconoir-eye text-lg';
      originalEditUser(userId);
    };

    // Submit formulario
    document.getElementById('user-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errorEl = document.getElementById('user-error');
      errorEl.classList.add('hidden');

      const userId = document.getElementById('user-id').value;
      const firstName = document.getElementById('user-first-name').value.trim();
      const lastName = document.getElementById('user-last-name').value.trim();
      const email = document.getElementById('user-email').value.trim();
      const departmentId = document.getElementById('user-department').value || null;
      const password = document.getElementById('user-password').value;
      const isSuperadmin = document.getElementById('user-superadmin').checked;
      const isActive = document.getElementById('user-status').checked;

      const submitBtn = e.target.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = isEditMode ? 'Guardando...' : 'Creando...';

      try {
        if (isEditMode) {
          await api('/api/admin/users/update.php', {
            method: 'POST',
            body: {
              id: parseInt(userId),
              first_name: firstName,
              last_name: lastName,
              email: email,
              department_id: departmentId,
              status: isActive ? 'active' : 'disabled',
              is_superadmin: isSuperadmin,
              new_password: password
            }
          });
        } else {
          await api('/api/admin/users/create.php', {
            method: 'POST',
            body: {
              first_name: firstName,
              last_name: lastName,
              email: email,
              password: password,
              department_id: departmentId,
              is_superadmin: isSuperadmin
            }
          });
        }

        document.getElementById('user-modal').classList.add('hidden');
        await loadUsers();
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = isEditMode ? 'Guardar cambios' : 'Crear usuario';
      }
    });

    // Eliminar usuario
    let userToDelete = null;
    
    window.confirmDeleteUser = function(userId, userName) {
      userToDelete = userId;
      document.getElementById('delete-user-name').textContent = userName;
      document.getElementById('delete-modal').classList.remove('hidden');
    };

    document.getElementById('cancel-delete-btn').addEventListener('click', () => {
      document.getElementById('delete-modal').classList.add('hidden');
      userToDelete = null;
    });

    // Cerrar modal al hacer clic fuera
    document.getElementById('delete-modal').addEventListener('click', (e) => {
      if (e.target.id === 'delete-modal') {
        document.getElementById('delete-modal').classList.add('hidden');
        userToDelete = null;
      }
    });

    document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
      if (!userToDelete) return;

      const btn = document.getElementById('confirm-delete-btn');
      btn.disabled = true;
      btn.textContent = 'Eliminando...';

      try {
        await api('/api/admin/users/delete.php', {
          method: 'POST',
          body: { id: userToDelete }
        });

        document.getElementById('delete-modal').classList.add('hidden');
        userToDelete = null;
        await loadUsers();
      } catch (err) {
        alert('Error al eliminar usuario: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Sí, eliminar usuario';
      }
    });

    // Búsqueda y filtros
    document.getElementById('search-input').addEventListener('input', renderUsers);
    document.getElementById('status-filter').addEventListener('change', renderUsers);

    // Escape HTML
    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    // Permisos
    window.openPermissions = function(userId) {
      selectedUser = allUsers.find(u => u.id === userId);
      if (!selectedUser) return;

      document.getElementById('perm-user-avatar').textContent = 
        (selectedUser.first_name[0] + selectedUser.last_name[0]).toUpperCase();
      document.getElementById('perm-user-name').textContent = 
        `${selectedUser.first_name} ${selectedUser.last_name}`;
      document.getElementById('perm-user-email').textContent = selectedUser.email;

      const isSuperadmin = !!selectedUser.is_superadmin;
      document.getElementById('perm-superadmin-notice').classList.toggle('hidden', !isSuperadmin);

      renderPermissionsList('gesture', 'gestures-list');
      renderPermissionsList('voice', 'voices-list');
      renderPermissionsList('feature', 'features-list');

      document.getElementById('permissions-modal').classList.remove('hidden');
    };

    function renderPermissionsList(type, containerId) {
      const container = document.getElementById(containerId);
      const features = allFeatures[type] || [];
      const isSuperadmin = !!selectedUser.is_superadmin;

      if (features.length === 0) {
        container.innerHTML = '<p class="text-xs text-slate-400 text-center py-2">No disponible</p>';
        return;
      }

      // Cargar permisos específicos del usuario si no los tiene (esto asume que /api/admin/features/list.php devuelve access)
      // Si el endpoint original de users no devuelve access, lo buscamos o esperamos que el repo lo traiga
      
      container.innerHTML = features.map(f => {
        const key = `${type}:${f.feature_slug}`;
        // Buscamos si el usuario tiene acceso habilitado en su mapa de acceso
        // NOTA: Necesitamos asegurarnos de que allUsers traiga el mapa de 'access'
        const isEnabled = isSuperadmin || (selectedUser.access && selectedUser.access[key] === true);
        const disabled = isSuperadmin ? 'disabled' : '';

        return `
          <div class="flex items-center justify-between gap-3 p-2 bg-white rounded-lg border border-slate-100">
            <div class="min-w-0">
              <div class="text-xs font-semibold text-slate-800 truncate">${escapeHtml(f.name)}</div>
            </div>
            <label class="toggle-switch transform scale-75 origin-right">
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

    window.togglePermission = async function(checkbox) {
      if (!selectedUser) return;
      
      const type = checkbox.dataset.type;
      const slug = checkbox.dataset.slug;
      const enabled = checkbox.checked;
      
      showSaveToast();
      checkbox.disabled = true;
      
      try {
        await api('/api/admin/features/update.php', {
          method: 'POST',
          body: {
            user_id: selectedUser.id,
            feature_type: type,
            feature_slug: slug,
            enabled: enabled
          }
        });
        
        const key = `${type}:${slug}`;
        if (!selectedUser.access) selectedUser.access = {};
        selectedUser.access[key] = enabled;
        
        hideSaveToast(true);
      } catch (err) {
        checkbox.checked = !enabled;
        hideSaveToast(false, err.message);
      } finally {
        checkbox.disabled = false;
      }
    };

    window.toggleAllOfType = async function(type, enable) {
      if (!selectedUser || selectedUser.is_superadmin) return;
      
      showSaveToast();
      const checkboxes = document.querySelectorAll(`#permissions-modal input[data-type="${type}"]`);
      checkboxes.forEach(cb => cb.disabled = true);
      
      try {
        const result = await api('/api/admin/features/bulk-update.php', {
          method: 'POST',
          body: {
            user_id: selectedUser.id,
            feature_type: type,
            action: enable ? 'enable_all' : 'disable_all'
          }
        });
        
        selectedUser.access = result.access || {};
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
    };

    function showSaveToast() {
      const toast = document.getElementById('save-toast');
      toast.innerHTML = `<div class="h-5 w-5 animate-spin rounded-full border-2 border-white border-r-transparent"></div><span class="text-sm font-medium">Guardando...</span>`;
      toast.classList.remove('hidden', 'bg-green-600', 'bg-red-600');
      toast.classList.add('bg-slate-800');
    }

    function hideSaveToast(success, message = '') {
      const toast = document.getElementById('save-toast');
      if (success) {
        toast.innerHTML = `<i class="iconoir-check text-lg"></i><span class="text-sm font-medium">Guardado</span>`;
        toast.classList.replace('bg-slate-800', 'bg-green-600');
      } else {
        toast.innerHTML = `<i class="iconoir-warning-circle text-lg"></i><span class="text-sm font-medium">Error: ${escapeHtml(message)}</span>`;
        toast.classList.replace('bg-slate-800', 'bg-red-600');
      }
      setTimeout(() => toast.classList.add('hidden'), 2000);
    }

    // Modales
    [document.getElementById('close-permissions-btn'), document.getElementById('close-perm-modal-btn')].forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('permissions-modal').classList.add('hidden');
        selectedUser = null;
      });
    });

    // Cargar datos al inicio
    init();
  </script>
  
  <!-- Bottom Navigation (móvil) -->
  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>

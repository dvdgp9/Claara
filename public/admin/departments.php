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

if (empty($user['is_superadmin']) && !in_array('admin', $user['roles'] ?? [], true)) {
    header('Location: /');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = '';
$pageTitle = 'Departments';
$headerTitle = 'Departments';
$headerSubtitle = 'Internal areas management';
$headerIcon = 'iconoir-community';
$headerBackUrl = '/admin/users.php';
$headerBackText = 'Usuarios';
?><!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../includes/head.php'; ?>
<body class="bg-slate-50 text-slate-900 overflow-hidden">
  <div class="min-h-screen flex h-screen">
    <?php include __DIR__ . '/../includes/left-tabs.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">
      <?php include __DIR__ . '/../includes/header-unified.php'; ?>

      <div class="flex-1 overflow-auto bg-slate-50 pb-16 lg:pb-0">
        <div class="max-w-6xl mx-auto p-4 lg:p-6">
          <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mt-4 lg:mt-6 mb-6">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 mb-2">Administration</p>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Departments</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">Create and maintain areas assigned to users.</p>
            </div>
            <button id="new-department-btn" class="w-full sm:w-auto px-4 py-2 bg-gradient-to-r from-[#B7C9F2] to-[#2F3440] text-white rounded-lg font-medium hover:opacity-90 hover:shadow-lg transition-all flex items-center justify-center gap-2 shadow-md">
              <i class="iconoir-plus-circle"></i>
              <span>New department</span>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
              <div class="px-4 lg:px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <h2 class="font-semibold text-slate-800">Listado</h2>
                  <p id="departments-count" class="text-xs text-slate-500 mt-0.5">Loading departments...</p>
                </div>
                <div class="flex items-center gap-2">
                  <div class="relative">
                    <i class="iconoir-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="search-input" placeholder="Buscar..." class="w-full sm:w-56 pl-9 pr-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors text-sm">
                  </div>
                  <button id="refresh-departments-btn" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-colors" title="Refresh">
                    <i class="iconoir-refresh"></i>
                  </button>
                </div>
              </div>

              <div id="departments-loading" class="text-center py-12">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#B7C9F2] border-r-transparent"></div>
                <p class="text-sm text-slate-500 mt-3">Loading departments...</p>
              </div>

              <div id="departments-empty" class="hidden text-center py-12 px-4">
                <i class="iconoir-empty-page text-4xl text-slate-300"></i>
                <p class="text-slate-500 mt-3">There are no departments yet.</p>
              </div>

              <div id="departments-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full min-w-[680px]">
                  <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Departamento</th>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Slug</th>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Usuarios</th>
                      <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="departments-list" class="divide-y divide-slate-200"></tbody>
                </table>
              </div>
            </section>

            <aside class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-fit">
              <div class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-700 flex items-center justify-center mb-4">
                <i class="iconoir-info-circle text-xl"></i>
              </div>
              <h2 class="font-semibold text-slate-800 mb-2">Usage in users</h2>
              <p class="text-sm text-slate-600 leading-relaxed">These departments appear in the user create and edit selector. If you delete one, related users will be left without a department due to database relation.</p>
              <a href="/admin/users.php" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-cyan-700 hover:text-cyan-900">
                <i class="iconoir-arrow-right"></i>
                Manage users
              </a>
            </aside>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="department-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 id="modal-title" class="text-lg font-semibold text-slate-800">New department</h3>
        <button id="close-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="department-form" class="space-y-4">
        <input type="hidden" id="department-id">
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Name *</label>
          <input type="text" id="department-name" maxlength="120" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" placeholder="Operaciones" required>
          <p class="text-xs text-slate-500 mt-1">Slug is generated automatically to keep consistency.</p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4">
          <button type="button" id="cancel-modal-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">Cancel</button>
          <button type="submit" id="save-department-btn" class="px-5 py-2 bg-slate-900 text-white rounded-lg font-medium hover:bg-slate-800 transition-colors text-sm">Save department</button>
        </div>
      </form>
    </div>
  </div>

  <div id="save-toast" class="hidden fixed bottom-6 right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 z-[100]">
    <i class="iconoir-check text-lg"></i>
    <span class="text-sm font-medium">Guardado</span>
  </div>

  <script>
    let departments = [];
    let editingDepartment = null;

    async function api(path, opts = {}) {
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...(window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {})
        },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[c]));
    }

    function filteredDepartments() {
      const term = document.getElementById('search-input').value.trim().toLowerCase();
      if (!term) return departments;
      return departments.filter(department =>
        String(department.name || '').toLowerCase().includes(term) ||
        String(department.slug || '').toLowerCase().includes(term)
      );
    }

    function renderDepartments() {
      const loading = document.getElementById('departments-loading');
      const empty = document.getElementById('departments-empty');
      const tableWrap = document.getElementById('departments-table-wrap');
      const list = document.getElementById('departments-list');
      const count = document.getElementById('departments-count');
      const visibleDepartments = filteredDepartments();

      loading.classList.add('hidden');
      count.textContent = `${departments.length} department${departments.length === 1 ? '' : 's'} configured`;

      if (visibleDepartments.length === 0) {
        empty.classList.remove('hidden');
        tableWrap.classList.add('hidden');
        list.innerHTML = '';
        return;
      }

      empty.classList.add('hidden');
      tableWrap.classList.remove('hidden');
      list.innerHTML = visibleDepartments.map(department => `
        <tr>
          <td class="px-5 py-4">
            <div class="font-semibold text-slate-800">${escapeHtml(department.name)}</div>
            <div class="text-xs text-slate-400">ID ${escapeHtml(department.id)}</div>
          </td>
          <td class="px-5 py-4">
            <code class="text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded-md">${escapeHtml(department.slug)}</code>
          </td>
          <td class="px-5 py-4">
            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-600">
              <i class="iconoir-user"></i>
              ${escapeHtml(department.user_count || 0)}
            </span>
          </td>
          <td class="px-5 py-4">
            <div class="flex items-center justify-end gap-1">
              <button type="button" class="edit-department-btn p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-colors" data-id="${escapeHtml(department.id)}" title="Edit">
                <i class="iconoir-edit-pencil"></i>
              </button>
              <button type="button" class="delete-department-btn p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-id="${escapeHtml(department.id)}" title="Delete">
                <i class="iconoir-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    async function loadDepartments() {
      document.getElementById('departments-loading').classList.remove('hidden');
      document.getElementById('departments-empty').classList.add('hidden');
      document.getElementById('departments-table-wrap').classList.add('hidden');
      const data = await api('/api/admin/departments/list.php');
      departments = data.departments || [];
      renderDepartments();
    }

    function openModal(department = null) {
      editingDepartment = department;
      document.getElementById('modal-title').textContent = department ? 'Edit department' : 'New department';
      document.getElementById('department-id').value = department?.id || '';
      document.getElementById('department-name').value = department?.name || '';
      document.getElementById('department-modal').classList.remove('hidden');
      document.getElementById('department-name').focus();
    }

    function closeModal() {
      document.getElementById('department-modal').classList.add('hidden');
      editingDepartment = null;
      document.getElementById('department-form').reset();
    }

    function showToast(message, isError = false) {
      const toast = document.getElementById('save-toast');
      toast.innerHTML = `<i class="${isError ? 'iconoir-warning-circle' : 'iconoir-check'} text-lg"></i><span class="text-sm font-medium">${escapeHtml(message)}</span>`;
      toast.classList.remove('hidden', 'bg-slate-800', 'bg-green-600', 'bg-red-600');
      toast.classList.add(isError ? 'bg-red-600' : 'bg-green-600');
      setTimeout(() => toast.classList.add('hidden'), 2600);
    }

    async function saveDepartment(event) {
      event.preventDefault();
      const payload = { name: document.getElementById('department-name').value.trim() };

      if (!payload.name) {
        showToast('Enter a department name.', true);
        return;
      }

      const saveBtn = document.getElementById('save-department-btn');
      saveBtn.disabled = true;
      saveBtn.textContent = 'Guardando...';

      try {
        if (editingDepartment) {
          await api('/api/admin/departments/update.php', {
            method: 'POST',
            body: { id: editingDepartment.id, ...payload }
          });
        } else {
          await api('/api/admin/departments/create.php', {
            method: 'POST',
            body: payload
          });
        }
        closeModal();
        await loadDepartments();
        showToast('Departamento guardado');
      } catch (error) {
        showToast(error.message || 'Could not save department.', true);
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save department';
      }
    }

    async function deleteDepartment(id) {
      const department = departments.find(item => Number(item.id) === Number(id));
      if (!department) return;
      const users = Number(department.user_count || 0);
      const detail = users > 0
        ? ` ${users} user${users === 1 ? '' : 's'} will be left without a department.`
        : '';
      if (!window.confirm(`Delete "${department.name}"?${detail}`)) return;

      try {
        await api('/api/admin/departments/delete.php', {
          method: 'POST',
          body: { id: department.id }
        });
        await loadDepartments();
        showToast('Departamento eliminado');
      } catch (error) {
        showToast(error.message || 'Could not delete department.', true);
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('new-department-btn').addEventListener('click', () => openModal());
      document.getElementById('refresh-departments-btn').addEventListener('click', loadDepartments);
      document.getElementById('search-input').addEventListener('input', renderDepartments);
      document.getElementById('department-form').addEventListener('submit', saveDepartment);
      document.getElementById('close-modal-btn').addEventListener('click', closeModal);
      document.getElementById('cancel-modal-btn').addEventListener('click', closeModal);
      document.getElementById('department-modal').addEventListener('click', event => {
        if (event.target.id === 'department-modal') closeModal();
      });

      document.getElementById('departments-list').addEventListener('click', event => {
        const editBtn = event.target.closest('.edit-department-btn');
        if (editBtn) {
          const department = departments.find(item => Number(item.id) === Number(editBtn.dataset.id));
          openModal(department);
          return;
        }

        const deleteBtn = event.target.closest('.delete-department-btn');
        if (deleteBtn) {
          deleteDepartment(deleteBtn.dataset.id);
        }
      });

      loadDepartments().catch(error => {
        document.getElementById('departments-loading').classList.add('hidden');
        showToast(error.message || 'Could not load departments.', true);
      });
    });
  </script>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>

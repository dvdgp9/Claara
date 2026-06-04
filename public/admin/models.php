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

if (empty($user['is_superadmin'])) {
    header('Location: /app/');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
$activeTab = '';
$pageTitle = 'Available Models';
$headerTitle = 'Available Models';
$headerSubtitle = 'Catalog visible in the chat selector';
$headerIcon = 'iconoir-settings';
$headerBackUrl = '/app/';
$headerBackText = 'Chat';
?><!DOCTYPE html>
<html lang="en">
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
              <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 mb-2">Superadmin</p>
              <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Available Models</h1>
              <p class="text-slate-600 text-sm lg:text-base mt-1">Enable, reorder, and edit the models shown in the chat selector.</p>
            </div>
            <button id="new-model-btn" class="w-full sm:w-auto px-4 py-2 bg-gradient-to-r from-[#B7C9F2] to-[#2F3440] text-white rounded-lg font-medium hover:opacity-90 hover:shadow-lg transition-all flex items-center justify-center gap-2 shadow-md">
              <i class="iconoir-plus-circle"></i>
              <span>New model</span>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
              <div class="px-4 lg:px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div>
                  <h2 class="font-semibold text-slate-800">Catalog</h2>
                  <p id="models-count" class="text-xs text-slate-500 mt-0.5">Loading models...</p>
                </div>
                <button id="refresh-models-btn" class="p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-colors" title="Refresh">
                  <i class="iconoir-refresh"></i>
                </button>
              </div>

              <div id="models-loading" class="text-center py-12">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-[#B7C9F2] border-r-transparent"></div>
                <p class="text-sm text-slate-500 mt-3">Loading models...</p>
              </div>

              <div id="models-empty" class="hidden text-center py-12 px-4">
                <i class="iconoir-empty-page text-4xl text-slate-300"></i>
                <p class="text-slate-500 mt-3">There are no models in the catalog yet.</p>
              </div>

              <div id="models-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full min-w-[780px]">
                  <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Model</th>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">OpenRouter Key</th>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Order</th>
                      <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                      <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="models-list" class="divide-y divide-slate-200"></tbody>
                </table>
              </div>
            </section>

            <aside class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-fit">
              <div class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-700 flex items-center justify-center mb-4">
                <i class="iconoir-info-circle text-xl"></i>
              </div>
              <h2 class="font-semibold text-slate-800 mb-2">How it works</h2>
              <p class="text-sm text-slate-600 leading-relaxed">Active models appear in the chat selector for superadmins, ordered by the Order field.</p>
              <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Example Key</p>
                <code class="text-xs text-slate-700 break-all">google/gemini-3-flash-preview</code>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="model-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 id="modal-title" class="text-lg font-semibold text-slate-800">New model</h3>
        <button id="close-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
          <i class="iconoir-xmark text-xl"></i>
        </button>
      </div>

      <form id="model-form" class="space-y-4">
        <input type="hidden" id="model-id">
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Display name *</label>
          <input type="text" id="model-label" maxlength="120" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" placeholder="Gemini 3 Flash" required>
        </div>
        <div>
          <label class="text-sm font-medium text-slate-700 block mb-2">Model key *</label>
          <input type="text" id="model-key" maxlength="120" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors font-mono text-sm" placeholder="provider/model" required>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-slate-700 block mb-2">Order</label>
            <input type="number" id="model-sort" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#B7C9F2] focus:ring-2 focus:ring-[#B7C9F2]/20 transition-colors" value="10">
          </div>
          <label class="flex items-center gap-3 mt-0 sm:mt-8">
            <input type="checkbox" id="model-active" class="h-4 w-4 rounded border-slate-300 text-[#B7C9F2] focus:ring-[#B7C9F2]" checked>
            <span class="text-sm font-medium text-slate-700">Active in chat</span>
          </label>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4">
          <button type="button" id="cancel-modal-btn" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors text-sm">Cancel</button>
          <button type="submit" id="save-model-btn" class="px-5 py-2 bg-slate-900 text-white rounded-lg font-medium hover:bg-slate-800 transition-colors text-sm">Save model</button>
        </div>
      </form>
    </div>
  </div>

  <div id="save-toast" class="hidden fixed bottom-6 right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 z-[100]">
    <i class="iconoir-check text-lg"></i>
    <span class="text-sm font-medium">Saved</span>
  </div>

  <script>
    let models = [];
    let editingModel = null;

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

    function renderModels() {
      const loading = document.getElementById('models-loading');
      const empty = document.getElementById('models-empty');
      const tableWrap = document.getElementById('models-table-wrap');
      const list = document.getElementById('models-list');
      const count = document.getElementById('models-count');

      loading.classList.add('hidden');
      count.textContent = `${models.length} model${models.length === 1 ? '' : 's'} configured`;

      if (models.length === 0) {
        empty.classList.remove('hidden');
        tableWrap.classList.add('hidden');
        list.innerHTML = '';
        return;
      }

      empty.classList.add('hidden');
      tableWrap.classList.remove('hidden');
      list.innerHTML = models.map(model => `
        <tr>
          <td class="px-5 py-4">
            <div class="font-semibold text-slate-800">${escapeHtml(model.label)}</div>
            <div class="text-xs text-slate-400">ID ${escapeHtml(model.id)}</div>
          </td>
          <td class="px-5 py-4">
            <code class="text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded-md">${escapeHtml(model.model_key)}</code>
          </td>
          <td class="px-5 py-4 text-sm text-slate-600">${escapeHtml(model.sort_order)}</td>
          <td class="px-5 py-4">
            ${Number(model.is_active) === 1
              ? '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active</span>'
              : '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-500"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Hidden</span>'}
          </td>
          <td class="px-5 py-4">
            <div class="flex items-center justify-end gap-1">
              <button type="button" class="edit-model-btn p-2 text-slate-400 hover:text-[#B7C9F2] hover:bg-cyan-50 rounded-lg transition-colors" data-id="${escapeHtml(model.id)}" title="Edit">
                <i class="iconoir-edit-pencil"></i>
              </button>
              <button type="button" class="delete-model-btn p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-id="${escapeHtml(model.id)}" title="Delete">
                <i class="iconoir-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    async function loadModels() {
      document.getElementById('models-loading').classList.remove('hidden');
      document.getElementById('models-empty').classList.add('hidden');
      document.getElementById('models-table-wrap').classList.add('hidden');
      const data = await api('/api/admin/models/list.php');
      models = data.models || [];
      renderModels();
    }

    function openModal(model = null) {
      editingModel = model;
      document.getElementById('modal-title').textContent = model ? 'Edit model' : 'New model';
      document.getElementById('model-id').value = model?.id || '';
      document.getElementById('model-label').value = model?.label || '';
      document.getElementById('model-key').value = model?.model_key || '';
      document.getElementById('model-sort').value = model?.sort_order ?? nextSortOrder();
      document.getElementById('model-active').checked = model ? Number(model.is_active) === 1 : true;
      document.getElementById('model-modal').classList.remove('hidden');
      document.getElementById('model-label').focus();
    }

    function closeModal() {
      document.getElementById('model-modal').classList.add('hidden');
      editingModel = null;
      document.getElementById('model-form').reset();
    }

    function nextSortOrder() {
      const maxSort = models.reduce((max, model) => Math.max(max, Number(model.sort_order) || 0), 0);
      return maxSort + 10;
    }

    function showToast(message, isError = false) {
      const toast = document.getElementById('save-toast');
      toast.innerHTML = `<i class="${isError ? 'iconoir-warning-circle' : 'iconoir-check'} text-lg"></i><span class="text-sm font-medium">${escapeHtml(message)}</span>`;
      toast.classList.remove('hidden', 'bg-slate-800', 'bg-green-600', 'bg-red-600');
      toast.classList.add(isError ? 'bg-red-600' : 'bg-green-600');
      setTimeout(() => toast.classList.add('hidden'), 2600);
    }

    async function saveModel(event) {
      event.preventDefault();
      const payload = {
        model_key: document.getElementById('model-key').value.trim(),
        label: document.getElementById('model-label').value.trim(),
        is_active: document.getElementById('model-active').checked ? 1 : 0,
        sort_order: parseInt(document.getElementById('model-sort').value || '0', 10)
      };

      if (!payload.model_key || !payload.label) {
        showToast('Completa los campos obligatorios.', true);
        return;
      }

      const saveBtn = document.getElementById('save-model-btn');
      saveBtn.disabled = true;
      saveBtn.textContent = 'Guardando...';

      try {
        if (editingModel) {
          await api('/api/admin/models/update.php', {
            method: 'POST',
            body: { id: editingModel.id, ...payload }
          });
        } else {
          await api('/api/admin/models/create.php', {
            method: 'POST',
            body: payload
          });
        }
        closeModal();
        await loadModels();
        showToast('Modelo guardado');
      } catch (error) {
        showToast(error.message || 'Could not save model.', true);
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save model';
      }
    }

    async function deleteModel(id) {
      const model = models.find(item => Number(item.id) === Number(id));
      if (!model) return;
      if (!window.confirm(`Delete "${model.label}" from the catalog?`)) return;

      try {
        await api('/api/admin/models/delete.php', {
          method: 'POST',
          body: { id: model.id }
        });
        await loadModels();
        showToast('Modelo eliminado');
      } catch (error) {
        showToast(error.message || 'Could not delete model.', true);
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('new-model-btn').addEventListener('click', () => openModal());
      document.getElementById('refresh-models-btn').addEventListener('click', loadModels);
      document.getElementById('model-form').addEventListener('submit', saveModel);
      document.getElementById('close-modal-btn').addEventListener('click', closeModal);
      document.getElementById('cancel-modal-btn').addEventListener('click', closeModal);
      document.getElementById('model-modal').addEventListener('click', event => {
        if (event.target.id === 'model-modal') closeModal();
      });

      document.getElementById('models-list').addEventListener('click', event => {
        const editBtn = event.target.closest('.edit-model-btn');
        if (editBtn) {
          const model = models.find(item => Number(item.id) === Number(editBtn.dataset.id));
          openModal(model);
          return;
        }

        const deleteBtn = event.target.closest('.delete-model-btn');
        if (deleteBtn) {
          deleteModel(deleteBtn.dataset.id);
        }
      });

      loadModels().catch(error => {
        document.getElementById('models-loading').classList.add('hidden');
        showToast(error.message || 'Could not load models.', true);
      });
    });
  </script>

  <?php include __DIR__ . '/../includes/bottom-nav.php'; ?>
</body>
</html>

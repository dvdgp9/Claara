(function () {
  const els = {
    count: document.getElementById('admin-connectors-count'),
    loading: document.getElementById('admin-connectors-loading'),
    error: document.getElementById('admin-connectors-error'),
    list: document.getElementById('admin-connectors-list'),
    refresh: document.getElementById('refresh-admin-connectors-btn'),
    connected: document.getElementById('admin-connected-count'),
    imported: document.getElementById('admin-imported-count'),
    errors: document.getElementById('admin-error-count'),
  };

  document.addEventListener('DOMContentLoaded', () => {
    els.refresh?.addEventListener('click', loadSummary);
    loadSummary();
  });

  async function loadSummary() {
    setState('loading');
    try {
      const response = await fetch('/api/admin/connectors/summary.php', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error?.message || 'Could not load connector summary');
      }
      renderSummary(data.providers || []);
      setState('ready');
    } catch (error) {
      console.error(error);
      setState('error');
    }
  }

  function renderSummary(providers) {
    const totals = providers.reduce((acc, row) => {
      acc.connected += Number(row.connected_accounts || 0);
      acc.imported += Number(row.imported_items || 0);
      acc.errors += Number(row.error_accounts || 0);
      return acc;
    }, { connected: 0, imported: 0, errors: 0 });

    els.connected.textContent = String(totals.connected);
    els.imported.textContent = String(totals.imported);
    els.errors.textContent = String(totals.errors);
    els.count.textContent = `${providers.length} provider${providers.length === 1 ? '' : 's'} tracked`;
    els.list.innerHTML = providers.map(providerRow).join('');
  }

  function providerRow(provider, index) {
    const enabled = Number(provider.is_enabled) === 1;
    return `
      <article class="connectors-admin-row" style="--index:${index}">
        <div class="connectors-admin-provider">
          <div class="connectors-provider-icon">
            <i class="${escapeAttr(provider.icon || 'iconoir-cloud')}"></i>
          </div>
          <div>
            <h3>${escapeHtml(provider.display_name || provider.provider_key)}</h3>
            <p>${escapeHtml(provider.description || '')}</p>
          </div>
        </div>
        <div class="connectors-admin-stat">
          <strong>${Number(provider.connected_accounts || 0)}</strong>
          <span>Accounts</span>
        </div>
        <div class="connectors-admin-stat">
          <strong>${Number(provider.selected_items || 0)}</strong>
          <span>Selected</span>
        </div>
        <div class="connectors-admin-stat">
          <strong>${Number(provider.imported_items || 0)}</strong>
          <span>Imported</span>
        </div>
        <span class="connectors-status ${enabled ? 'connectors-status-connected' : 'connectors-status-planned'}">
          ${enabled ? 'Enabled' : 'Planned'}
        </span>
      </article>
    `;
  }

  function setState(state) {
    els.loading.classList.toggle('hidden', state !== 'loading');
    els.error.classList.toggle('hidden', state !== 'error');
    els.list.classList.toggle('hidden', state !== 'ready');
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
  }
})();


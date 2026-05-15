(function () {
  const els = {
    count: document.getElementById('connectors-count'),
    loading: document.getElementById('connectors-loading'),
    error: document.getElementById('connectors-error'),
    list: document.getElementById('connectors-list'),
    refresh: document.getElementById('refresh-connectors-btn'),
  };

  document.addEventListener('DOMContentLoaded', () => {
    els.refresh?.addEventListener('click', loadConnectors);
    loadConnectors();
  });

  async function loadConnectors() {
    setState('loading');
    try {
      const response = await fetch('/api/connectors/providers.php', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error?.message || 'Could not load connectors');
      }
      renderProviders(data.providers || []);
      setState('ready');
    } catch (error) {
      console.error(error);
      setState('error');
    }
  }

  function renderProviders(providers) {
    els.count.textContent = `${providers.length} source${providers.length === 1 ? '' : 's'} configured`;
    els.list.innerHTML = providers.map(providerCard).join('');
  }

  function providerCard(provider, index) {
    const enabled = Number(provider.is_enabled) === 1;
    const connected = Boolean(provider.account_id);
    const status = connected ? provider.account_status : (enabled ? 'not_connected' : 'planned');
    const actionLabel = enabled ? (connected ? 'Manage' : 'Connect') : 'Planned';
    const actionDisabled = !enabled || !connected;
    const subtitle = connected
      ? `${provider.external_email || provider.account_display_name || 'Connected account'}`
      : (enabled ? 'Ready for selected-file access' : 'Not enabled yet');

    return `
      <article class="connectors-provider-card" style="--index:${index}">
        <div class="connectors-provider-topline">
          <div class="connectors-provider-icon">
            <i class="${escapeAttr(provider.icon || 'iconoir-cloud')}"></i>
          </div>
          ${statusPill(status)}
        </div>
        <div class="connectors-provider-body">
          <h3>${escapeHtml(provider.display_name || provider.provider_key)}</h3>
          <p>${escapeHtml(provider.description || '')}</p>
          <span>${escapeHtml(subtitle)}</span>
        </div>
        <div class="connectors-provider-meta">
          <div>
            <strong>${Number(provider.item_count || 0)}</strong>
            <span>Selected</span>
          </div>
          <div>
            <strong>${Number(provider.imported_count || 0)}</strong>
            <span>Imported</span>
          </div>
        </div>
        <button class="connectors-primary-btn" ${actionDisabled ? 'disabled' : ''}>
          <i class="${connected ? 'iconoir-settings' : 'iconoir-log-in'}"></i>
          <span>${actionLabel}</span>
        </button>
      </article>
    `;
  }

  function statusPill(status) {
    const labels = {
      connected: 'Connected',
      error: 'Error',
      needs_attention: 'Needs attention',
      not_connected: 'Available',
      planned: 'Planned',
    };
    return `<span class="connectors-status connectors-status-${escapeAttr(status)}">${escapeHtml(labels[status] || status)}</span>`;
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


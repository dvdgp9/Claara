(function () {
  const els = {
    count: document.getElementById('connectors-count'),
    loading: document.getElementById('connectors-loading'),
    error: document.getElementById('connectors-error'),
    list: document.getElementById('connectors-list'),
    refresh: document.getElementById('refresh-connectors-btn'),
    notice: document.getElementById('connectors-notice'),
  };

  const START_URLS = {
    google_drive: '/api/connectors/google/start.php',
    onedrive: '/api/connectors/onedrive/start.php',
  };
  const DISCONNECT_URLS = {
    google_drive: '/api/connectors/google/disconnect.php',
    onedrive: '/api/connectors/onedrive/disconnect.php',
  };

  document.addEventListener('DOMContentLoaded', () => {
    els.refresh?.addEventListener('click', loadConnectors);
    els.list?.addEventListener('click', onProviderAction);
    showCallbackNotice();
    loadConnectors();
  });

  function showCallbackNotice() {
    const params = new URLSearchParams(window.location.search);
    const result = params.get('connect');
    if (!result) return;
    const detail = params.get('detail') || '';
    let message;
    let kind = 'error';
    if (result === 'success') {
      message = 'Google Drive connected successfully.';
      kind = 'success';
    } else if (result === 'cancelled') {
      message = 'Connection cancelled.';
    } else if (detail.includes('not granted')) {
      message = 'Google Drive was not connected: please tick the Drive files checkbox on the Google consent screen and try again.';
    } else {
      message = 'Could not complete the connection. Please try again.';
    }
    showNotice(message, kind);
    window.history.replaceState({}, '', '/connectors.php');
  }

  function showNotice(message, kind) {
    if (!els.notice) return;
    els.notice.textContent = message;
    els.notice.className = `connectors-notice connectors-notice-${kind}`;
    if (kind === 'success') {
      setTimeout(() => els.notice.classList.add('hidden'), 6000);
    }
  }

  async function onProviderAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const providerKey = button.dataset.provider;

    if (button.dataset.action === 'connect' && START_URLS[providerKey]) {
      window.location.href = START_URLS[providerKey];
      return;
    }

    if (button.dataset.action === 'disconnect' && DISCONNECT_URLS[providerKey]) {
      if (!window.confirm('Disconnect this account? Files already imported stay in Claara.')) return;
      button.disabled = true;
      try {
        const response = await fetch(DISCONNECT_URLS[providerKey], {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || '',
          },
          body: JSON.stringify({ account_id: Number(button.dataset.accountId) }),
        });
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.error?.message || 'Could not disconnect');
        }
        showNotice('Account disconnected.', 'success');
        loadConnectors();
      } catch (error) {
        console.error(error);
        button.disabled = false;
        showNotice('Could not disconnect the account. Please try again.', 'error');
      }
    }
  }

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
    const hasFlow = Boolean(START_URLS[provider.provider_key]);
    const needsReconnect = connected && (status === 'error' || status === 'needs_attention');
    const actionLabel = !enabled ? 'Planned' : (needsReconnect ? 'Reconnect' : (connected ? 'Disconnect' : 'Connect'));
    const actionDisabled = !enabled || !hasFlow;
    const action = connected && !needsReconnect ? 'disconnect' : 'connect';
    const subtitle = needsReconnect
      ? (provider.last_error_message || 'This account needs to be reconnected.')
      : connected
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
        <button class="connectors-primary-btn" ${actionDisabled ? 'disabled' : ''}
          data-action="${action}"
          data-provider="${escapeAttr(provider.provider_key)}"
          data-account-id="${Number(provider.account_id || 0)}">
          <i class="${action === 'disconnect' ? 'iconoir-log-out' : 'iconoir-log-in'}"></i>
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


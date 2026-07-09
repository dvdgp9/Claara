/**
 * ClaaraDrivePicker — shared Google Drive picker used by chat and Voice Studio.
 *
 * Usage:
 *   const opened = await ClaaraDrivePicker.open({
 *     mimeTypes: ['application/pdf', ...],
 *     title: 'Choose a file from Google Drive',
 *     onPicked: (docs) => { ... }   // array of picked Drive documents
 *   });
 *
 * Handles: short-lived token fetch (refresh token never reaches the browser),
 * the "not connected yet" prompt, lazy loading of Google's picker library,
 * and auto-resume after the user connects their account in another tab.
 */
window.ClaaraDrivePicker = (function () {
  let pickerLibPromise = null;
  let pendingOpenArgs = null;
  let promptEl = null;

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      script.onerror = () => reject(new Error('Could not load Google Drive. Check your connection and try again.'));
      document.head.appendChild(script);
    });
  }

  function ensurePickerLib() {
    if (!pickerLibPromise) {
      pickerLibPromise = loadScript('https://apis.google.com/js/api.js')
        .then(() => new Promise((resolve) => window.gapi.load('picker', resolve)));
      pickerLibPromise.catch(() => { pickerLibPromise = null; });
    }
    return pickerLibPromise;
  }

  async function fetchToken() {
    const response = await fetch('/api/connectors/google/picker-token.php', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 409 && data.error?.code === 'not_connected') {
      return { notConnected: true };
    }
    if (!response.ok) {
      throw new Error(data.error?.message || 'Could not prepare Google Drive');
    }
    return data;
  }

  function buildPrompt() {
    if (promptEl) return promptEl;
    promptEl = document.createElement('div');
    promptEl.className = 'hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[95] flex items-center justify-center p-4';
    promptEl.innerHTML = `
      <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#B7C9F2]/15 flex items-center justify-center mx-auto mb-5">
          <i class="iconoir-google-drive text-3xl text-[#2F3440]"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900 mb-2">Connect Google Drive</h3>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed">Connect your account once and you will be able to pick Drive files without leaving Claara.</p>
        <a href="/connectors.php" target="_blank" rel="noopener"
           class="block w-full py-3 px-6 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-smooth mb-2">
          Connect Google Drive
        </a>
        <button type="button" data-drive-prompt-close
                class="w-full py-2.5 px-6 rounded-xl text-slate-500 font-medium hover:bg-slate-100 transition-smooth">
          Not now
        </button>
        <p class="hidden mt-3 text-xs text-[#2F3440] font-medium" data-drive-prompt-waiting>
          Waiting for the connection... come back to this tab when you are done.
        </p>
      </div>`;
    document.body.appendChild(promptEl);

    promptEl.addEventListener('click', (event) => {
      if (event.target === promptEl || event.target.closest('[data-drive-prompt-close]')) {
        hidePrompt();
      }
      if (event.target.closest('a[href="/connectors.php"]')) {
        promptEl.querySelector('[data-drive-prompt-waiting]').classList.remove('hidden');
      }
    });

    // When the user comes back after connecting in the other tab, resume
    // straight into the picker they originally asked for.
    window.addEventListener('focus', async () => {
      if (promptEl.classList.contains('hidden') || !pendingOpenArgs) return;
      try {
        const token = await fetchToken();
        if (!token.notConnected) {
          const args = pendingOpenArgs;
          hidePrompt();
          await showPicker(token, args);
        }
      } catch (e) { /* keep the prompt; the user can retry */ }
    });

    return promptEl;
  }

  function showPrompt() {
    buildPrompt().classList.remove('hidden');
  }

  function hidePrompt() {
    if (!promptEl) return;
    promptEl.classList.add('hidden');
    promptEl.querySelector('[data-drive-prompt-waiting]')?.classList.add('hidden');
    pendingOpenArgs = null;
  }

  async function showPicker(token, args) {
    await ensurePickerLib();
    const picker = window.google.picker;

    const view = new picker.DocsView(picker.ViewId.DOCS)
      .setIncludeFolders(true)
      .setSelectFolderEnabled(false);
    if (args.mimeTypes && args.mimeTypes.length) {
      view.setMimeTypes(args.mimeTypes.join(','));
    }

    new picker.PickerBuilder()
      .setOAuthToken(token.access_token)
      .setDeveloperKey(token.api_key)
      .setAppId(token.app_id)
      .setTitle(args.title || 'Choose a file from Google Drive')
      .addView(view)
      .setCallback((data) => {
        if (data[picker.Response.ACTION] === picker.Action.PICKED) {
          const docs = data[picker.Response.DOCUMENTS] || [];
          if (docs.length) args.onPicked(docs);
        }
      })
      .build()
      .setVisible(true);
  }

  /**
   * Opens the picker (or the connect prompt when no account is linked).
   * Resolves true when the picker was shown, false when the connect prompt
   * was shown instead. Rejects on configuration/network errors.
   */
  async function open(args) {
    const token = await fetchToken();
    if (token.notConnected) {
      pendingOpenArgs = args;
      showPrompt();
      return false;
    }
    await showPicker(token, args);
    return true;
  }

  return { open };
})();

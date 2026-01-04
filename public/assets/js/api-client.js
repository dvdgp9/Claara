window.api = async function(path, opts = {}) {
  let currentCsrf = typeof window.CSRF_TOKEN !== 'undefined' ? window.CSRF_TOKEN : (typeof csrf !== 'undefined' ? csrf : null);
  
  const res = await fetch(path, {
    method: opts.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(currentCsrf ? { 'X-CSRF-Token': currentCsrf } : {})
    },
    body: opts.body ? JSON.stringify(opts.body) : undefined,
    credentials: 'include'
  });
  
  const data = await res.json().catch(() => ({}));

  if (res.status === 403 && data?.error?.code === 'csrf_invalid' && !opts._retry) {
    try {
      const meRes = await fetch('/api/auth/me.php', { credentials: 'include' });
      if (meRes.ok) {
        const meData = await meRes.json();
        const newCsrf = meData.csrf_token || null;
        if (newCsrf) {
          if (typeof window.CSRF_TOKEN !== 'undefined') window.CSRF_TOKEN = newCsrf;
          if (typeof csrf !== 'undefined') csrf = newCsrf;
          return window.api(path, { ...opts, _retry: true });
        }
      }
    } catch (e) {
      console.error('Error refrescando CSRF:', e);
    }
  }

  if (!res.ok) {
    if (res.status === 401) {
      window.location.href = '/login.php';
      return;
    }
    throw new Error(data?.error?.message || res.statusText);
  }
  return data;
};

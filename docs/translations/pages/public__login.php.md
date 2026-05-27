# Page Text Extraction

- Source: `/public/login.php`
- Extracted strings: 10

| Line | Type | Text |
|---:|---|---|
| 16 | html_text | Claara — Login |
| 20 | html_text | @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body { font-family: 'Inter', system-ui, -apple-system, sans-serif; } .gradient-bg { background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%); } .btn-gradient { background: linear-gradient(90deg, #23AAC5 0%, #115c6c 100%); } input[type="text"], input[type="password"] { border: 2px solid #23AAC5; border-radius: 50px; padding: 12px 24px; transition: all 0.3s ease; } input[type="text"]:focus, input[type="password"]:focus { outline: none; border-color: #115c6c; box-shadow: 0 0 0 3px rgba(35, 170, 197, 0.1); } |
| 59 | html_text | Friendly AI assistance for focused, everyday work. |
| 73 | html_text | Log in |
| 78 | html_text | Username or email |
| 106 | html_text | Remember me for 30 days |
| 115 | html_text | Log in |
| 124 | html_text | const form = document.getElementById('login-form'); const email = document.getElementById('email'); const password = document.getElementById('password'); const errorEl = document.getElementById('error'); const submitBtn = document.getElementById('submit-btn'); const rememberEl = document.getElementById('remember'); async function api(path, opts={}){ const res = await fetch(path, { method: opts.method \|\| 'GET', headers: { 'Content-Type': 'application/json' }, body: opts.body ? JSON.stringify(opts.body) : undefined, credentials: 'include' }); const data = await res.json().catch(()=>({})); if(!res.ok) throw new Error(data?.error?.message \|\| res.statusText); return data; } form.addEventListener('submit', async (e)=>{ e.preventDefault(); errorEl.classList.add('hidden'); submitBtn.disabled = true; submitBtn.textContent = 'Logging in...'; try { await api('/api/auth/login.php', { method: 'POST', body: { email: email.value.trim(), password: password.value, remember: rememberEl.checked } }); window.location.href = '/'; } catch(err){ errorEl.textContent = err.message; errorEl.classList.remove('hidden'); } finally { submitBtn.disabled = false; submitBtn.textContent = 'Log in'; } }); // Focus the first field on load. email.focus(); |
| 148 | script_string | Logging in... |
| 165 | script_string | Log in |

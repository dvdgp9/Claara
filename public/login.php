<?php
require_once __DIR__ . '/../src/App/bootstrap.php';

use App\Session;

$user = Session::user();
if ($user) {
    header('Location: /app/');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Claara — Login</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/assets/images/isotipo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="min-h-screen claara-login-page flex">
  <!-- Lado izquierdo - Gradiente -->
  <div class="hidden lg:flex lg:w-1/2 claara-login-hero items-center justify-center p-12 relative">
    <div class="absolute top-12 left-12">
      <img src="/assets/images/claara-logo.png" alt="Claara" class="h-24 claara-logo-on-hero" />
    </div>
    
    <div class="text-slate-800 text-left max-w-md">
      <p class="claara-kicker mb-5">Clarity. Guidance. Answers.</p>
      <h2 class="text-4xl font-semibold leading-tight">
        Stop searching. <span class="text-[#FF8B73]">Ask Claara.</span>
      </h2>
    </div>
  </div>

  <!-- Lado derecho - Formulario -->
  <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <img src="/assets/images/claara-logo.png" alt="Claara" class="h-24 mx-auto" />
      </div>
      
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Log in</h1>
      </div>

      <form id="login-form" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-900 mb-2">Username or email</label>
          <input 
            id="email" 
            type="text" 
            class="w-full bg-white" 
            required 
            autocomplete="username"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-900 mb-2">Password</label>
          <input 
            id="password" 
            type="password" 
            class="w-full bg-white" 
            required
            autocomplete="current-password"
          />
        </div>

        <div class="flex items-center">
          <input 
            id="remember" 
            type="checkbox" 
            class="h-4 w-4 rounded border-gray-300 text-[#B7C9F2] focus:ring-[#B7C9F2]"
            checked
          />
          <label for="remember" class="ml-2 text-sm text-gray-700">
            Remember me for 30 days
          </label>
        </div>

        <button 
          type="submit" 
          id="submit-btn"
          class="w-full btn-gradient text-[#2F3440] font-semibold py-3 rounded-full hover:opacity-90 transition-all duration-200 shadow-md hover:shadow-lg"
        >
          Log in
        </button>

        <p id="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2 hidden text-center"></p>
      </form>
    </div>
  </div>

  <script type="module">
    const form = document.getElementById('login-form');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const errorEl = document.getElementById('error');
    const submitBtn = document.getElementById('submit-btn');
    const rememberEl = document.getElementById('remember');

    async function api(path, opts={}){
      const res = await fetch(path, {
        method: opts.method || 'GET',
        headers: { 'Content-Type': 'application/json' },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
        credentials: 'include'
      });
      const data = await res.json().catch(()=>({}));
      if(!res.ok) throw new Error(data?.error?.message || res.statusText);
      return data;
    }

    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      errorEl.classList.add('hidden');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Logging in...';
      
      try {
        await api('/api/auth/login.php', { 
          method: 'POST', 
          body: { 
            email: email.value.trim(), 
            password: password.value,
            remember: rememberEl.checked
          } 
        });
        window.location.href = '/app/';
      } catch(err){
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Log in';
      }
    });

    // Focus the first field on load.
    email.focus();
  </script>
</body>
</html>

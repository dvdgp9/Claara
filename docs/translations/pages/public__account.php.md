# Page Text Extraction

- Source: `/public/account.php`
- Extracted strings: 38

| Line | Type | Text |
|---:|---|---|
| 19 | html_text | My account — iaiaPRO |
| 24 | html_text | /* Base layout styles */ .gradient-brand { background: linear-gradient(135deg, #23AAC5 0%, #115c6c 100%); } ::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; } ::-webkit-scrollbar-thumb:hover { background: #94a3b8; } |
| 58 | html_text | Personal information |
| 59 | html_text | Manage your details and preferences |
| 71 | html_text | First name |
| 75 | html_text | Last name |
| 92 | html_text | First name |
| 96 | html_text | Last name |
| 110 | html_text | If you need to change your email or department, contact your workspace administrator. |
| 115 | html_text | Save changes |
| 134 | html_text | Change password |
| 142 | html_text | Recent activity |
| 146 | html_text | Loading stats... |
| 155 | html_text | Conversations created |
| 157 | html_text | this week · |
| 168 | html_text | Messages sent |
| 170 | html_text | this week · |
| 181 | html_text | Last login |
| 188 | php_string | First session |
| 200 | html_text | Account created |
| 214 | html_text | © 2025 iaiaPRO. All rights reserved. |
| 228 | html_text | Change password |
| 236 | html_text | Current password |
| 241 | html_text | New password |
| 243 | html_text | Minimum 8 characters |
| 247 | html_text | Confirm password |
| 255 | html_text | Change password |
| 266 | html_text | const csrf = ''; // API helper async function api(path, opts = {}) { const res = await fetch(path, { method: opts.method \|\| 'GET', headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) }, body: opts.body ? JSON.stringify(opts.body) : undefined, credentials: 'include' }); const data = await res.json().catch(() => ({})); if (!res.ok) throw new Error(data?.error?.message \|\| res.statusText); return data; } // Load stats async function loadActivity() { try { const stats = await api('/api/account/activity.php'); document.getElementById('stats-conversations-week').textContent = stats.conversations_this_week; document.getElementById('stats-conversations-total').textContent = stats.total_conversations; document.getElementById('stats-messages-week').textContent = stats.messages_this_week; document.getElementById('stats-messages-total').textContent = stats.total_messages; document.getElementById('activity-loading').classList.add('hidden'); document.getElementById('activity-content').classList.remove('hidden'); } catch (err) { document.getElementById('activity-loading').innerHTML = ' |
| 297 | html_text | Error loading stats |
| 297 | html_text | '; } } // Edit profile const profileView = document.getElementById('profile-view'); const profileForm = document.getElementById('profile-edit-form'); const editToggleBtn = document.getElementById('edit-toggle-btn'); const cancelEditBtn = document.getElementById('cancel-edit-btn'); const editFirstName = document.getElementById('edit-first-name'); const editLastName = document.getElementById('edit-last-name'); const displayFirstName = document.getElementById('display-first-name'); const displayLastName = document.getElementById('display-last-name'); const avatarBig = document.getElementById('avatar-big'); editToggleBtn.addEventListener('click', () => { profileView.classList.add('hidden'); profileForm.classList.remove('hidden'); editFirstName.value = displayFirstName.textContent.trim(); editLastName.value = displayLastName.textContent.trim(); editFirstName.focus(); }); cancelEditBtn.addEventListener('click', () => { profileView.classList.remove('hidden'); profileForm.classList.add('hidden'); }); profileForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitBtn = profileForm.querySelector('button[type="submit"]'); submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; try { const data = await api('/api/account/update_profile.php', { method: 'POST', body: { first_name: editFirstName.value.trim(), last_name: editLastName.value.trim() } }); displayFirstName.textContent = data.user.first_name; displayLastName.textContent = data.user.last_name; // Update avatar const initials = data.user.first_name[0].toUpperCase() + data.user.last_name[0].toUpperCase(); avatarBig.textContent = initials; profileView.classList.remove('hidden'); profileForm.classList.add('hidden'); } catch (err) { alert('Error updating profile: ' + err.message); } finally { submitBtn.disabled = false; submitBtn.textContent = 'Save changes'; } }); // Change password modal const passwordModal = document.getElementById('password-modal'); const changePasswordBtn = document.getElementById('change-password-btn'); const closeModalBtn = document.getElementById('close-modal-btn'); const cancelPasswordBtn = document.getElementById('cancel-password-btn'); const passwordForm = document.getElementById('password-form'); const passwordError = document.getElementById('password-error'); const passwordSuccess = document.getElementById('password-success'); changePasswordBtn.addEventListener('click', () => { passwordModal.classList.remove('hidden'); document.getElementById('current-password').focus(); }); [closeModalBtn, cancelPasswordBtn].forEach(btn => { btn.addEventListener('click', () => { passwordModal.classList.add('hidden'); passwordForm.reset(); passwordError.classList.add('hidden'); passwordSuccess.classList.add('hidden'); }); }); passwordForm.addEventListener('submit', async (e) => { e.preventDefault(); passwordError.classList.add('hidden'); passwordSuccess.classList.add('hidden'); const current = document.getElementById('current-password').value; const newPass = document.getElementById('new-password').value; const confirm = document.getElementById('confirm-password').value; if (newPass !== confirm) { passwordError.textContent = 'Passwords do not match'; passwordError.classList.remove('hidden'); return; } const submitBtn = passwordForm.querySelector('button[type="submit"]'); submitBtn.disabled = true; submitBtn.textContent = 'Changing...'; try { await api('/api/account/change_password.php', { method: 'POST', body: { current_password: current, new_password: newPass, confirm_password: confirm } }); passwordSuccess.textContent = 'Password updated successfully'; passwordSuccess.classList.remove('hidden'); passwordForm.reset(); setTimeout(() => { passwordModal.classList.add('hidden'); passwordSuccess.classList.add('hidden'); }, 2000); } catch (err) { passwordError.textContent = err.message; passwordError.classList.remove('hidden'); } finally { submitBtn.disabled = false; submitBtn.textContent = 'Change password'; } }); // Close modal when clicking outside passwordModal.addEventListener('click', (e) => { if (e.target === passwordModal) { passwordModal.classList.add('hidden'); passwordForm.reset(); passwordError.classList.add('hidden'); passwordSuccess.classList.add('hidden'); } }); // Load activity on start loadActivity(); |
| 297 | script_string | <p class="text-sm text-red-600">Error loading stats</p> |
| 327 | script_string | button[type="submit"] |
| 350 | script_string | Error updating profile: |
| 353 | script_string | Save changes |
| 390 | script_string | Passwords do not match |
| 395 | script_string | button[type="submit"] |
| 409 | script_string | Password updated successfully |
| 422 | script_string | Change password |

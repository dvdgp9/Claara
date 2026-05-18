# Page Text Extraction

- Source: `/public/gestos/creador-cursos.php`
- Extracted strings: 26

| Line | Type | Text |
|---:|---|---|
| 44 | html_text | .format-card { transition: all 0.2s ease; position: relative; } .format-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); } .format-card.selected { border-color: #10b981; background: linear-gradient(135deg, rgba(16,185,129,0.05) 0%, rgba(20,184,166,0.08) 100%); } .format-card.selected .format-icon { transform: scale(1.1); } .format-card .check-badge { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; background: #10b981; border-radius: 50%; display: none; align-items: center; justify-content: center; color: white; font-size: 10px; box-shadow: 0 2px 4px rgba(16,185,129,0.3); } .format-card.selected .check-badge { display: flex; } .config-option { transition: all 0.2s ease; } .config-option:hover { border-color: #10b981; } .config-option.selected { border-color: #10b981; background-color: rgba(16,185,129,0.1); } .result-tab { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem; } .result-tab:hover { background: rgba(16,185,129,0.1); } .result-tab.active { background: #10b981; color: white; } .result-panel { display: none; } .result-panel.active { display: block; } .preview-toggle { display: flex; gap: 0.5rem; padding: 0.25rem; background: #f1f5f9; border-radius: 0.5rem; width: fit-content; } .preview-toggle button { padding: 0.375rem 0.75rem; font-size: 0.75rem; border-radius: 0.375rem; transition: all 0.2s; } .preview-toggle button.active { background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); } .content-preview { background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; max-height: 600px; overflow-y: auto; } .content-preview h1, .content-preview h2, .content-preview h3 { margin-top: 1.5rem; margin-bottom: 0.75rem; } .content-preview h1 { font-size: 1.5rem; font-weight: 700; } .content-preview h2 { font-size: 1.25rem; font-weight: 600; } .content-preview h3 { font-size: 1.1rem; font-weight: 600; } .content-preview p { margin-bottom: 1rem; } .content-preview ul, .content-preview ol { margin-left: 1.5rem; margin-bottom: 1rem; } .content-preview li { margin-bottom: 0.5rem; } .content-preview strong { font-weight: 600; } .content-preview table { width: 100%; border-collapse: collapse; margin: 1rem 0; } .content-preview th, .content-preview td { border: 1px solid #e2e8f0; padding: 0.5rem; text-align: left; } .content-preview th { background: #f8fafc; font-weight: 600; } .raw-preview { background: #1e293b; color: #e2e8f0; border-radius: 0.75rem; padding: 1rem; max-height: 600px; overflow: auto; } .raw-preview pre { white-space: pre-wrap; word-break: break-word; font-family: 'Fira Code', 'Monaco', monospace; font-size: 0.8rem; line-height: 1.6; } |
| 221 | html_text | Course Creator |
| 224 | html_text | Upload a PDF or paste handbook text. Generate an editable learning outline and build the full content for each module. |
| 236 | html_text | Material de origen |
| 254 | html_text | Drag a PDF or click to select |
| 255 | html_text | Handbook, syllabus, theory... (max 20MB) |
| 268 | attribute | Paste the handbook, theory, or source material you want to turn into a course... |
| 269 | html_text | Minimum 100 words to generate quality content |
| 319 | html_text | 🌱 Basic |
| 325 | html_text | 🌿 Intermediate |
| 331 | html_text | 🌳 Advanced |
| 347 | html_text | 🏫 On-site |
| 353 | html_text | 💻 Online |
| 359 | html_text | 🔄 Hybrid |
| 369 | html_text | Generate course outline |
| 373 | html_text | Step 1 of 2: Generate an outline you can edit before building the content |
| 385 | html_text | This may take a few minutes depending on selected formats |
| 411 | html_text | Course outline |
| 412 | html_text | Step 2 of 2: Review and edit the outline, then generate the modules |
| 428 | html_text | Course generated |
| 433 | html_text | New course |
| 448 | html_text | window.CSRF_TOKEN = ''; |
| 454 | html_text | // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('course-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); mobileDrawerContent.addEventListener('click', (e) => { const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('course-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 463 | script_string | .opacity-0, .lg\:opacity-0 |
| 482 | script_string | ${id} |
| 496 | script_string | ${id} |

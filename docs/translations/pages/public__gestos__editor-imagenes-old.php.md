# Page Text Extraction

- Source: `/public/gestos/editor-imagenes-old.php`
- Extracted strings: 39

| Line | Type | Text |
|---:|---|---|
| 47 | attribute | Nueva imagen |
| 83 | html_text | Image editor |
| 87 | html_text | Generate images with Nanobanana |
| 97 | html_text | Image editor |
| 98 | html_text | Generate or edit images with AI |
| 144 | html_text | Edit mode: |
| 144 | html_text | Upload a source image to edit. Optionally add a target image to blend elements. |
| 150 | html_text | Source image |
| 155 | html_text | Drag or click |
| 166 | html_text | Target image |
| 166 | html_text | (optional) |
| 171 | html_text | Drag or click |
| 185 | html_text | What image do you want to create? |
| 186 | attribute | Describe the image you need. Be specific: objects, scene, atmosphere, colors... |
| 290 | html_text | Digital Art |
| 311 | html_text | 3D Render |
| 318 | html_text | Flat Design |
| 334 | html_text | Pro Portrait |
| 342 | html_text | Pro Corporate |
| 350 | html_text | Luxury Product |
| 405 | html_text | B/N |
| 467 | html_text | Golden hour |
| 508 | html_text | Wide shot |
| 522 | html_text | Low angle |
| 536 | html_text | Negative space |
| 549 | html_text | Current settings |
| 551 | html_text | Format 1:1 • No specific style |
| 558 | html_text | Generate image |
| 566 | html_text | Generated image |
| 577 | attribute | Imagen generada |
| 590 | html_text | Generating image with Nanobanana... |
| 591 | html_text | This may take a few seconds |
| 604 | attribute | Enlarged image |
| 607 | html_text | window.CSRF_TOKEN = ''; |
| 614 | html_text | .option-pill.active { border-color: #f59e0b !important; background: rgba(245, 158, 11, 0.1); color: #b45309; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); } .mode-toggle-btn { color: #64748b; background: transparent; } .mode-toggle-btn.active { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important; color: white !important; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25); transform: translateY(-1px); } .provider-toggle-btn { color: #64748b; background: transparent; position: relative; } .provider-toggle-btn.active { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important; color: white !important; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25); transform: translateY(-1px); } .provider-toggle-btn.active span span { background: white !important; } .option-tab.active { color: #f59e0b; border-bottom-color: #f59e0b; background: rgba(245, 158, 11, 0.05); } .option-pill.active, .option-pill:has(input:checked) { border-color: #f59e0b !important; background: rgba(245, 158, 11, 0.1); color: #b45309; } .mode-btn { color: #64748b; background: transparent; } .mode-btn.active { color: #fff; background: linear-gradient(to right, #f59e0b, #ea580c); box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3); } .mode-btn:not(.active):hover { background: rgba(245, 158, 11, 0.1); color: #f59e0b; } |
| 676 | html_text | // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('gesture-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); mobileDrawerContent.addEventListener('click', (e) => { const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('gesture-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 685 | script_string | .opacity-0, .lg\:opacity-0 |
| 704 | script_string | ${id} |
| 718 | script_string | ${id} |

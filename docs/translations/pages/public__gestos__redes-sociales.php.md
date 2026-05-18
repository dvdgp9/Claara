# Page Text Extraction

- Source: `/public/gestos/redes-sociales.php`
- Extracted strings: 23

| Line | Type | Text |
|---:|---|---|
| 47 | attribute | New post |
| 83 | html_text | What is this post about? |
| 86 | attribute | Ej: Hoy han terminado las obras del nuevo CUBOFIT... |
| 133 | html_text | Brand / context |
| 231 | html_text | Advanced options |
| 239 | html_text | Narrative focus |
| 270 | html_text | Closing type |
| 286 | html_text | Soft CTA |
| 290 | html_text | Clear CTA |
| 300 | html_text | Generate post |
| 307 | html_text | Editorial summary |
| 325 | html_text | Your post will appear here |
| 326 | html_text | Configure the options on the left and click "Generate post" |
| 336 | html_text | Building post... |
| 379 | html_text | Quick variants |
| 383 | html_text | More conversational |
| 386 | html_text | More formal |
| 392 | html_text | More direct |
| 395 | html_text | More emotional |
| 413 | html_text | // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('social-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; // Forzar visibilidad de acciones en móvil (no hay hover) mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); // Event delegation para clics en el drawer móvil mobileDrawerContent.addEventListener('click', (e) => { // Clic en el botón de eliminar const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } // Clic en el item principal (cargar contenido) const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('social-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 423 | script_string | .opacity-0, .lg\:opacity-0 |
| 444 | script_string | ${id} |
| 459 | script_string | ${id} |

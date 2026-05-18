# Page Text Extraction

- Source: `/public/gestos/podcast-articulo.php`
- Extracted strings: 20

| Line | Type | Text |
|---:|---|---|
| 44 | html_text | .audio-player-warm { background: linear-gradient(135deg, #7c2d12 0%, #c2410c 100%); } .custom-audio-player::-webkit-media-controls-enclosure { background-color: rgba(255, 255, 255, 0.1); } @keyframes pulse-wave { 0%, 100% { transform: scaleY(0.5); opacity: 0.5; } 50% { transform: scaleY(1); opacity: 1; } } .wave-bar { animation: pulse-wave 1s ease-in-out infinite; } .history-item.active { background-color: rgba(249, 115, 22, 0.05); border-left: 3px solid #f97316; } .history-item.active p { color: #c2410c; font-weight: 600; } .wave-bar:nth-child(2) { animation-delay: 0.1s; } .wave-bar:nth-child(3) { animation-delay: 0.2s; } .wave-bar:nth-child(4) { animation-delay: 0.3s; } .wave-bar:nth-child(5) { animation-delay: 0.4s; } |
| 112 | html_text | Turn text into audio |
| 115 | html_text | Transform any article, document, or text into a dynamic podcast hosted by Iris and Bruno. Great for consuming content while you do other tasks. |
| 127 | html_text | Article source |
| 149 | html_text | Paste the URL of any web article |
| 156 | attribute | Paste the article text here... |
| 157 | html_text | Copy and paste the article content directly |
| 164 | html_text | Drag a PDF or click to select |
| 174 | html_text | Generate Podcast |
| 190 | html_text | This can take up to 5 minutes |
| 193 | attribute | Cancel generation |
| 219 | html_text | Generated podcast |
| 224 | html_text | New podcast |
| 235 | html_text | Generated podcast |
| 245 | html_text | Hosted by Iris and Bruno |
| 260 | html_text | Transcript and script |
| 282 | html_text | // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('podcast-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; // Forzar visibilidad de acciones en móvil (no hay hover) mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); // Event delegation para clics en el drawer móvil mobileDrawerContent.addEventListener('click', (e) => { // Clic en el botón de eliminar const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } // Clic en el item principal (cargar contenido) const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('podcast-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 292 | script_string | .opacity-0, .lg\:opacity-0 |
| 313 | script_string | ${id} |
| 328 | script_string | ${id} |

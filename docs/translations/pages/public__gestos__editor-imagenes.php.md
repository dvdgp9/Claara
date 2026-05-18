# Page Text Extraction

- Source: `/public/gestos/editor-imagenes.php`
- Extracted strings: 62

| Line | Type | Text |
|---:|---|---|
| 47 | attribute | New image |
| 84 | html_text | Create from scratch |
| 87 | html_text | Open concept |
| 91 | html_text | Edit image |
| 94 | html_text | Edits from source |
| 101 | html_text | Brand and communications |
| 108 | html_text | Commercial mockup |
| 112 | html_text | Poster + logos |
| 115 | html_text | Up to 4 references |
| 125 | attribute | Describe the image you want to create... |
| 134 | html_text | Automatic settings |
| 153 | html_text | Visual references |
| 154 | html_text | (optional · max 4) |
| 168 | html_text | Change background |
| 169 | html_text | Add logo |
| 170 | html_text | Remove object |
| 171 | html_text | Improve lighting |
| 172 | html_text | Change colors |
| 173 | html_text | Extend framing |
| 185 | html_text | Generate your first image |
| 186 | html_text | Describe what you want to create and tune the settings |
| 198 | html_text | Imagen fuente |
| 199 | html_text | Drag or click |
| 201 | attribute | Imagen fuente |
| 206 | html_text | Imagen a editar |
| 207 | html_text | (requerida) |
| 217 | html_text | Drag or click |
| 219 | attribute | Imagen referencia |
| 224 | html_text | Imagen objetivo |
| 225 | html_text | (opcional) |
| 233 | attribute | Imagen generada |
| 237 | attribute | Usar como base |
| 239 | html_text | Usar como base |
| 247 | attribute | Ver grande |
| 262 | html_text | Generating image... |
| 263 | html_text | This may take a few seconds |
| 372 | html_text | Wide shot |
| 386 | html_text | Low angle |
| 400 | html_text | Neg. space |
| 433 | html_text | Digital Art |
| 454 | html_text | 3D Render |
| 461 | html_text | Flat Design |
| 475 | html_text | Luxury product |
| 529 | html_text | Golden hour |
| 604 | html_text | B/N |
| 629 | attribute | Enlarged image |
| 638 | html_text | Image parameters |
| 653 | html_text | window.CSRF_TOKEN = ''; |
| 660 | html_text | .format-pill.active, .format-pill:has(input:checked), .style-pill.active, .style-pill:has(input:checked) { border-color: #f59e0b !important; background: rgba(245, 158, 11, 0.1); color: #b45309; } .intent-card.active { border-color: #f59e0b; background: rgba(245, 158, 11, 0.08); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35); } .intent-card { cursor: pointer; transform: translateY(0); transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background-color .18s ease; } .intent-card:hover { transform: translateY(-1px); box-shadow: 0 10px 24px -22px rgba(2, 6, 23, .55); } |
| 687 | html_text | // === Parámetros Modal (Móvil) === document.addEventListener('DOMContentLoaded', () => { const openParamsBtn = document.getElementById('open-params-mobile'); const paramsModal = document.getElementById('params-modal'); const closeParamsBtn = document.getElementById('close-params-modal'); const paramsModalContent = document.getElementById('params-modal-content'); const desktopParamsPanel = document.querySelector('#controls-panel .flex-1.overflow-auto'); // Sincronizar contenido del panel desktop al modal móvil let modalInitialized = false; // Función para sincronizar el estado visual de todos los radios checked function syncAllRadioStates() { // Obtener todos los radios checked del desktop const checkedRadios = desktopParamsPanel.querySelectorAll('input[type="radio"]:checked'); checkedRadios.forEach(desktopRadio => { const modalRadio = paramsModalContent.querySelector(`input[name="${desktopRadio.name}"][value="${desktopRadio.value}"]`); if (modalRadio) { modalRadio.checked = true; } }); } function syncParamsContent() { if (desktopParamsPanel && paramsModalContent) { // Solo copiar HTML la primera vez if (!modalInitialized) { paramsModalContent.innerHTML = desktopParamsPanel.innerHTML; modalInitialized = true; // Añadir listeners a los radios del modal para sincronizar con desktop paramsModalContent.querySelectorAll('input[type="radio"]').forEach(radio => { radio.addEventListener('change', () => { // Encontrar el radio correspondiente en desktop y marcarlo const desktopRadio = desktopParamsPanel.querySelector(`input[name="${radio.name}"][value="${radio.value}"]`); if (desktopRadio) { desktopRadio.checked = true; // Disparar evento change en desktop para que se actualice el resumen desktopRadio.dispatchEvent(new Event('change', { bubbles: true })); } }); }); } // SIEMPRE sincronizar el estado de todos los radios al abrir syncAllRadioStates(); } } // Abrir modal if (openParamsBtn && paramsModal) { openParamsBtn.addEventListener('click', () => { syncParamsContent(); paramsModal.classList.remove('hidden'); paramsModal.classList.add('flex'); }); } // Cerrar modal if (closeParamsBtn && paramsModal) { closeParamsBtn.addEventListener('click', () => { paramsModal.classList.add('hidden'); paramsModal.classList.remove('flex'); }); } // Cerrar al hacer clic fuera if (paramsModal) { paramsModal.addEventListener('click', (e) => { if (e.target === paramsModal) { paramsModal.classList.add('hidden'); paramsModal.classList.remove('flex'); } }); } // === Sincronizar historial con drawer móvil === const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('gesture-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); mobileDrawerContent.addEventListener('click', (e) => { const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('gesture-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 694 | script_string | #controls-panel .flex-1.overflow-auto |
| 702 | script_string | input[type="radio"]:checked |
| 705 | attribute | ${desktopRadio.value} |
| 705 | script_string | ${desktopRadio.name} |
| 705 | script_string | ${desktopRadio.value} |
| 720 | script_string | input[type="radio"] |
| 723 | attribute | ${radio.value} |
| 723 | script_string | ${radio.name} |
| 723 | script_string | ${radio.value} |
| 772 | script_string | .opacity-0, .lg\:opacity-0 |
| 790 | script_string | ${id} |
| 804 | script_string | ${id} |

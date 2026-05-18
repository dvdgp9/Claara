# Page Text Extraction

- Source: `/public/gestos/sop-generator.php`
- Extracted strings: 47

| Line | Type | Text |
|---:|---|---|
| 42 | html_text | .source-card { transition: all 0.2s ease; position: relative; cursor: pointer; } .source-card:hover { transform: translateY(-2px); border-color: #10b981; } .source-card.has-content { border-color: #10b981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(20, 184, 166, 0.05) 100%); } .source-card.has-content .source-icon { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); color: white; } .source-card .check-badge { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; background: #10b981; border-radius: 50%; display: none; align-items: center; justify-content: center; color: white; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); } .source-card.has-content .check-badge { display: flex; } .source-card.active { border-color: #10b981; background: rgba(16, 185, 129, 0.05); ring: 2px solid #10b981; } .source-card.active .source-icon { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); color: white; } .history-item.active { background-color: rgba(16, 185, 129, 0.05); border-left: 3px solid #10b981; } .result-tabs { display: flex; gap: 4px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; } .result-tabs::-webkit-scrollbar { display: none; } .result-tab { padding: 8px 16px; border-radius: 9999px; font-size: 14px; font-weight: 500; white-space: nowrap; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; } .result-tab:hover { background: #f8fafc; } .result-tab.active { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); color: white; border-color: transparent; } .result-panel { display: none; } .result-panel.active { display: block; } .mermaid-container { background: white; border-radius: 12px; padding: 16px; overflow: auto; } .processing-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 100; backdrop-filter: blur(4px); } .processing-overlay:not(.hidden) { display: flex; align-items: center; justify-content: center; } .processing-card { background: white; border-radius: 16px; padding: 32px; text-align: center; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); } .pulse-ring { animation: pulse-ring 1.5s ease-out infinite; } @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 1; } 100% { transform: scale(1.3); opacity: 0; } } .file-preview { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; } .file-preview .file-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 20px; } .file-preview .file-icon.audio { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; } .file-preview .file-icon.image { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; } .file-preview .file-icon.pdf { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; } .images-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; } .image-thumb { aspect-ratio: 1; border-radius: 8px; overflow: hidden; position: relative; } .image-thumb img { width: 100%; height: 100%; object-fit: cover; } .image-thumb .remove-btn { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; opacity: 0; transition: opacity 0.2s; } .image-thumb:hover .remove-btn { opacity: 1; } |
| 272 | html_text | Process generator |
| 275 | html_text | Transform unstructured information into professional operating procedures. Upload text, audio, images, or PDFs. |
| 287 | html_text | Procedure title |
| 288 | html_text | (optional) |
| 292 | attribute | Ex: New employee onboarding process |
| 298 | html_text | Content sources |
| 299 | html_text | (add one or more) |
| 310 | html_text | Paste content |
| 320 | html_text | Extract from web |
| 340 | html_text | Analyze screenshots |
| 350 | attribute | Paste process information here: meeting notes, emails, informal instructions, etc. |
| 365 | html_text | Upload file |
| 378 | html_text | Drag an audio file or click to select |
| 379 | html_text | MP3, WAV, M4A, WebM (max 25MB) |
| 388 | html_text | 2.5 MB |
| 406 | html_text | Press the button to start recording |
| 408 | html_text | Start recording |
| 433 | html_text | Audio recording |
| 455 | html_text | Drag images or click to select |
| 456 | html_text | Screenshots, diagrams, photos (multiple) |
| 468 | html_text | Drag a PDF or click to select |
| 469 | html_text | Documents, handbooks, guides (max 20MB) |
| 478 | html_text | 1.2 MB |
| 491 | html_text | Add PDF |
| 504 | html_text | Generate SOP |
| 514 | html_text | Generate new process |
| 543 | html_text | Structured procedure |
| 565 | html_text | Download PNG |
| 569 | html_text | Copy code |
| 582 | html_text | Download document |
| 593 | html_text | Portable document |
| 604 | html_text | Word (DOCX) |
| 605 | html_text | Editable in Microsoft Word |
| 627 | html_text | Generating SOP |
| 628 | html_text | Processing content... |
| 638 | html_text | // Inicializar Mermaid con configuración optimizada para diagramas ordenados mermaid.initialize({ startOnLoad: false, theme: 'neutral', flowchart: { htmlLabels: true, curve: 'basis', rankSpacing: 50, nodeSpacing: 30, padding: 15, useMaxWidth: true, defaultRenderer: 'dagre-wrapper' }, themeVariables: { primaryColor: '#e0f2f1', primaryBorderColor: '#26a69a', primaryTextColor: '#37474f', lineColor: '#78909c', secondaryColor: '#fff8e1', tertiaryColor: '#f3e5f5' } }); |
| 653 | script_string | #e0f2f1 |
| 654 | script_string | #26a69a |
| 655 | script_string | #37474f |
| 656 | script_string | #78909c |
| 657 | script_string | #fff8e1 |
| 658 | script_string | #f3e5f5 |
| 663 | html_text | window.CSRF_TOKEN = ''; // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('sop-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; // Forzar visibilidad de acciones en móvil (no hay hover) mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); // Event delegation para clics en el drawer móvil mobileDrawerContent.addEventListener('click', (e) => { // Clic en el botón de eliminar const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } // Clic en el item principal (cargar contenido) const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('sop-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 675 | script_string | .opacity-0, .lg\:opacity-0 |
| 696 | script_string | ${id} |
| 711 | script_string | ${id} |

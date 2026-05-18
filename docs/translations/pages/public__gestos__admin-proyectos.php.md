# Page Text Extraction

- Source: `/public/gestos/admin-proyectos.php`
- Extracted strings: 23

| Line | Type | Text |
|---:|---|---|
| 44 | html_text | .action-card { transition: all 0.2s ease; position: relative; } .action-card:hover { transform: translateY(-2px); } .action-card.selected { border-color: #10b981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(20, 184, 166, 0.1) 100%); box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3); } .action-card.selected .action-icon { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); color: white; } .action-card .check-badge { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; background: #10b981; border-radius: 50%; display: none; align-items: center; justify-content: center; color: white; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); } .action-card.selected .check-badge { display: flex; } .history-item.active { background-color: rgba(16, 185, 129, 0.05); border-left: 3px solid #10b981; } .dropzone { border: 2px dashed #cbd5e1; transition: all 0.2s ease; } .dropzone.dragover { border-color: #10b981; background: rgba(16, 185, 129, 0.05); } .file-item { animation: slideIn 0.2s ease; } @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } } .result-table { width: 100%; border-collapse: collapse; } .result-table th, .result-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; } .result-table th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 0.875rem; } .result-table tr:hover { background: #f8fafc; } .result-table .total-row { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(20, 184, 166, 0.1) 100%); font-weight: 600; } .result-table .subtotal-row { background: #f1f5f9; font-weight: 500; } |
| 167 | html_text | Project Analysis |
| 170 | html_text | Upload tender documents and automatically extract non-staff costs, work hours, and other key information. |
| 182 | html_text | Tender documents |
| 188 | html_text | Drag PDFs here or click to select |
| 189 | html_text | Maximum 40MB per file |
| 200 | html_text | Additional instructions |
| 201 | html_text | (optional) |
| 205 | attribute | Example: Focus on IT equipment requirements... |
| 211 | html_text | What do you want to analyze? |
| 212 | html_text | (select one or more) |
| 221 | html_text | Non-staff costs |
| 222 | html_text | Equipment, materials, licenses, insurance... |
| 230 | html_text | Hours count |
| 231 | html_text | Service, training, and coordination hours... |
| 235 | html_text | 1 analysis selected |
| 241 | html_text | Analyze tender |
| 250 | html_text | Extracting tender information |
| 276 | html_text | Analysis completed |
| 278 | html_text | Tender analyzed |
| 302 | html_text | window.CSRF_TOKEN = ''; window.GESTURE_TYPE = 'project-admin'; |
| 311 | html_text | // Sync history with mobile drawer document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('project-admin-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); mobileDrawerContent.addEventListener('click', (e) => { const historyItem = e.target.closest('.history-item-main'); if (historyItem) { const itemId = historyItem.closest('.history-item')?.dataset.id; if (itemId && window.loadHistoryItem) { window.loadHistoryItem(itemId); // Cerrar drawer document.getElementById('project-admin-history-drawer')?.classList.add('hidden'); } } }); } }); |
| 320 | script_string | .opacity-0, .lg\:opacity-0 |

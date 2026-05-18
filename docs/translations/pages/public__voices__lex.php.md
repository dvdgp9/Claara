# Page Text Extraction

- Source: `/public/voices/lex.php`
- Extracted strings: 20

| Line | Type | Text |
|---:|---|---|
| 59 | attribute | New query |
| 114 | html_text | Hi, |
| 115 | html_text | I am |
| 116 | html_text | , your legal assistant. I can help with questions about reference documents, collective agreements, rights, leave, and legal procedures. |
| 121 | html_text | Try asking: |
| 123 | html_text | How many vacation days does the agreement provide? |
| 126 | html_text | What is the procedure for requesting unpaid leave? |
| 129 | html_text | What does the agreement say about overtime? |
| 160 | attribute | Write your legal question... |
| 176 | html_text | Available documents |
| 179 | html_text | Lex knowledge sources |
| 186 | attribute | Search by title... |
| 193 | html_text | Loading documents... |
| 200 | html_text | Lex uses these documents to answer |
| 221 | html_text | Lex reference document |
| 234 | html_text | Loading document... |
| 253 | html_text | // Sync history with mobile drawer. document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('lex-history-drawer-content'); if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; const observer = new MutationObserver(() => { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; }); observer.observe(desktopHistory, { childList: true, subtree: true }); // Event delegation para clics en el drawer móvil mobileDrawerContent.addEventListener('click', (e) => { const historyItem = e.target.closest('[data-history-id], [data-id], [data-conv-id], .history-item, button'); if (historyItem) { const allMobileItems = mobileDrawerContent.querySelectorAll('[data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"]'); const allDesktopItems = desktopHistory.querySelectorAll('[data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"]'); const index = Array.from(allMobileItems).indexOf(historyItem); if (index >= 0 && allDesktopItems[index]) { closeMobileDrawer('lex-history-drawer'); allDesktopItems[index].click(); } } }); } // Sincronizar botón nueva consulta móvil const mobileNewBtn = document.getElementById('mobile-new-chat-btn'); const desktopNewBtn = document.getElementById('new-chat-btn'); if (mobileNewBtn && desktopNewBtn) { mobileNewBtn.addEventListener('click', () => { closeMobileDrawer('lex-history-drawer'); desktopNewBtn.click(); }); } // Auto-expand textarea de entrada const input = document.getElementById('chat-input'); function autoResize(el){ if(!el) return; el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; } if (input) { autoResize(input); input.addEventListener('input', () => autoResize(input)); } // Sincronizar documentos con drawer móvil const docsPanelList = document.getElementById('docs-list'); const docsDrawerContent = document.getElementById('lex-docs-drawer-content'); if (docsPanelList && docsDrawerContent) { // Clonar contenido inicial cuando cargue const syncDocs = () => { docsDrawerContent.innerHTML = docsPanelList.innerHTML; }; syncDocs(); const obs = new MutationObserver(syncDocs); obs.observe(docsPanelList, { childList: true, subtree: true }); // Delegación de clics para abrir visor de documentos desde el drawer docsDrawerContent.addEventListener('click', (e) => { const btn = e.target.closest('.doc-item'); if (!btn) return; const docId = btn.getAttribute('data-doc-id'); if (docId) { closeMobileDrawer('lex-docs-drawer'); if (window.lexOpenDocViewer) { window.lexOpenDocViewer(docId); } } }); } }); |
| 269 | script_string | [data-history-id], [data-id], [data-conv-id], .history-item, button |
| 271 | script_string | [data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"] |
| 272 | script_string | [data-history-id], [data-id], [data-conv-id], .history-item, button[class*="history"] |

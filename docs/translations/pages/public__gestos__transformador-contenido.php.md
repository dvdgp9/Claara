# Page Text Extraction

- Source: `/public/gestos/transformador-contenido.php`
- Extracted strings: 26

| Line | Type | Text |
|---:|---|---|
| 45 | html_text | .format-card { transition: all 0.2s ease; position: relative; } .format-card:hover { transform: translateY(-2px); } .format-card.selected { border-color: #6366f1; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3); } .format-card.selected .format-icon { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; } .format-card .check-badge { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; background: #6366f1; border-radius: 50%; display: none; align-items: center; justify-content: center; color: white; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); } .format-card.selected .check-badge { display: flex; } .history-item.active { background-color: rgba(99, 102, 241, 0.05); border-left: 3px solid #6366f1; } .result-tabs { display: flex; gap: 4px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; } .result-tabs::-webkit-scrollbar { display: none; } .result-tab { padding: 8px 16px; border-radius: 9999px; font-size: 14px; font-weight: 500; white-space: nowrap; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; } .result-tab:hover { background: #f8fafc; } .result-tab.active { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; border-color: transparent; } .result-panel { display: none; } .result-panel.active { display: block; } @media (max-width: 1023px) { .result-panel { display: block !important; margin-bottom: 16px; } .result-tabs { display: none; } } |
| 174 | html_text | Transform your content |
| 177 | html_text | Convert any article, document, or text into the format you need: social posts, blogs, newsletters, landing pages, or FAQs. |
| 189 | html_text | Content source |
| 211 | html_text | Paste the URL of any web article |
| 218 | attribute | Paste the text you want to transform here... |
| 219 | html_text | Minimum 20 words |
| 226 | html_text | Drag a PDF or click to select |
| 236 | html_text | What do you want to generate? |
| 237 | html_text | (select multiple) |
| 239 | html_text | Click the formats you want to generate. You can select multiple at once. |
| 249 | html_text | Post + hashtags |
| 275 | html_text | X (Twitter) |
| 276 | html_text | Tweet/Thread |
| 286 | html_text | SEO article |
| 295 | html_text | HTML/CSS/JS |
| 317 | html_text | 1 format selected |
| 323 | html_text | Transform content |
| 332 | html_text | Extracting and transforming content |
| 358 | html_text | Generated content |
| 360 | html_text | Source: URL |
| 365 | html_text | Copy all |
| 392 | html_text | // Sync history with mobile drawer document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('repurposer-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); mobileDrawerContent.addEventListener('click', (e) => { const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('repurposer-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 401 | script_string | .opacity-0, .lg\:opacity-0 |
| 420 | script_string | ${id} |
| 434 | script_string | ${id} |

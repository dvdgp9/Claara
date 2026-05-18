# Page Text Extraction

- Source: `/public/gestos/escribir-articulo.php`
- Extracted strings: 57

| Line | Type | Text |
|---:|---|---|
| 47 | attribute | New content |
| 84 | html_text | Write content |
| 85 | html_text | Generate articles, blog posts, or press releases |
| 94 | html_text | What type of content do you need? |
| 101 | html_text | Informative article |
| 103 | html_text | News, current affairs, sports, culture. Objective and direct content. |
| 111 | html_text | Blog post |
| 113 | html_text | SEO-optimized, with keywords and web structure. |
| 121 | html_text | Press release |
| 123 | html_text | Official announcements with professional journalistic structure. |
| 131 | html_text | Brand / context: |
| 157 | html_text | Article topic |
| 158 | attribute | Ex: New season of aquatic activities at sports centers |
| 167 | html_text | Health and wellness |
| 174 | html_text | Short (~300 words) |
| 175 | html_text | Medium (~500 words) |
| 176 | html_text | Long (~800 words) |
| 181 | html_text | Additional details |
| 181 | html_text | (optional) |
| 182 | attribute | Extra information, specific data, desired angle... |
| 189 | html_text | Post topic |
| 190 | attribute | Ex: 5 benefits of exercising in the morning |
| 193 | html_text | SEO keywords |
| 193 | html_text | (comma-separated) |
| 194 | attribute | Ex: morning workout, fitness routine, health, wellness |
| 199 | html_text | Automatic SEO setup |
| 201 | html_text | 600-1000 words • H2/H3 structure • Meta description • Intro with keyword • Final CTA |
| 204 | html_text | Additional instructions |
| 204 | html_text | (optional) |
| 205 | attribute | Specific tone, key data to include, call to action... |
| 213 | html_text | Announcement type |
| 247 | html_text | Award/Recognition |
| 256 | html_text | What happened? |
| 257 | attribute | The key fact or main news |
| 260 | html_text | Who is involved? |
| 261 | attribute | Person, company, organization... |
| 264 | html_text | When? |
| 265 | attribute | Date, period, moment... |
| 268 | html_text | Where? |
| 269 | attribute | Location, place, scope... |
| 274 | html_text | Why? |
| 275 | attribute | Reason, cause, context (only verified and reliable information) |
| 278 | html_text | Additional information |
| 278 | html_text | (optional) |
| 279 | attribute | Confirmed complementary data. Do not add anything uncertain. |
| 283 | html_text | Statement or direct quote |
| 283 | html_text | (optional) |
| 285 | attribute | Quote author |
| 286 | attribute | Quote text... |
| 289 | html_text | If you leave fields empty, the system will generate the release with available information. Required fields are marked with *. AI must not invent data (dates, names, roles, figures, etc.); always review for accuracy. |
| 296 | html_text | Generate content |
| 304 | html_text | Generated content |
| 321 | html_text | Generating content... |
| 334 | html_text | // Sincronizar historial con drawer móvil document.addEventListener('DOMContentLoaded', () => { const desktopHistory = document.getElementById('history-list'); const mobileDrawerContent = document.getElementById('gesture-history-drawer-content'); function syncDrawerContent() { if (desktopHistory && mobileDrawerContent) { mobileDrawerContent.innerHTML = desktopHistory.innerHTML; // Forzar visibilidad de acciones en móvil (no hay hover) mobileDrawerContent.querySelectorAll('.opacity-0, .lg\\:opacity-0').forEach(el => { el.classList.remove('opacity-0', 'lg:opacity-0'); el.classList.add('opacity-100'); }); } } if (desktopHistory && mobileDrawerContent) { syncDrawerContent(); const observer = new MutationObserver(syncDrawerContent); observer.observe(desktopHistory, { childList: true, subtree: true }); // Event delegation para clics en el drawer móvil mobileDrawerContent.addEventListener('click', (e) => { // Clic en el botón de eliminar const deleteBtn = e.target.closest('.history-item-delete'); if (deleteBtn) { const historyItem = deleteBtn.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItem = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-delete`); if (desktopItem) { e.stopPropagation(); desktopItem.click(); } } return; } // Clic en el item principal (cargar contenido) const historyItemMain = e.target.closest('.history-item-main'); if (historyItemMain) { const historyItem = historyItemMain.closest('.history-item'); if (historyItem) { const id = historyItem.dataset.id; const desktopItemMain = desktopHistory.querySelector(`.history-item[data-id="${id}"] .history-item-main`); if (desktopItemMain) { closeMobileDrawer('gesture-history-drawer'); desktopItemMain.click(); } } return; } }); } }); |
| 344 | script_string | .opacity-0, .lg\:opacity-0 |
| 365 | script_string | ${id} |
| 380 | script_string | ${id} |

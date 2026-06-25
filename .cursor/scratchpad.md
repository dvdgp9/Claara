# Claara Scratchpad

## Background and Motivation

Claara is a private B2B AI workspace for companies. Its purpose is to help employees ask, execute, and improve internal work through:

- A central company chat.
- Specialized RAG voices with their own knowledge bases.
- Guided workflows called gestures.
- Company context and document management.
- Organization governance: users, departments, permissions, voice access, and voice responsibility.
- A feedback loop where users can report issues in voice answers and responsible users/admins can manage them.

The product direction is **managed company intelligence**, not a generic AI toolbox.

## Current Platform Snapshot

- Public landing at `/`; authenticated workspace at `/app/`.
- Main chat supports history, folders, files, web search, image generation mode, and capability recommendations.
- Chat can execute voice RAG queries inline through `/api/capabilities/voice-query.php`.
- Voices are dynamic RAG assistants managed in Voice Studio.
- Voice knowledge can be uploaded, processed, and queried with source match, sources, and conflict metadata.
- Users can report voice answers from chat; reports go to admins or voice responsible users.
- Reports panel exists at `/flags.php`.
- Gestures are structured workflows for tasks such as writing, social media, podcast generation, image editing, content repurposing, SOPs, transcription, course creation, project analysis, and lead finding.
- Organization module manages users, departments, job titles, department responsibility, voice access, and voice responsibility.
- Connectors exist as a foundation for external sources/accounts.

## Active Product Principles

- Application UI copy must be in English.
- CSS belongs in `public/assets/css/styles.css`; avoid new inline CSS.
- Use existing PHP + vanilla JS + Tailwind CDN patterns unless a larger frontend migration is explicitly approved.
- Voice responsibility is not the same as voice access.
- A voice responsible user should keep access to that voice, but removing responsibility should not automatically remove access.
- Conversations, reports, voices, and organization should feel connected, not like isolated admin modules.
- For dashboard/admin UX, prefer compact tables, drawers, tabs, chips, inline states, and clear actions over large standalone cards.
- Avoid exposing technical routes or internal identifiers to end users unless needed for admins.

## Implemented Core Capabilities

### Voice Platform

- Dynamic voices are stored in `voices`.
- Voice documents use flexible context metadata (`target_type='voice'`, `target_slug`).
- Voice Studio supports create/edit/publish/archive, responsible users, document upload, document processing, and publish readiness.
- Published voices appear in `/voices/` and can be opened through a generic voice view.
- Chat capability catalog recommends accessible voices and gestures per user.
- General chat can run voice queries inline and store the resulting assistant message with `metadata.voice_slug`.

### Voice Reports

- Users can report voice answers with issue types such as missing info, incorrect answer, or other.
- Reports are stored in `voice_flags`.
- Responsible users see reports for their voices.
- Admins see assigned reports and an unassigned fallback for voices without responsible users.
- Report states: `open`, `in_progress`, `resolved`, `dismissed`.

### Organization

- Users have `job_title`.
- Departments can have multiple responsible users.
- Voices can have multiple responsible users.
- User management compactly displays profile, department, voice access, voice responsibility, status, and actions.
- Departments and Users share the Organization navigation pattern.
- My Account shows role/profile context and accessible voices.

### Image Generation

- OpenRouter image generation supports `image_config`.
- Aspect ratio and resolution are sent as API parameters, not as prompt text.
- Backend prompt enhancement improves short user prompts before generation.
- Quality guardrails are included for generation and edit flows.

## Active Feature Planning: Shared Conversations

### Background and Motivation

Claara should become more collaborative. Users should be able to share conversations with other users or departments. Shared conversations need two permission levels:

- `Can view`: read-only access.
- `Can chat`: participants can add messages and ask Claara.

The main challenge is keeping the conversation orderly when several users participate and AI responses take a few seconds.

### Product Decisions

- Do not allow multiple simultaneous AI runs in the same conversation.
- “Edit mode” should mean “can participate/chat”, not “can edit other users’ past messages”.
- Human participants may add messages while Claara is responding, but those messages should not automatically trigger another AI response.
- When Claara finishes, show a clear action such as `Ask Claara with new messages` if new participant messages arrived during the AI run.
- A shared conversation has an owner and explicit shares to users and/or departments.
- Department shares should use current department membership at access-check time.
- Start without external email notifications; use in-app state/sidebar indicators first.
- Keep folders private in the first version. Shared conversations should appear in dedicated sidebar sections instead of being moved into another user's private folders.
- Owners can rename, delete, move, favorite, and share their own conversations.
- Shared users with `Can chat` can add new messages and run Claara, but cannot rename, delete, move, favorite, or change shares.
- Shared users with `Can view` can read messages and sources, but cannot upload files, run voices, flag if the flag requires a new message mutation, or trigger AI.
- Superadmins may manage shares for any conversation if needed, but this can be hidden from the first UI.

### UX Direction

- Conversation header should show:
  - shared/private state,
  - participant avatars/initials,
  - permission state (`Read only` or `Can chat`),
  - a `Share` action.
- Read-only conversations keep the input area disabled with a calm message: `You have read-only access to this conversation`.
- Collaborative conversations keep the input available for humans even while Claara is responding, but the AI trigger is locked.
- Sidebar should not dump everything into one list. Use clear groupings:
  - `My conversations`
  - `Shared with me`
  - `Department conversations`
- Shared conversations should show a compact shared icon, permission badge, and new activity indicator.
- Share modal:
  - `People` search/select.
  - `Departments` select.
  - Permission selector: `Can view` / `Can chat`.
  - Save with inline validation and clear empty states.

### Proposed Data Model

`conversation_shares`

- `id BIGINT UNSIGNED AUTO_INCREMENT`
- `conversation_id BIGINT UNSIGNED NOT NULL`
- `target_type ENUM('user','department') NOT NULL`
- `target_id BIGINT UNSIGNED NOT NULL`
- `permission ENUM('view','chat') NOT NULL DEFAULT 'view'`
- `created_by BIGINT UNSIGNED NULL`
- `created_at DATETIME`
- `updated_at DATETIME`
- unique key `(conversation_id, target_type, target_id)`
- indexes:
  - `(target_type, target_id)`
  - `(conversation_id, permission)`

`conversation_ai_runs` or conversation lock fields

- Preferred first cut: dedicated lock fields on `conversations`:
  - `ai_status ENUM('idle','responding') NOT NULL DEFAULT 'idle'`
  - `ai_started_at DATETIME NULL`
  - `ai_locked_by_message_id BIGINT UNSIGNED NULL`
- This is simpler than a historical run table and enough to prevent duplicate AI responses.
- Add a timeout fallback in code: if a lock is older than a short threshold, treat it as stale and release it before starting a new run.

Optional later:

`conversation_participants`

- Track users who actually participated for avatars, activity, and future presence.

### Current Code Findings

- `src/Repos/ConversationsRepo.php` currently assumes ownership through `WHERE user_id = ?` in list, find, rename, delete, favorite, and move operations.
- `public/api/messages/list.php` uses `findByIdForUser`, so shared users currently cannot read messages.
- `public/api/chat-stream.php`, `public/api/chat.php`, `public/api/chat-regenerate.php`, `public/api/chat-regenerate-full.php`, and `public/api/capabilities/voice-query.php` all need permission-aware access checks before writing messages or assistant responses.
- `public/api/conversations/list.php` currently returns only owned conversations and filters by personal folder.
- `public/app.php` renders one flat conversation list inside the selected folder. It needs grouped rendering for shared areas.
- `folders` are user-owned. They should remain owner-only in the MVP.
- Existing admin user/department APIs and repos can support the share modal target picker:
  - `UsersRepo::listAll()`
  - `DepartmentsRepo::listAll()`
- Existing migrations use `BIGINT UNSIGNED`; new foreign keys must match exactly.

### High-Level Task Breakdown

1. [x] Inspect current conversation/message/folder schema and sidebar loading flow.
   - Success: identify all ownership checks that currently assume `conversations.user_id = current user`.

2. [x] Add sharing schema and repository layer.
   - Add migration for `conversation_shares`.
   - Add migration for conversation AI lock fields.
   - Add access helpers: owner, shared user, shared department, permission resolution.
   - Success: one service answers `view`/`chat` permission for a user and conversation.

3. [x] Update conversation read/list APIs.
   - Keep `/api/conversations/list.php` backward compatible for owned/folder views.
   - Add grouped response support or a new endpoint for:
     - `owned`
     - `shared_with_me`
     - `department_shared`
   - Return share metadata and effective permission.
   - Success: shared read-only conversations appear and can be opened without write access.

4. [x] Add Share modal and APIs.
   - Endpoints to list shares, add/update/remove share targets.
   - UI in chat header.
   - Success: owner/admin can share a conversation with users/departments in `Can view` or `Can chat`.

5. [ ] Enforce permissions on writes.
   - Owner-only: rename, delete, folder moves, favorite, share management.
   - `Can chat`: send messages, upload files into that conversation, run standard chat, regenerate own permitted AI flow if approved, run voice-query from the conversation.
   - `Can view`: read messages only.
   - Success: read-only users cannot mutate the conversation; chat users can add messages and trigger Claara without owner privileges.

6. [x] Add single-AI-run lock.
   - Prevent duplicate AI responses in a shared conversation.
   - Show clear UI while Claara is responding.
   - Success: two users cannot start two simultaneous AI runs in the same conversation.

7. [ ] Sidebar organization.
   - Group `My conversations`, `Shared with me`, and `Department conversations`.
   - Add shared icon, permission badge, and activity dot.
   - Success: shared conversations are discoverable without cluttering private folders.

8. [ ] QA and deploy.
   - Test owner, shared user, department member, read-only, chat permission, concurrent AI lock, and unauthorized access.
   - Success: collaboration works without leaking private conversations or breaking current private chat behavior.

### Suggested MVP Scope

Build in this order:

1. Read-only sharing.
2. `Can chat` sharing with one-AI-run lock.
3. Sidebar groups.
4. Department sharing.
5. Presence/activity polish.

### UX Implementation Notes

- Header action: add a compact `Share` button near the conversation title, only visible for owners/admins.
- Header state: show a small chip:
  - `Private`
  - `Shared`
  - `Read only`
  - `Can chat`
- Share modal should be a focused drawer/modal with:
  - target search,
  - segmented target type (`People`, `Departments`),
  - permission selector (`Can view`, `Can chat`),
  - current shares list with compact rows and remove action.
- Sidebar should avoid adding a second full folder tree. Recommended structure:
  - current folder list remains for owner conversations,
  - below it, compact sections for `Shared with me` and `Department conversations`,
  - each shared row shows title, owner/department, permission chip, and last update.
- Read-only input area should stay visually stable but disabled with: `You have read-only access to this conversation`.
- During an AI response in collaborative conversations:
  - input remains available for drafting,
  - send/ask action is disabled or queued with clear copy,
  - if a participant adds text while Claara is responding, show `Ask Claara with new messages` after the current response finishes.

## Active Feature Planning: Voice Access Profiles & Document Folders

### Background and Motivation

Today a voice is a single flat knowledge base and access is binary: a user can open the whole voice or none of it (`user_feature_access` with `feature_type='voice'`). Companies need finer control: inside one voice (e.g. Legal) some documents are for everyone, some only for the legal department, and some only for managers/C-level/board.

Requested model (confirmed with Pierre and the user):

- Inside a voice, organize documents in a **tree of folders**.
- An admin defines **access profiles per voice** (e.g. `standard employee`, `manager`, `board`).
- For each **folder**, the admin chooses which **profiles** can access it.
- Each **user is assigned a profile in that voice** (per-voice, not global: a user can be `board` in Legal and `standard` in HR).
- A user only retrieves/sees documents from folders their profile is allowed to access.

### Product Decisions (confirmed)

- **Profiles are per voice.** Profile assignment is per (user, voice). One profile per user per voice.
- **Folders are a tree.** Access granted on a folder **inherits down** to its descendants. MVP is grant-only (no deny overrides on subfolders).
- **Bulk folder upload:** admin can upload a whole folder from their computer and the folder structure is recreated server-side (browser `webkitdirectory`, each file carries its relative path).
- **Single source of truth for voice access = profiles.** Having a profile in a voice *is* having access to that voice. The legacy binary voice toggle is replaced by/derived from this.
- **Where assignment lives (IA decision):** voice is the source of truth. User→profile assignment is managed in a new **"Access" tab inside Voice Studio** (next to Knowledge/folders). The Feature Permissions page keeps a per-user overview but its **Voices column becomes a profile dropdown** (`No access` / profile) that reads & writes the **same** table. Fine-grained config (folders, folder→profile mapping) is NOT added to the per-user permissions page — it stays voice-centric.
- **Fail closed.** If access resolution fails or yields an empty folder set, the query must return "no accessible documents", never fall back to an unfiltered search. This protects sensitive legal data.

### Key Challenges and Analysis

- **RAG is the only hard part; everything else is CRUD.** All of a voice's documents live in one Qdrant collection (`voices.rag_collection`). `LexRetriever::retrieve()` searches the whole collection with no access filter. Qdrant already supports payload filters (`must/should/must_not`, already used for `document_id` and for delete/count by filter), so per-folder isolation is done by stamping `folder_id` on each chunk's payload and filtering by the user's allowed folder set. No need for a collection per profile (that would re-embed and explode collections).
- **Prompt leakage.** `VoiceContextBuilder::buildSystemPromptWithRag()` injects the names of ALL documents ("Available Documentation") plus a "Retrieved document coverage" section. These must be filtered to allowed folders too, or the model can reveal names/metadata of forbidden documents even without quoting them.
- **Backfill / reindex.** Existing chunks in Qdrant have no `folder_id` payload, and existing voices have no folders/profiles. Need: (a) a default root folder per voice, (b) a default profile that maps to existing `user_feature_access` voice grants so nobody loses access, (c) a one-off script to stamp `folder_id` on existing chunks (or re-process docs). New uploads stamp `folder_id` from the start.
- **Concept proliferation.** We already have departments, RBAC roles, and voice_responsibles. "Profile" is justified (per-voice, orthogonal to department) but we keep it as the only user-facing voice-access concept; RBAC roles stay internal. Departments are NOT reused (org-wide ≠ per-voice sensitivity tier).
- **Inheritance resolution.** Use a materialized `path` on `voice_folders` so ancestor lookups (for "granted on any ancestor") and breadcrumbs are cheap. Resolution flattens to a set of allowed folder ids, keeping the Qdrant filter a simple `match any` list.
- **Type/consistency.** Match existing types: `voices.id`, `users.id` are `BIGINT UNSIGNED`. `voice_responsibles` keys by `voice_slug`; new tables key by `voice_id` (FK to `voices.id`) for referential integrity, but resolution helpers should accept slug or id to fit existing call sites.

### Proposed Data Model

`voice_access_profiles` — profiles scoped to a voice
- `id BIGINT UNSIGNED PK`, `voice_id BIGINT UNSIGNED NOT NULL`, `name VARCHAR(120)`, `slug VARCHAR(120)`, `sort_order INT`, timestamps
- `UNIQUE (voice_id, slug)`, FK `voice_id -> voices(id) ON DELETE CASCADE`

`user_voice_profiles` — one profile per user per voice (= voice access)
- `user_id BIGINT UNSIGNED`, `voice_id BIGINT UNSIGNED`, `profile_id BIGINT UNSIGNED`, `created_at`
- `PRIMARY KEY (user_id, voice_id)`, FKs to `users(id)`, `voices(id)`, `voice_access_profiles(id)` ON DELETE CASCADE

`voice_folders` — document tree per voice
- `id BIGINT UNSIGNED PK`, `voice_id BIGINT UNSIGNED`, `parent_id BIGINT UNSIGNED NULL`, `name VARCHAR(255)`, `path VARCHAR(1000)`, `depth INT`, `sort_order INT`, timestamps
- FK `voice_id -> voices(id) ON DELETE CASCADE`, FK `parent_id -> voice_folders(id) ON DELETE CASCADE`, KEY `(voice_id, parent_id)`

`folder_profile_access` — which profiles can access a folder (inherits down)
- `folder_id BIGINT UNSIGNED`, `profile_id BIGINT UNSIGNED`, `created_at`
- `PRIMARY KEY (folder_id, profile_id)`, FKs ON DELETE CASCADE

`context_documents` — attach docs to a folder
- `ADD COLUMN folder_id BIGINT UNSIGNED NULL` (NULL = voice root). Follow the existing `contextDocsHasColumn()` defensive pattern in `ContextDocsRepo`.

Qdrant payload: add `folder_id` to each chunk (`RagProcessor::processDocument` metadata block).

### Current Code Findings (integration points)

- Access gate today: `VoiceQueryService::query()` calls `UserFeatureAccessRepo::hasVoiceAccess()` (binary). This becomes "resolve profile + allowed folders, else 403".
- Retriever: `src/Rag/LexRetriever.php` `retrieve($query, $topK, $documentFilter)` — add an allowed-folders filter alongside the existing `document_id` filter.
- Context build: `src/Voices/VoiceContextBuilder.php` `buildSystemPromptWithRag()`, `listDocuments()`, `buildRetrievedDocumentSummary()` — must receive and apply the allowed-folder set.
- Indexing: `public/api/admin/voices/documents/process.php` calls `RagProcessor::processDocument(..., [metadata])` with `voices.rag_collection`; payload built in `src/Rag/RagProcessor.php`. Add `folder_id` to metadata + payload.
- Upload: `public/api/admin/voices/documents/upload.php` + `ContextDocsRepo::create()` (already column-adaptive). Accept a `folder_id` / relative path.
- Admin UI: Voice Studio `public/admin/voices.php` (Catalog + Editor + Knowledge panel) is where folders/profiles/Access live. Feature Permissions `public/admin/permissions.php` Voices column (currently a binary toggle, `toggleAllOfType('voice', ...)`) becomes a profile dropdown. Capability recommendations (`src/Claara/CapabilityCatalogService.php`) must use the new access check so chat only suggests accessible voices.
- Org context: `users.job_title`, `department_responsibles`, `voice_responsibles` already exist; superadmins and voice responsibles bypass folder filtering (full access).

### High-Level Task Breakdown

Phase A — Schema & access core (no UI)
1. [ ] Migration `024_voice_access_profiles_and_folders.sql`: create the 4 new tables + `context_documents.folder_id`; backfill a default root folder per voice, a default "Full access" profile per voice, map existing `user_feature_access` voice grants into `user_voice_profiles`, and grant the root folder to all existing profiles.
   - Success: existing voices keep working; every current voice user has an equivalent profile; FKs/types match existing schema.
2. [ ] Repos + access service: `VoiceProfilesRepo`, `VoiceFoldersRepo`, and a resolver `resolveAccessibleFolderIds(userId, voice)` + `getUserVoiceProfile(userId, voice)`.
   - Success: resolver returns allowed folder ids (superadmin/responsible → all; no profile → empty/none), with inheritance via `path`. Unit-checkable in isolation.

Phase B — RAG enforcement (highest risk)
3. [x] Stamp `folder_id` in `RagProcessor` payload; add a one-off backfill script for existing chunks. Done — `backfill_qdrant_folders.php` re-tagged 387 chunks (lex 92 → folder 1, conveniex 295 → folder 3). Key fix: match by `document_name` (== filename), since lex chunks use a legacy unprefixed `document_id`.
   - Success: new and backfilled chunks carry `folder_id` in Qdrant. ✓
4. [x] Thread the allowed-folder filter through `LexRetriever::retrieve()`, `VoiceContextBuilder` (retrieval + document list), and `VoiceQueryService::query()`; fail closed on empty/unresolved access. Also enforced on `voices/doc.php`, `docs.php`, `list_docs_ajax.php`.
   - Success: verified on prod — retriever null=5/folder1=5/folder999=0/empty=0; doc list null=1/folder1=1/folder999=0; restricted user with empty folder set gets a safe no-docs response without invoking the model. Superadmins/responsibles unaffected (null filter). ✓

Phase C — Voice Studio: folders + documents
5. [x] Folder tree UI in the Knowledge panel: create/rename/delete, breadcrumb, per-document move, upload into a selected folder. Endpoints under `api/admin/voices/folders/` + `documents/move.php`; UI in `voices.php` + `admin-voices.js` + `styles.css`.
   - Success: admin organizes a voice's documents into a tree; documents show and move between folders. ✓ (backend verified; visual review pending)
6. [x] Bulk folder upload (`webkitdirectory`): `upload.php` accepts a `relative_path` and recreates the tree server-side via `VoiceFoldersRepo::ensurePath`. Files still need a Process pass for RAG (no auto-enqueue yet).
   - Success: uploading a desktop folder recreates the structure and places files in the right folders. ✓

Phase D — Voice Studio: profiles + access
7. [ ] Profiles CRUD per voice.
8. [ ] Folder→profile access matrix (grant per folder; show inheritance).
9. [ ] "Access" tab: assign each user a profile in this voice.
   - Success: admin defines profiles, maps folders to profiles, and assigns users; changes take effect in retrieval.

Phase E — Reconcile Feature Permissions
10. [ ] Replace the Voices binary toggle with a profile dropdown (reads/writes `user_voice_profiles`); update `CapabilityCatalogService` and `/api/capabilities` to use the new access check; deprecate binary voice rows in `user_feature_access`.
    - Success: one source of truth; per-user overview still works; chat only recommends accessible voices.

Phase F — QA & migrate
11. [ ] End-to-end QA: fail-closed tests, a user with different profiles across two voices, bulk upload, reindex/verify Lex, no prompt leakage.
    - Success: no access leak; existing Lex users unaffected; sensitivity tiers enforced end to end.

### Suggested MVP Sequencing

A → B → C(minimal: tree + single-file upload into folder) → D → E. Bulk folder upload (step 6) and inheritance-override polish can follow once the single-file path and enforcement are proven. Phase B is the gate: nothing ships to a client until retrieval is verified fail-closed.

### UX Direction

- Voice Studio gets a tabbed voice config: `Identity` / `Knowledge (folders + docs)` / `Access (profiles + folder mapping + user assignment)`.
- Knowledge panel: left = folder tree (create/rename/move/delete, drag or move action), right = documents in the selected folder with status (processing/processed/error) and a per-folder upload + "Upload folder" action.
- Access tab: a profiles list (CRUD), a folder→profile grid (rows = folders, columns = profiles, checkboxes with an inheritance hint), and a users list with a per-user profile dropdown.
- Feature Permissions: Voices column shows a compact dropdown per voice (`No access` + profile names) instead of a checkbox; keep the All/None pattern only for gestures/features.
- Copy stays English. New layout/tree classes go in `public/assets/css/styles.css`, not inline.

## Project Status Board

- [ ] Planner: finalize shared conversations architecture and UX plan.
- [x] Executor: inspect current conversation access assumptions.
- [x] Executor: implement sharing schema and access service.
- [x] Executor: implement read-only sharing.
- [x] Executor: implement collaborative chat permissions and AI run locking.
- [ ] Executor: update sidebar grouping and shared states.
- [x] Executor: harden real-time assistant response streaming.
- [x] Planner: design voice access profiles & document folders (per-voice profiles, folder tree, fail-closed RAG filtering, Voice Studio as source of truth).
- [x] Executor: Phase A — schema + access core (migration 024, profiles/folders/assignment tables, resolver service, backfill of existing voice grants). Applied & verified on prod 2026-06-25.
- [x] Executor: Phase B — RAG enforcement (folder_id payload + backfill, allowed-folder filter through retriever/context/query, fail-closed, no prompt leakage). Applied & verified on prod 2026-06-25 (15/15 assertions).
- [x] Executor: Phase C — Voice Studio folder tree + single-file upload into folders. Backend applied & verified on prod 2026-06-25 (8/8 assertions incl. inheritance). UI deployed (visual review pending).
- [x] Executor: Phase C — bulk folder upload (webkitdirectory) recreating structure. Deployed 2026-06-25.
- [ ] Executor: Phase D — profiles CRUD, folder→profile matrix, per-voice user assignment.
- [ ] Executor: Phase E — reconcile Feature Permissions (profile dropdown, capability catalog).
- [ ] Executor: Phase F — end-to-end QA + reindex Lex.

## Current Status / Progress Tracking

- 2026-06-25 Executor: Phase C (Voice Studio document folders) deployed to PRODUCTION (commit df57da8). Backend: folder CRUD endpoints (`api/admin/voices/folders/{list,create,rename,delete}.php`), document `move.php`, `upload.php` now accepts `folder_id` or a `relative_path` (whole-folder upload recreates the tree via `VoiceFoldersRepo::ensurePath`). `RagProcessor::setDocumentFolder` re-tags a doc's Qdrant chunks on move/delete so chunks are never orphaned; folder delete reassigns docs to the parent. UI: folder tree + breadcrumb + new/rename/delete + per-document move + upload-into-folder + "Upload a folder" (webkitdirectory), built in the existing Voice Studio design system (`voices.php`, `admin-voices.js`, `styles.css`). Verified on prod with a transactional (rolled-back) smoke test: 8/8 assertions incl. folder inheritance (a profile granted on "Legal" reaches "Legal/Contracts/2024" but not the root). Created a non-superadmin test user for end-user testing: test.restricted@claara.tech / ClaaraTest2026! (user id 11). Visual UI review still pending (admin page needs a superadmin browser session). Remaining: Phase D (profiles + folder→profile matrix + per-voice user assignment UI), Phase E (Feature Permissions reconciliation).
- 2026-06-25 Executor: Phase B (RAG enforcement) applied and verified on PRODUCTION (commits 11145cc, 90d137d). RagProcessor now stamps `folder_id` on each Qdrant chunk; `QdrantClient::setPayloadByFilter` + `backfill_qdrant_folders.php` re-tagged all 387 existing chunks (lex 92→folder 1, conveniex 295→folder 3). Retrieval filters by `folder_id` (match any) and fails closed on an empty allow-list; `VoiceContextBuilder` also filters the document-name list shown in the prompt (no name leakage); `VoiceQueryService` resolves access via `VoiceAccessResolver` (superadmin/responsible → null filter = full access; profile with no folders or unavailable RAG → safe "no accessible documents" response without calling the model). The doc endpoints (`voices/doc.php`, `docs.php`, `list_docs_ajax.php`) now filter listing/serving by folder. Verified end-to-end on prod with a temporary (rolled-back) non-superadmin user: 15/15 assertions passed; prod data integrity confirmed afterwards (2 users, 3 default profiles, 0 residuals). Key gotcha: lex chunks use a legacy unprefixed `document_id`, so the Qdrant re-tag matches on `document_name` (== filename). Phases C–F (Voice Studio folder/profile/Access UI, Feature Permissions reconciliation) remain.
- 2026-06-25 Executor: Phase A applied and verified on PRODUCTION (claara.tech, DB `iaiapro_db`, MariaDB 11.4). Took a full DB backup first (`/var/backups/claara/iaiapro_db_pre024_*.sql.gz`, ~54MB). Deployed via push to `main` + `git pull` on prod (commits 3fdddde, 247b297, 6309a66). Applied migration 024 directly (NOT via migrate.php, due to schema_migrations drift) and registered its row. Hit `ERROR 1071 key too long` on `voice_folders(voice_id, path)` (VARCHAR(1000) utf8mb4) → fixed to a 191-char prefix index and re-applied idempotently. Discovered the app has no PSR-4 autoloader (explicit require_once in bootstrap.php) → registered the 3 new classes there. Ran `backfill_voice_access.php`: seeded 3 root folders + 3 Full-access profiles (lex/test-voice/conveniex), placed 4 documents into roots (lex 1, conveniex 3), mirrored 2 Lex voice grants into `user_voice_profiles`. Re-ran backfill = 0 changes (idempotent). Verified 0 documents without folder, and a resolver smoke test confirmed fail-closed behavior (superadmin → all folders; ghost/non-member → access=false, folders=[]). NOTE: prod currently has only 2 users and BOTH are superadmins, so no end user depends on voice access yet — Phase B enforcement can be turned on with minimal blast radius. Nothing is wired into retrieval yet; live behavior unchanged. Next: Phase B (RAG enforcement).
- 2026-06-25 Planner: Reviewed how Claara handles voice/document access today (binary per-voice whitelist in `user_feature_access`; single shared Qdrant collection per voice; no folders for voice docs; no document-level filtering in `LexRetriever`). Confirmed Pierre's model (per-voice profiles + folder tree + folder→profile access + user→profile assignment) is viable: Qdrant payload filtering already exists, ingestion/admin pipelines are clear integration points, and `ContextDocsRepo` is already column-adaptive. Confirmed product decisions: profiles per voice, folder tree with grant-inherits-down, bulk folder upload via `webkitdirectory`, profiles as single source of truth for voice access, and Voice Studio (new "Access" tab) as the management home with Feature Permissions' Voices column reduced to a profile dropdown over the same table. Documented full plan (data model, integration points, 6-phase breakdown, MVP sequencing, UX) in "Active Feature Planning: Voice Access Profiles & Document Folders". Phase B (fail-closed RAG filtering) flagged as the gate before any client ship. Awaiting user (Planner) go-ahead to start Phase A in Executor mode.
- 2026-06-17 Executor: Prepared a commercial, non-technical feature inventory request for Claara. Local code review confirmed current commercial modules: central chat, integrated RAG voices, guided gestures, document/file support, web search, image generation, shared conversations, voice feedback loop, organization/access management, and commercial lead/content workflows. Local DB connection is not available from this environment, so production-published dynamic voice names beyond the code-confirmed Lex voice still need server validation if the dossier must name every live voice.
- 2026-06-17 Executor: Updated the commercial feature inventory dossier to English for Pierre while keeping the non-technical, commercial positioning and the emphasis on chat-to-voice integration and gestures.
- 2026-06-17 Executor: Generated a PDF version of the English commercial dossier at `output/pdf/claara-current-features-commercial-dossier.pdf`. Rendered pages to PNG and visually checked representative pages for legibility, margins, and page endings.
- 2026-06-09 Planner: Scratchpad cleaned and rewritten in English. Active planning focus is shared conversations and collaborative chat.
- 2026-06-09 Executor: Added migration `023_shared_conversations.sql` and `ConversationAccessRepo`. The repo resolves owner, direct user share, department share, `Can view`, `Can chat`, and manage permissions. PHP lint passed for the new repo and bootstrap.
- 2026-06-09 Executor: Implemented first usable sharing cut. Added share target/shares APIs, Share modal, shared sidebar sections, read-only composer state, message read access for shared conversations, file serving for viewers, and backend `Can chat` checks for chat, streaming, voice-query, file upload, and regeneration. PHP lint and extracted JS syntax check passed. Local DB is not reachable, so migration validation must happen on the server.
- 2026-06-09 Executor: Added single-AI-run lock using `conversations.ai_status`, `ai_started_at`, and `ai_locked_by_message_id`. Standard chat, streaming chat, and inline voice queries now reject a second AI run while Claara is already responding in the same conversation. Frontend SSE error handling now surfaces server errors in the assistant bubble.

- 2026-06-09 Executor: Fine-grained actions pass. Verified rename/delete/move/favorite remain owner-only (all SQL scoped by `user_id`). Decision (user-confirmed): `Can chat` users may regenerate. Added the AI lock to `chat-regenerate.php` and `chat-regenerate-full.php` (409 `conversation_busy` + shutdown release). Added integrated "Claara is responding" UI: `activity.php` now returns `ai_status` (stale-aware, 180s), frontend polling shows a pulsing banner, disables the composer while busy, and a 409 on send now restores the user's text instead of showing a red error bubble. Also: activity poll only calls `loadConversations()` on the first notice, and polling continues while the busy banner is visible so it always clears. PHP lint and extracted JS syntax check passed.

- 2026-06-09 Executor: Fixed phantom "Ask Open voice" button. `extractCapabilityRoutes` matched the `/voices/doc.php` substring inside `/api/voices/doc.php?...` source links from voice answers, inferring slug `doc` ("Voice not found" on click). Added a `(?<!\/api)` lookbehind plus a filter excluding `doc.php`/`docs.php`/`list_docs_ajax.php` routes. JS syntax check passed.

- 2026-06-11 Executor: Hardened SSE streaming for assistant responses. The endpoint now disables common buffering/compression paths, sends an initial SSE comment after auth, and flushes consistently. OpenRouter streaming parsing now ignores SSE comments, accepts `data:` events without requiring a space, caps diagnostic raw bodies, enables low-latency cURL options, and surfaces mid-stream OpenRouter errors. Frontend parsing now preserves partial SSE lines across network chunks. PHP lint, extracted JS syntax check, and direct OpenRouter streaming tests passed locally and on production.

## Executor's Feedback or Assistance Requests

- 2026-06-17: Need production/server access details only if Pierre's dossier must list the exact set of currently published dynamic voices, because the local DB connection fails from this environment.

## Lessons

- Keep scratchpad concise. Archive old history in git instead of preserving every completed implementation detail here.
- Before database work, inspect production schema and existing migration drift. Production has known `schema_migrations` drift from older manual migrations.
- For new migrations, use `docs/migrations/*.sql`; `.gitignore` previously hid migration files outside the documented path.
- In migrations with foreign keys, match existing column types exactly. `users.id`, `conversations.id`, and many related IDs are `BIGINT UNSIGNED`.
- Avoid unnecessary foreign keys against slug-based dynamic entities when the application already treats slugs as durable identifiers.
- For long-running jobs, command-line tools such as `ffprobe` and `ffmpeg` need explicit timeouts.
- For OpenRouter image generation, aspect ratio and resolution belong in `image_config`, not in prompt text.
- Verify Iconoir icon names before using new icons; missing icons render as square placeholders.
- Sidebar/shared layout classes should live in `public/assets/css/styles.css`, not page-local inline styles.
- Avoid creating a foreign key from `conversations.ai_locked_by_message_id` to `messages.id`; `messages` already depends on `conversations`, and a reverse FK would create an unnecessary cycle.
- SSRF: validar la URL antes del fetch no basta. `file_get_contents`/cURL siguen redirecciones sin revalidar (302 → IP interna) y resuelven DNS de nuevo (DNS rebinding). En `ContentExtractor::fetchUrlSafely` se siguen redirecciones manualmente revalidando cada salto y se fija la IP validada con `CURLOPT_RESOLVE`. `extractFromUrl` (src/Audio/ContentExtractor.php) es el único punto que descarga URLs de usuario (gestos podcast y SOP).
- Streaming responses over OpenRouter use SSE with `stream: true`; chunks arrive in `choices[0].delta.content`, and comment lines such as `: OPENROUTER PROCESSING` must be ignored.
- Prod deploy mechanism: push to GitHub `dvdgp9/Claara` `main`, then `git pull --ff-only origin main` on the server. App lives at `/home/dvdgp/web/claara.tech/public_html` (HestiaCP). Prod DB is `iaiapro_db` (NOT `ebonia_db` as in the sample .env); local sample .env points elsewhere.
- Prod access: SSH host alias `iaiapro` (91.98.155.109, mail.claara.tech), user `codex`. `sudo -n` works as ROOT only; `sudo -n -u dvdgp ...` requires a password. Run one-off PHP/DB tasks via `sudo -n` (root reads .env fine). MySQL CLI is `mariadb`/`mariadb-dump` and auths via root unix socket under sudo.
- After a root `git pull` on prod, restore ownership of touched code subtrees with `chown -R dvdgp:dvdgp .git src docs scripts .cursor`. Do NOT blanket-chown the app root: `vendor/` is `root:root` and the app dir group is `www-data`.
- schema_migrations drift is real on prod: 005,006,007,008,018,021 are applied but NOT registered. Never run the full `migrate.php` on prod — it would try to re-apply them and fail. Apply new migrations directly and `INSERT IGNORE` their `schema_migrations` row.
- The app has NO PSR-4 autoloader. New classes must be added as explicit `require_once` lines in `src/App/bootstrap.php` or they are "class not found" (even though the file exists).
- MariaDB/InnoDB index key limit is 3072 bytes. A composite index on a `VARCHAR(1000)` utf8mb4 column (4 bytes/char) overflows it; use a prefix index, e.g. `KEY (voice_id, path(191))`.
- Voice document folders: `voice_folders.path` is a materialized id-path including self (root `/1/`, child `/1/5/`). Descendants (inclusive) of folder G = rows where `path LIKE CONCAT(G.path,'%')`; this is how `VoiceAccessResolver` expands a profile's folder grants down the tree. Access is FAIL CLOSED: empty folder set = no documents, never "no filter".
- Qdrant chunk `document_id` schemes are inconsistent across voices: lex (legacy) uses the bare filename-without-ext, conveniex uses `{slug}_{base}`. The reliable join key to a DB document is `document_name`, which equals the stored `filename` in both. Filter Qdrant payload by `document_name` when re-tagging, not `document_id`.
- Voice access enforcement entry points (all must use `VoiceAccessResolver`, fail closed): `VoiceQueryService::query()` is the single query chokepoint (both `voices/chat.php` and `capabilities/voice-query.php` route through it); plus `voices/doc.php`, `voices/docs.php`, `voices/list_docs_ajax.php` for listing/serving. `VoiceContextBuilder::listDocuments($allowedFolderIds)` filters by folder via `ContextDocsRepo::accessibleFilenameSet()` (filename is the filesystem↔DB join key). Superadmins/voice-responsibles pass a null filter = unrestricted.

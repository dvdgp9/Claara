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

6. [ ] Add single-AI-run lock.
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

## Project Status Board

- [ ] Planner: finalize shared conversations architecture and UX plan.
- [x] Executor: inspect current conversation access assumptions.
- [x] Executor: implement sharing schema and access service.
- [x] Executor: implement read-only sharing.
- [ ] Executor: implement collaborative chat permissions and AI run locking.
- [ ] Executor: update sidebar grouping and shared states.

## Current Status / Progress Tracking

- 2026-06-09 Planner: Scratchpad cleaned and rewritten in English. Active planning focus is shared conversations and collaborative chat.
- 2026-06-09 Executor: Added migration `023_shared_conversations.sql` and `ConversationAccessRepo`. The repo resolves owner, direct user share, department share, `Can view`, `Can chat`, and manage permissions. PHP lint passed for the new repo and bootstrap.
- 2026-06-09 Executor: Implemented first usable sharing cut. Added share target/shares APIs, Share modal, shared sidebar sections, read-only composer state, message read access for shared conversations, file serving for viewers, and backend `Can chat` checks for chat, streaming, voice-query, file upload, and regeneration. PHP lint and extracted JS syntax check passed. Local DB is not reachable, so migration validation must happen on the server.

## Executor's Feedback or Assistance Requests

- None currently.

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

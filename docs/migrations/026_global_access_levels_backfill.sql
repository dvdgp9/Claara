-- Backfill for migration 026 (Global access levels). Date: 2026-06-26
-- FAIL-CLOSED CUTOVER: no user may gain access they didn't already have.
--
--   1. Every user gets the default global level (for future 'level' voices).
--   2. The pre-existing voices switch to explicit 'list' mode, seeded with
--      exactly their current members, so access is unchanged at cutover.
--      Superadmins / voice responsibles keep their bypass in the resolver.
--   3. Folder minimums (all NULL on prod today) are cleared: under 'list' mode
--      they are moot, and they point at per-voice profiles slated for removal.
--
-- Idempotent. Step 2 is scoped to the voice ids that existed at cutover
-- (1,2,3) so re-running never flips voices created later in 'level' mode.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Default global level for every user without one.
UPDATE users u
JOIN access_levels al ON al.is_default = 1
SET u.access_level_id = al.id
WHERE u.access_level_id IS NULL;

-- 2. Pre-existing voices -> explicit list mode (one-time cutover).
UPDATE voices SET access_mode = 'list' WHERE id IN (1, 2, 3);

-- 3. Seed each voice's allow-list from the current per-voice assignments.
INSERT IGNORE INTO voice_access_list (voice_id, user_id)
SELECT voice_id, user_id FROM user_voice_profiles;

-- 4. Clear folder minimums (moot under list mode; reference soon-dropped table).
UPDATE voice_folders SET required_level_id = NULL WHERE required_level_id IS NOT NULL;

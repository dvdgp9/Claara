# Claara Instance Backup and Restore Verification

## Scope

The instance backup contains:

- Transactionally consistent MariaDB dump, including routines, triggers, and events.
- Persistent application storage plus per-file SHA-256 manifest.
- Root-only application environment file.
- Hestia web/TLS configuration archive.
- One downloadable Qdrant collection snapshot per collection.
- Git/source and runtime-version metadata.
- Whole-backup SHA-256 manifest and explicit `COMPLETE` marker.

The backup remains under `/var/backups/claara/instance-platform` with root-only permissions. It currently contains secrets and is therefore not suitable for unencrypted external transfer.

## Backup Command

```bash
sudo bash scripts/ops/backup_instance.sh \
  --instance claara-default \
  --app-root /home/dvdgp/web/claara.tech/public_html \
  --db-name iaiapro_db \
  --web-config-root /home/dvdgp/conf/web/claara.tech
```

## Restore Verification Command

```bash
sudo bash scripts/ops/verify_restore_instance.sh \
  --backup-dir /var/backups/claara/instance-platform/<backup-id>
```

Verification restores into explicitly prefixed temporary resources:

- MariaDB database `claara_restore_verify_*`.
- Directory `/var/tmp/claara-restore-verify.*`.
- Qdrant collections `restore_verify_*`.

It verifies backup checksums, database table/key counts and table integrity, per-file storage hashes, Qdrant point/vector counts, and configuration archives. The temporary resources are removed by an exit trap whether verification succeeds or fails. Production database, files, and collection names are never restore targets.

## Operational Rules

- Never accept unresolved globs or unvalidated database/collection identifiers as restore targets.
- Never test a restore by overwriting production.
- A backup is usable only after a successful isolated restore verification.
- Encrypt before copying off-host.
- Add retention and off-host scheduling only after the first verified baseline.
- Record the backup id, checksum result, restore result, and cleanup result in the project scratchpad.

## Verified Baseline — 2026-08-15

- Backup: `claara-default-20260815T082139Z` (93 MB), stored root-only under `/var/backups/claara/instance-platform/`.
- Integrity: all whole-backup SHA-256 checks passed.
- MariaDB restore: 41 tables; 3 users, 187 messages, and 4 context documents; table checks passed.
- File restore: archive extraction and per-file SHA-256 verification passed.
- Qdrant restore: `lex_knowledge_base` restored with 92 points and `voice_conveniex` with 295 points; both use vector size 4096.
- Cleanup: zero temporary databases, directories, or Qdrant collections remained.
- Production verification: database and Qdrant counts were unchanged, both collections remained green, and the anonymous HTTP smoke baseline passed 17/17.

## Qdrant Reference

The workflow uses Qdrant collection-level snapshots and restores them to new collections with `priority=snapshot`, following the official snapshot procedure:

- https://qdrant.tech/documentation/operations/snapshots/

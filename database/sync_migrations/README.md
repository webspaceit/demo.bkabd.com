# Demo schema sync migrations

This directory is the allow-listed migration source for the dashboard's **Database Sync** page. For local use, configure a dedicated target connection to the demo database; the app's normal local database connection is not used by the sync service.

- Add a dated PHP file such as `2026_10_24_add_example_column.php`.
- It must return an array of individual SQL statements.
- Only additive schema DDL is accepted: `CREATE TABLE IF NOT EXISTS` or `ALTER TABLE ... ADD ... IF NOT EXISTS` for columns and indexes.
- Do not put student/application data operations, destructive statements, multi-statement SQL, or schema-qualified table names here.
- Review and test a migration against a demo database copy and back up the actual demo database before applying it.
- Applying a migration uses only the separately configured demo connection. The exam connection is used only for read-only schema metadata comparison.
- `2026_10_26_center_reference_sync` makes `centers.user_id` nullable on demo so center references can be synchronized without manufacturing login accounts. This migration is intentionally limited to that column definition; back up demo before applying it.

The dashboard tracks applied filenames in `codex_schema_migrations`. MySQL DDL may commit statement by statement, so keep migrations small and make each statement safe to retry on the production MySQL/MariaDB version. Local application is disabled by default; it requires `DB_SYNC_ALLOW_LOCAL=true` as well as the normal demo target checks.

The schema comparison intentionally ignores the known differences `login_logs`, `unions.id`, and the `unions_name_unique` index. These are displayed as skipped and are not proposed for syncing.

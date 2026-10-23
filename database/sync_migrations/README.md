# Demo schema sync migrations

This directory is the allow-listed migration source for the dashboard's **Database Sync** page.

- Add a dated PHP file such as `2026_10_24_add_example_column.php`.
- It must return an array of individual SQL statements.
- Only additive schema DDL is accepted: `CREATE TABLE IF NOT EXISTS` or `ALTER TABLE ... ADD ... IF NOT EXISTS` for columns and indexes.
- Do not put student/application data operations, destructive statements, multi-statement SQL, or schema-qualified table names here.
- Review and test a migration against a demo database copy and back up the actual demo database before applying it.
- Applying a migration updates only the database configured as this app's current database. The separate exam connection is used only for schema metadata comparison.

The dashboard tracks applied filenames in `codex_schema_migrations`. MySQL DDL may commit statement by statement, so keep migrations small and make each statement safe to retry on the production MySQL/MariaDB version.

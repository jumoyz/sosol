# Schema Alignment Migrations (2025-09-21)

These migrations align the live database with the refactored SOL group domain model.

## Migrations Added

1. `2025_09_21_0001_align_sol_participants.sql`
   - Ensures columns: `role`, `payout_position`, `join_date`, `payout_received` exist.
   - Backfills `payout_position` using a window row number on `created_at` if NULL.
   - Backfills `join_date` with `created_at` if NULL.
   - Normalizes empty / NULL `role` to `member`.
   - Adds a unique key on `(sol_group_id, user_id)` if missing.

2. `2025_09_21_0002_align_sol_invitations.sql`
   - Renames legacy columns (`inviter_id` -> `invited_by`, `invitee_id` -> `invited_user_id`) via add/backfill/drop pattern.
   - Adds unique key `(sol_group_id, invited_user_id)`.
   - Adds indexes for query performance (`invited_user_id`, `invited_by`).

## Applying the Migrations

### 1. Backup First
```sql
-- From MySQL shell
SET SESSION sql_notes = 0; -- optional to suppress warnings

-- Logical backup
mysqldump -u <user> -p <database> > backup_pre_alignment.sql
```

### 2. Review Environment
Confirm MySQL version (some scripts rely on `IF [NOT] EXISTS` in `ALTER TABLE` which requires MySQL 8.0.29+).
If you run an older version, manually check existence and remove the clauses.

Check current structure:
```sql
SHOW CREATE TABLE sol_participants \G
SHOW CREATE TABLE sol_invitations \G
```

### 3. Apply Scripts (Recommended Order)
```sql
SOURCE database/migrations/2025_09_21_0001_align_sol_participants.sql;
SOURCE database/migrations/2025_09_21_0002_align_sol_invitations.sql;
SOURCE database/migrations/2025_09_21_0003_unique_sol_group_payout_position.sql;
```

### 4. Validate Post-migration
```sql
SELECT id, role, payout_position, join_date FROM sol_participants LIMIT 5;
SELECT sol_group_id, invited_by, invited_user_id, status FROM sol_invitations LIMIT 5;
SHOW INDEX FROM sol_participants;
SHOW INDEX FROM sol_invitations;
```

### 5. Clean Up / Cache Refresh
- Restart PHP-FPM / clear opcode caches if you use them.
- Flush any app-level schema caches if present.

## Rollback Strategy
Because we are adding columns (non-destructive) and backfilling, rollback would mean:
1. Dropping the newly added columns (if you must revert).
2. Re-adding legacy columns (if previously removed) with default values.

Keep the pre-alignment dump to restore if necessary:
```bash
mysql -u <user> -p <database> < backup_pre_alignment.sql
```

## Next Recommended Enhancements
- Add foreign key from `sol_participants.role` to a `roles` lookup table (optional).
- Add a check constraint (or application-level validation) to ensure `payout_position >= 1`.
- Unique composite index on `(sol_group_id, payout_position)` now added via `2025_09_21_0003_unique_sol_group_payout_position.sql`.
- Add `accepted_at` / `responded_at` timestamps to `sol_invitations`.
- Create a dedicated migrations runner (PHP CLI script) to log applied migrations.
   * Implemented: `php tools/migrate.php` (use `--pretend` to list pending).

## Troubleshooting
| Issue | Cause | Resolution |
|-------|-------|-----------|
| Error: Duplicate key when adding unique index | Existing duplicate participant rows | Identify duplicates: `SELECT sol_group_id, user_id, COUNT(*) c FROM sol_participants GROUP BY 1,2 HAVING c>1;` then consolidate |
| `IF NOT EXISTS` syntax error | MySQL < 8.0.29 | Remove `IF NOT EXISTS` and manually conditionalize via `SHOW COLUMNS` checks |
| Window function error (`ROW_NUMBER()`) | MySQL < 8.0 | Use session variable technique to emulate row numbers |

---
Prepared on 2025-09-21 for alignment with refactored SOL group logic.

# Slice 21 — Data export and account data portability

## Slice Contract

### Status
IMPLEMENTED

### Goal
Let a user take their data with them. Nothing in the roadmap currently provides export, and daily
real-world use should not begin without it.

### In scope
- A complete, machine-readable export of the signed-in user's own data: ToDos, tags, Projects and
  sections, Notebooks and sections, Libraries, relationships, objectives and results, review
  schedules, and the user's own activity history.
- A documented, versioned export format with a stable schema identifier.
- A client action that requests the export and a documented operator command for the same job.
- A documented restore procedure, even if restore is initially operator-driven.

### Out of scope
- Importing data from other products.
- Exporting another user's data, or data visible only through a capability grant.
- Scheduled or automatic backups of the database itself, which is a deployment concern.

### Dependencies
All domain slices (1–13). Slice 18 if capability-shared resources are to be considered, which this
contract deliberately excludes.

### Design constraints
- The export is scoped to rows the user owns. Authorization is evaluated by the existing services;
  export must not become a second, weaker read path.
- Password hashes, tokens, and other credential material are never included.
- Generation must be bounded: streaming or chunked output, never an unbounded in-memory array.

### Test plan
- PHPUnit: the export contains every owned record type; it contains no other user's rows even when a
  capability grant exists; it contains no credential fields; an unauthenticated request is denied;
  the format matches its documented schema version.
- A memory/bounds test asserting the export does not load the entire dataset at once.
- Browser test for requesting an export and receiving a downloadable result.
- A round-trip test for the documented restore procedure.

### Risks
- Export is the single highest-value target in the system; an authorization mistake here discloses
  everything a user has. The denied paths matter more than the happy path.

  ## Version 1 JSONL contract

  `DataPortabilityService::SCHEMA` is `urn:cheat-to-do:export:1`. UTF-8 JSONL contains
  one JSON object per newline-terminated line, with a maximum of 1,048,576 bytes per line
  (including the newline). The first line is `{"schema":"urn:cheat-to-do:export:1"}`.
  Each subsequent record is `{"type":"todos","data":{...}}`, using the table identifiers below.
  The last line is `{"end":true,"records":N}`, counting data records only. The footer is mandatory:
  missing footers, wrong counts, trailing records, truncated lines, and malformed JSON are rejected.
  No account identity, password hash, session, token, or credential column is exported.

  The complete field allowlists are:

  | Record type | Fields |
  | --- | --- |
  | `projects`, `notebooks`, `libraries` | `id`, `name`, `description`, `created`, `modified` |
  | `project_sections` | `id`, `project_id`, `name`, `position`, `created`, `modified` |
  | `notebook_sections` | `id`, `notebook_id`, `name`, `position`, `created`, `modified` |
  | `tags` | `id`, `name`, `created`, `modified` |
  | `todos` | `id`, `title`, `notes`, `status`, `created`, `modified`, `project_section_id`, `notebook_section_id`, `archived_at`, `trashed_at`, `previous_status`, `last_reviewed_at`, `next_review_at`, `review_interval_days`, `parent_todo_id`, `terminal_objective`, `objective_satisfied_at`, `result_summary` |
  | `todos_tags` | `todo_id`, `tag_id` |
  | `related_todos` | `todo_id`, `related_todo_id`, `created` |
  | `libraries_projects` | `library_id`, `project_id`, `created` |
  | `libraries_notebooks` | `library_id`, `notebook_id`, `created` |
  | `activity_records` | `id`, `action`, `subject_type`, `subject_id`, `context_type`, `context_id`, `metadata`, `created` |

  All fields are present, including nullable fields. IDs and numeric fields are JSON integers.
  Dates use PostgreSQL's stored `YYYY-MM-DD HH:MM:SS` representation with an optional fractional
  second component of one to six digits; restore retains these values,
  statuses, objective/results, review schedules, and activity timestamps without invoking mutation
  callbacks or generating additional history.

  Ownership is mandatory, not merely a read capability. Projects, Notebooks, and Libraries are
  checked through `CapabilityService::ownerIdFor()`, and history uses `ActivityRecorder::forActor()`.
  Other rows are restricted through their owner or owned parent; every relationship and membership
  requires both endpoints to be owned. Archived and trashed ToDos are included. ToDo section and
  parent pointers to missing or other-owned records are replaced with null. Capability grants and
  cross-context requests are intentionally excluded: they are inter-account authorization/workflow
  records, not owned workspace records. They are not recreated on restore.

  History contains only the exporting actor's entries. Missing, foreign, or excluded subjects are
  represented with `subject_id: 0`; unsafe contexts become `context_type: null, context_id: null`.
  Original action and subject type remain. History metadata reuses `ActivityRecorder::redact()` and
  preserves a bounded whitelist: lifecycle `status`/`previous_status`, known `capability`, changed
  `fields`, owned reference fields, and owned `member`, `resource`, or `target` type/id pairs.
  Credential keys, user IDs, unknown keys, arbitrary text/nesting, and foreign/missing references
  are dropped; empty metadata is null. Metadata larger than 8,192 stored bytes is omitted by the
  database query before fetching. Restore validates this whitelist, remaps reference IDs, and
  preserves the safe details and redactions.

  Generation runs in its own PostgreSQL `REPEATABLE READ` transaction, using keyset pagination
  and fixed batches of at most 100 records, including composite-key membership/relationship pages.
  The service writes to a caller-owned stream via `writeExport(int $userId, resource $stream): void`.
  The HTTP endpoint completes generation in a private temporary stream before returning a Cake
  stream response, avoiding partially delivered exports on generation failure. Keep downloaded files
  private: credential-column exclusions do not make user-written notes or titles non-sensitive.

  The authenticated `GET /api/export` endpoint returns an attachment named
  `cheat-to-do-export-v1.jsonl`. The client offers a plain download link to that route, using the
  existing signed-in session; it does not fetch the entire export into a JavaScript array or Blob.
  Unauthenticated access is denied by the existing authentication middleware.

  The batch bound limits record counts, not the size of user-written text. Records are not split
  across lines: a single record larger than the line limit causes export generation to fail rather
  than emitting a file that cannot be restored. Restore reads at most the line limit before rejecting
  an oversized record. Activity metadata is fetched only when its stored size is at most 8,192
  bytes, so unbounded historical metadata cannot enlarge a portable activity record.

  ## Operator export and restore

  ```sh
  bin/cake export_user_data owner@example.com /secure/path/workspace.jsonl
  bin/cake restore_user_data target@example.com /secure/path/workspace.jsonl
  ```

  Export resolves an existing account email, creates a new file exclusively with mode `0600`, never
  overwrites a file (including a symlink), and removes its partially created file on failure.
  Choose a private destination directory and retain the export securely. No new dependencies are needed.

  Restore first requires an **existing empty target account**, provisioned through the normal account
  operator workflow. Existing credentials and identity are unchanged. Any owned workspace records
  or actor history cause rejection; restore never merges accounts or deletes existing rows.
  The input stream need not be seekable. All input is validated and staged in PostgreSQL temporary
  tables, with database-backed source-to-target ID maps; no whole-file PHP arrays are constructed.
  Restore accepts only this schema and exactly the fields above, rejects unknown fields (including
  credentials), invalid values, duplicate records, missing/cross-file references, and cyclic parents.
  It remaps IDs, inserts dependencies first, then resolves self-parent pointers in a second bounded
  pass. The entire restore is transactional: failure rolls back every inserted record and leaves
  all other users' data untouched. PostgreSQL sequences may advance after a failed transaction,
  which is normal and does not represent persisted workspace data.

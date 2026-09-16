# Slice 12 — Activity History and Audit Foundation

## Slice Contract

### Goal
Record important changes in a consistent, reusable, queryable activity stream so a user can see
recent actions and understand how an item changed.

### In scope
- `activity_records` table and `ActivityRecord` entity.
- `ActivityRecorder` — the single, deliberately small authority for writing and reading activity.
- Explicit recording calls at the end of successful mutations for ToDos (create, update, lifecycle
  transition, permanent delete, tag attach/detach), Projects, Notebooks, Libraries (create, delete,
  member add/remove), capability grants/revocations and cross-context request transitions.
- `GET /api/activity` with filtering and mandatory pagination.
- Metadata redaction and truncation.

### Out of scope
- Immutable compliance-grade audit ledger, external SIEM, full event sourcing, reconstructing object
  state from events, and any conversion of the application to an event-driven architecture.

## Delivered behaviour
Every recorded mutation produces exactly one row containing the actor, a normalized action name, the
subject type/id, an optional context type/id pair and a small redacted JSON metadata payload. Read
requests produce no rows. The caller can list their own history newest-first, filtered by
`subject_type`, `subject_id` and/or `action`, and paginated.

## Schema changes
Migration `20260916210000_CreateActivityRecords` creates `activity_records`:

| Column | Notes |
| --- | --- |
| `id` | identity primary key |
| `actor_user_id` | FK → `users.id`, `ON DELETE CASCADE` |
| `action` | normalized, ≤ 64 chars |
| `subject_type`, `subject_id` | what changed |
| `context_type`, `context_id` | optional surrounding context |
| `metadata` | redacted JSON text, nullable |
| `created` | set once on insert |

Constraints and indexes:
- `activity_records_subject_type_chk` restricts `subject_type` to the seven known kinds.
- `activity_records_context_chk` requires `context_type` and `context_id` to be supplied together.
- `activity_records_actor_idx (actor_user_id, created)` serves the primary listing query.
- `activity_records_subject_idx (subject_type, subject_id)` serves per-object history.
- `activity_records_context_idx (context_type, context_id, created)` serves context history.

A denormalized subject pair was chosen over per-subject tables because activity is read as one
stream; it matches the flat model already established by ADR 0002.

## API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `GET` | `/api/activity` | Caller's own activity, newest first |

Query parameters: `page` (default 1), `limit` (default 20, capped at 100), `subject_type`,
`subject_id`, `action`. Malformed values return `400`; anonymous callers receive `401`.

## Authorization rules
Visibility is deliberately narrow: an actor sees only the activity rows they themselves produced.
No capability, Library membership or ownership relationship widens that view, so activity can never
leak the existence, name or detail of an object the caller cannot otherwise see. Records are not
mass assignable at all — `ActivityRecord::$_accessible` marks every field `false` and only
`ActivityRecorder` writes rows.

## Failure policy
Activity recording is an explicit call placed after the domain mutation has succeeded, inside the
same request. A recording failure raises rather than being silently swallowed, so a broken history
write is visible instead of producing a misleading partial audit trail. Recording is deliberately
*not* wrapped around the domain transaction: history is supporting evidence, not a precondition, and
no domain operation is rolled back by history logic. Deleting a subject does not delete its history;
history rows outlive their subjects and are listed by id, never by dereferencing the subject.

## Shared components
- `ActivityRecorder` (`record()`, `forActor()`, plus the public `normalizeAction()`/`redact()`
  helpers the tests exercise) — cross-cutting and justified by the slice contract.
- `Api\AppController::activity()` — one accessor, mirroring the existing `capabilities()` accessor,
  so no controller constructs its own recorder or re-implements policy.

## Security review
- Metadata keys `password`, `token`, `secret`, `api_key`, `authorization` and `credential` are
  dropped before serialization, at every nesting level.
- String values are truncated to 255 characters; objects and resources are dropped entirely, so no
  unexpected payload can be serialized into history.
- Serialization uses `JSON_THROW_ON_ERROR` and converts failures into a domain error.
- Cross-user history is invisible; there is no endpoint that reads another actor's rows.
- No client input can set `actor_user_id` — it always comes from the authenticated session.

## Anti-bloat / reuse review
No event system, observer layer, behaviour or ORM hook was introduced. Recording is seven lines of
explicit calls spread across existing controllers, reusing the existing `respond()`/`requireUserId()`
conventions and the existing pagination shape. No new dependency was added.

## Tests added
- `tests/TestCase/Service/ActivityRecorderTest.php` — 22 tests: persistence, optional context,
  action normalization, empty/overlong action rejection, unknown subject/context type rejection,
  partial-context rejection, redaction (flat and nested), truncation, unsupported value dropping,
  actor scoping and ordering, each filter, empty-filter handling, malformed filter rejection and
  redaction idempotence.
- `tests/TestCase/Controller/Api/ActivityControllerTest.php` — 22 tests: anonymous denial, empty
  list, recording for ToDo create/update/lifecycle/tag attach/detach/permanent delete, Project and
  Notebook creation, Library membership add/remove, capability grant/revoke, cross-context request
  send and reject (recorded against the acting user only), read-only requests producing no rows,
  no duplicate rows for a single mutation, cross-user history hidden, pagination, limit capping,
  malformed `page`/`limit`/`subject_type`/`subject_id`, history surviving subject deletion and
  sensitive metadata never being exposed.
- The `app.ActivityRecords` fixture was added to every existing test that mutates, which is itself
  the regression proof that lifecycle, tagging, relationship, Library, capability and cross-context
  workflows still complete correctly with recording in place.

## Test results
```text
549 tests, 1299 assertions — OK
PHPCS — no errors
PHPStan — [OK] No errors
```

## Migration verification
`bin/cake migrations rollback --connection test --target 20260916200000` removed `activity_records`
(`to_regclass` returned null); `bin/cake migrations migrate --connection test` recreated it with the
primary key, three indexes, both check constraints and the cascading actor foreign key verified in
PostgreSQL. Both `default` and `test` connections are migrated.

## Known limitations
- Activity is visible only to the actor. A future slice may widen this to context participants, which
  will require a capability-based visibility rule rather than the current actor filter.
- Edits record only the names of the fields that changed, not before/after values, to avoid storing
  user content in a second place.

## Deferred work
- Per-object history endpoints (`/api/todos/{id}/activity`) once dashboard needs demand them.
- Retention/pruning policy.

## Slice Completion Report

All Slice 12 scope is implemented and verified. Important mutations across every previously approved
slice produce consistent, safe, queryable activity records; read-only traffic produces none; metadata
is redacted and truncated; and cross-user history is not reachable. Full PHPUnit, PHPCS, PHPStan and
migration up/down gates pass.

Slice Decision: APPROVED

Reason:
All required tests and quality gates pass. Activity recording, redaction, pagination and actor-scoped
visibility are verified. No blocking security, data-integrity, architecture, scope, or regression
issue remains.

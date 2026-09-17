# Slice 6 — Lifecycle, Archive, Trash, Restore

## Slice Contract

### Goal
Give every ToDo an explicit, server-authoritative lifecycle with archive, trash, restore and
permanent delete, so that removal is reversible by default and destructive deletion is a separate,
deliberate action.

### In scope
- Lifecycle statuses: `inbox`, `active`, `done`, `archived`, `trashed`.
- Lifecycle timestamps `archived_at` / `trashed_at` and `previous_status` for restore.
- A single transition authority (`App\Service\TodoLifecycleService`).
- Lifecycle API endpoints and default-list exclusion of hidden statuses.
- Database CHECK constraints keeping status and timestamps consistent.

### Out of scope
- Lifecycle for Projects, Notebooks, Tags or Sections (later slices).
- Automatic trash expiry / retention jobs.
- Activity history (Slice 12).

### Schema changes
Migration `20260916150000_AddTodoLifecycleFields`:
- `todos.previous_status` (nullable string).
- `todos.archived_at`, `todos.trashed_at` (nullable timestamps).
- Index on `(user_id, status)` for default-list and status filtering.
- `todos_status_chk`: status must be one of the five allowed values.
- `todos_archived_at_chk` / `todos_trashed_at_chk`: the timestamp is set if and only if the
  matching status is active.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `POST` | `/api/todos/{id}/activate` | → `active` |
| `POST` | `/api/todos/{id}/complete` | → `done` |
| `POST` | `/api/todos/{id}/archive` | → `archived` |
| `POST` | `/api/todos/{id}/trash` | → `trashed`, records `previous_status` |
| `POST` | `/api/todos/{id}/restore` | trashed → previous status |
| `DELETE` | `/api/todos/{id}/permanent` | permanently deletes a trashed ToDo |

- `GET /api/todos` hides `archived` and `trashed` unless `status` explicitly requests them.
- `POST`/`PATCH /api/todos` accept only the directly assignable statuses `inbox` and `active`;
  `done`, `archived` and `trashed` require the explicit lifecycle action (`409 Conflict`).
- The ToDo serializer now exposes `archived_at` and `trashed_at`.

### Transition matrix
| From \ To | inbox | active | done | archived | trashed |
| --- | --- | --- | --- | --- | --- |
| inbox | — | ✔ | ✔ | ✔ | ✔ |
| active | ✖ | — | ✔ | ✔ | ✔ |
| done | ✖ | ✔ | — | ✔ | ✔ |
| archived | ✖ | ✔ | ✖ | — | ✔ |
| trashed | ✖ (restore only) | ✖ | ✖ | ✖ | — |

Trashed ToDos leave the trash only through `restore`, which returns them to `previous_status`
(falling back to `inbox` when unknown).

---

## Slice Completion Report

### Delivered behaviour
All contracted behaviour was delivered: lifecycle columns and constraints, the transition service,
six lifecycle endpoints, default-list exclusion, restricted direct status assignment, and lifecycle
fields in the API payload.

### Shared components
- **Added:** `App\Service\TodoLifecycleService` — the only place transition rules, timestamp rules
  and restore semantics exist. Controllers translate its `DomainException` into `409 Conflict`.
- **Reused:** existing ownership resolution (`fetchOwnedTodoOrFail`), API envelope, error renderer,
  tag containment helper `TodosTable::loadTags()`.
- **Intentionally local:** the controller's status-assignment guard, which is an API-layer input
  policy rather than a domain rule.

### Tests added
- `tests/TestCase/Service/TodoLifecycleServiceTest.php`: full 25-cell transition matrix via data
  providers, timestamp rules, restore semantics (including restore of an archived-then-trashed
  ToDo), permanent-delete preconditions, relationship/tag survival, join-row cleanup, and direct
  database CHECK-constraint tests.
- `tests/TestCase/Controller/Api/TodosControllerTest.php`: all six endpoints, repeated transitions,
  invalid transitions from `trashed`, restore of a non-trashed ToDo, permanent delete of a
  non-trashed ToDo, default-list exclusion, explicit status filter, direct status assignment
  rejection on create and update, unknown status rejection, cross-user `404`, anonymous `401`,
  malformed id `404`, tag survival across trash/restore.

### Test results
- `composer test` — 228 tests, 485 assertions, OK.
- `composer cs-check` — no violations.
- `composer stan` — no errors.
- Migrations: `rollback --target=0` then `migrate` verified on the test connection.

### Authorization rules
Lifecycle actions require an authenticated session; ToDos are resolved through the owner-scoped
finder, so another user's ToDo is indistinguishable from a missing one (`404`). Anonymous requests
receive `401`. No lifecycle field is mass-assignable — status changes only through the service.

### Security review
- **Authentication/authorization:** covered by owner-scoped lookup plus explicit tests.
- **Mass assignment:** `status` restricted to directly assignable values; `previous_status`,
  `archived_at`, `trashed_at` are not accessible on the entity.
- **IDOR:** malformed and cross-user ids tested.
- **Destructive operations:** permanent deletion is only possible after an explicit trash step and
  deletes only the target ToDo and its join rows; Tags and sections are untouched.
- **Data integrity:** database CHECK constraints prevent inconsistent status/timestamp states even
  outside the application.

### Code-bloat review
One new class was added, and it centralizes a genuinely shared domain rule set with multiple
callers. No generic manager, helper or wrapper was introduced.

### Known limitations / deferred work
- No automatic purge of long-trashed ToDos; deletion remains user-initiated.
- Lifecycle for other entity types is deferred to their own slices.

### Decision
```text
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.
```

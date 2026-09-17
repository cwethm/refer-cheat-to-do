# Slice 7 — Review Scheduling, Orphan Detection, and Resurfacing

## Slice Contract

### Goal
Resurface unresolved or context-poor ToDos through a deterministic, explainable review queue, and
let the user review or snooze them.

### In scope
- Review bookkeeping on ToDos: `last_reviewed_at`, `next_review_at`, `review_interval_days`.
- `ReviewSchedulingService` — the single authority for review date computation.
- `OrphanDetectionService` — individually testable rules plus reason codes and rule-based
  suggested actions.
- Review queue endpoint, mark-reviewed endpoint, snooze endpoint.

### Out of scope
- AI recommendations, semantic relatedness, autonomous mutation, external notifications.

### Schema changes
Migration `20260916160000_AddTodoReviewFields`:
- `todos.last_reviewed_at`, `todos.next_review_at` (nullable timestamps).
- `todos.review_interval_days` (not null, default 7).
- Index `todos_user_next_review_idx` on `(user_id, next_review_at)` for the queue query.
- `todos_review_interval_chk`: interval between 1 and 365 days.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `GET` | `/api/todos/review-queue` | Paginated queue of eligible ToDos with `reasons` and `suggested_actions` |
| `POST` | `/api/todos/{id}/reviewed` | Records a review; optional `review_interval_days` / `next_review_at` |
| `POST` | `/api/todos/{id}/snooze` | Reschedules only; requires exactly one of `days` or `until` |

The ToDo serializer now exposes `last_reviewed_at`, `next_review_at` and `review_interval_days`.

### Reason codes
`review_overdue`, `never_reviewed`, `stale`, `no_section`, `no_tags` — accumulated in a stable order
and never duplicated. Suggested actions are derived from the reasons: `assign_section`, `add_tag`,
`mark_reviewed`, `archive_or_trash`.

### Eligibility and ordering
`done`, `archived` and `trashed` ToDos are never eligible. The queue is ordered by
`next_review_at ASC NULLS FIRST, id ASC`, so unscheduled and overdue items surface first.

---

## Slice Completion Report

### Delivered behaviour
All contracted behaviour was delivered: review columns and constraint, both services, the three
endpoints, reason and suggested-action serialization, and payload extension.

### Shared components
- **Added:** `App\Service\ReviewSchedulingService` (date computation, mark reviewed, snooze,
  due evaluation) and `App\Service\OrphanDetectionService` (pure rule predicates and reason
  accumulation). Both are used by the controller only through their public API.
- **Reused:** ownership resolution, lifecycle status constants, tag containment helper, API
  envelope, pagination validation helpers.
- **Intentionally local:** request body parsing helpers for optional integers and dates remain in
  the controller, since they are API input concerns with a single consumer each.
- No generic rules engine was introduced; each rule is a small predicate.

### Tests added
- `ReviewSchedulingServiceTest` — interval arithmetic, midnight boundary, explicit date overriding
  the interval, past-date rejection, snooze by days and by date, mutually exclusive snooze
  arguments, out-of-range durations, exactly-due vs one-second-before, unscheduled ToDos, and the
  database interval CHECK constraint. Uses a frozen clock (`DateTime::setTestNow`).
- `OrphanDetectionServiceTest` — every rule independently true and false, boundary conditions,
  status eligibility via data provider (including `done`), reason accumulation without duplicates,
  organized ToDos producing no reasons, suggested-action mapping, and a test proving rule
  evaluation never mutates the entity.
- `TodosControllerTest` — queue content and owner scoping, exclusion of archived/trashed/done,
  reappearance after restore, ordering, mark-reviewed timestamps and interval, explicit next review
  date, snooze, tag attachment removing the `no_tags` reason, invalid interval, malformed date,
  past date, missing and conflicting snooze arguments, cross-user `404`, anonymous `401`, invalid
  pagination `400`, and confirmation that review actions never change status.

### Test results
- `composer test` — 284 tests, 580 assertions, OK.
- `composer cs-check` — no violations.
- `composer stan` — no errors.
- Migrations: `rollback --target=0` then `migrate` verified on the test connection, with the full
  suite re-run afterwards.

### Authorization rules
The queue is built from an owner-scoped query, so another user's overdue ToDos are never returned
and never counted. Review actions resolve the ToDo through the owner-scoped finder (cross-user
`404`, anonymous `401`) and grant no additional permissions.

### Security review
- **Information leakage:** reasons are computed only from the requesting user's own entities; no
  names or counts of other users' objects can appear.
- **No automatic mutation:** orphan rules are pure predicates; firing a rule never changes data.
- **Mass assignment:** review fields are not accessible on the entity and are only written by the
  scheduling service.
- **Input validation:** intervals, durations and dates are validated before persistence, with the
  database CHECK constraint as the final authority.
- **IDOR:** malformed and cross-user ids covered by tests.

### Code-bloat review
Two services were added, each with a distinct responsibility and real callers, and each replacing
logic that would otherwise be duplicated across queue building and review actions.

### Known limitations / deferred work
- The queue evaluates rules in PHP over the user's eligible ToDos and paginates in memory. This is
  correct and owner-scoped; if workspaces grow large, the reason evaluation can be pushed into SQL.
  Documented as non-blocking technical debt.
- Daylight-saving behaviour follows PHP/Chronos date arithmetic; no application-specific timezone
  policy exists yet, and review times are stored as absolute moments.

### Decision
```text
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.
```

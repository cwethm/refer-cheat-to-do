# Slice 13 — Dashboard and MVP Integration Polish

## Slice Contract

### Goal
Integrate the existing MVP capabilities into a coherent daily-use surface and close usability gaps
without adding new domain features.

### In scope
- `GET /api/dashboard` summary: inbox/active counts, review-due count, orphan count and recent
  ToDos, Projects, Notebooks, Libraries and activity.
- `DashboardService`, which composes existing authorities rather than restating their rules.
- Pagination consistency and bounded result sets across every collection endpoint.
- End-to-end journey coverage and an MVP-wide authorization sweep.

### Out of scope
- Research collections, AI, document generation, native apps, vector search, redesign.
- Any schema change (none was required).

## Delivered behaviour
After authenticating, a single request returns everything the daily surface needs. Counts respect
the lifecycle exclusions already defined by `TodoLifecycleService` and `OrphanDetectionService`;
review due-ness comes from `ReviewSchedulingService::isDue()`; recent activity comes from
`ActivityRecorder::forActor()`. Archived and trashed ToDos are excluded from both counts and recent
lists. Reading the dashboard records no activity.

## Schema changes
None. The dashboard reuses the indexes added by earlier slices, including the
`activity_records_actor_idx (actor_user_id, created)` index that serves the recent-activity query.

## API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `GET` | `/api/dashboard` | Counts and recent lists for the authenticated user |

`recent_limit` defaults to 5 and is capped at 50; malformed or zero values return `400`; anonymous
callers receive `401`. The response uses the established `data` / `meta` envelope.

## Authorization rules
Every query in `DashboardService` is filtered by the authenticated `user_id`, so there are no
cross-user counts and no cross-context leakage. The dashboard introduces no new authority: it can
only surface what the caller could already read through the per-resource endpoints.

## Shared components
`DashboardService` is a composition layer only. It owns no policy: lifecycle exclusions, orphan
reasoning, review due-ness and activity visibility all come from the services approved in Slices 6,
7 and 12. No UI design system or query-builder abstraction was introduced.

## Tests added
- `tests/TestCase/Controller/Api/DashboardControllerTest.php` — 15 tests: anonymous denial, response
  shape, empty workspace, counts agreeing with the underlying `/api/todos` and `/api/todos/review-queue`
  listings, archived/trashed exclusion, review-due transitions, owner scoping of both counts and
  recent lists, activity scoping, `recent_limit` behaviour and capping, malformed/zero `recent_limit`,
  and the assertion that reading the dashboard records no activity.
- `tests/TestCase/Controller/Api/MvpJourneyTest.php` — 36 tests covering the six required journeys
  end to end (capture and rediscover, organize, review, lifecycle, Library safety, cross-context
  request with callback and revocation) plus the MVP authorization sweep: IDOR attempts across
  ToDos, Projects, Notebooks and Libraries; anonymous denial on every protected collection; ownership
  reassignment through update endpoints; permanent deletion attempted through the update endpoint;
  capability escalation; cross-context mutation without capability; credential leakage in payloads;
  consistent validation and error envelopes; consistent pagination; and bounded result sets.

## Test results
```text
600 tests, 1588 assertions — OK
PHPCS — no errors
PHPStan — [OK] No errors
```

## Migration verification
Slice 13 adds no migration. The complete chain was verified instead: `migrations rollback
--connection test --target 0` reduced the schema to the migration log alone, `migrations migrate
--connection test` reapplied all twelve migrations, `migrations status` reports every migration `up`,
and the full suite passes against the rebuilt schema.

## Security review
The MVP authorization sweep passes: cross-user reads and mutations return `404`, anonymous access
returns `401`, ownership fields are never mass assignable, permanent deletion is unreachable through
the update endpoint, self-escalation through `/api/capabilities` on another user's resource is
refused, cross-context sends without `send_task` are refused, revoking a grant immediately withdraws
the capability, and no response payload contains credential material.

## Performance review
Every collection endpoint is paginated with a hard cap of 100, verified by test. The dashboard issues
a bounded set of queries: two counts, one tagged ToDo load for review/orphan reasoning, four short
recent lists and one activity query, each served by an existing index. ToDo loads use the existing
`withTags` finder, which avoids the N+1 tag load. No speculative optimization was added.

## Accessibility review
This repository is API-only; there is no HTML surface, so keyboard navigation, focus behaviour,
labels and colour semantics are not applicable here. The API supports an accessible client by
returning machine-readable error codes and messages rather than colour- or layout-dependent status,
and by returning explicit `reasons` on the review queue instead of implicit signals.

## Known limitations
- Dashboard counts iterate the user's non-hidden ToDos in PHP to apply orphan reasoning. This is
  correct and bounded by the user's active workload; it would need a SQL-side rule if workloads grow
  by orders of magnitude.
- Accessibility verification must be repeated when a UI is built.

## Deferred work
- Per-context dashboards and saved views.
- A dedicated client-facing UI and its accessibility test suite.

## Slice Completion Report

Slice 13 delivers the integrated daily surface, the end-to-end journey suite and the MVP-wide
authorization sweep. No new domain feature, dependency or schema change was introduced. The full
PHPUnit, PHPCS, PHPStan and complete migration chain gates pass.

Slice Decision: APPROVED

Reason:
All required tests and quality gates pass. The dashboard restates no business rule, leaks no
cross-user data, and every critical MVP journey is covered end to end. No blocking security,
data-integrity, architecture, scope, or regression issue remains.

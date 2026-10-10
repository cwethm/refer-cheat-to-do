# Slice 17 — Relationships, objectives, lifecycle and activity history in the client

## Slice Contract

### Status
PROPOSED

### Goal
Expose the remaining single-user API surfaces in the client: ToDo relationships and hierarchy,
objectives and reported results, the archive/trash/restore lifecycle, and the actor's own activity
history.

### In scope
- Viewing and editing a ToDo's parent, children and related ToDos.
- Setting an objective and reporting a result.
- Archive, trash, restore and permanent delete, with an explicit confirmation for destructive
  actions and a clear statement of what each action does.
- A read-only activity history view for the signed-in user.
- Snooze and review-scheduling controls alongside the existing review queue.

### Out of scope
- New domain rules, schema changes, or new API endpoints.
- Widening activity visibility beyond the actor; that is an ADR decision in Slice 18.
- Bulk lifecycle operations.

### Dependencies
Slices 6 (lifecycle), 7 (review scheduling), 8 (relationships and objectives), 12 (activity
history), and Slices 15–16 for the shared view scaffolding.

### Design constraints
- Destructive behaviour must be controlled: permanent delete requires an explicit, typed or
  two-step confirmation, never a single misclickable button.
- Activity history is actor-only, as recorded in `docs/slices/12-activity-history-and-audit-foundation.md`.
  The client must not imply otherwise.

### Test plan
- Client unit tests for relationship and lifecycle URL builders and for activity-entry formatting.
- Browser tests for: linking and unlinking related ToDos; setting an objective and reporting a
  result; archive then restore; trash then restore; permanent delete behind its confirmation;
  snoozing a review; activity history empty state and pagination boundaries.
- A browser test asserting that a destructive action cannot be triggered by a single click without
  confirmation.
- Accessibility audit of each new view plus responsive overflow coverage.
- Standing client security checklist unchanged and passing.

### Risks
- Destructive actions are the highest-risk client surface added so far; the confirmation path must
  be tested, not assumed.
- Hierarchy rendering can become recursive and unbounded. Depth and page size must be bounded by the
  existing API parameters.

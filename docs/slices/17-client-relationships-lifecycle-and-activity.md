# Slice 17 — Relationships, objectives, lifecycle and activity history in the client

## Slice Contract

### Status
DELIVERED

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

## Delivered behaviour
Each ToDo card in the inbox has a **Details** button that opens one shared detail panel below the
list. The panel names the ToDo, states its current status, and exposes everything Slices 6, 7 and 8
already allowed the API to do.

Hierarchy is edited with a picker: choosing a parent and pressing *Save parent* sets it, choosing
"No parent" clears it. The ToDos reporting to the open ToDo are listed underneath, each with the
result it has reported back, if any. Related ToDos work the same way — link one from the picker,
unlink it from the list — and the link is symmetric, as the API defines it.

An objective is free text saved on its own; emptying it clears it. A result is reported once, to the
parent, and once reported the panel shows it as a summary line rather than pretending it can be
rewritten.

Lifecycle actions are rendered from the ToDo's current status, so only the transitions the server
permits are offered: archive and trash from an active ToDo, activate or trash from an archived one,
restore or permanent delete from a trashed one. Every offered action is explained in a sentence
beneath the buttons. Permanent delete is two-step: the first press changes nothing, says
"This cannot be undone. Press the button again to confirm." and relabels the button to
*Confirm: delete permanently*; only the second press sends the `DELETE`.

Review cards gained a snooze control: a day count and a *Snooze* button, so a ToDo can be pushed
into the future instead of being reviewed now.

A new **Activity** view lists the signed-in person's own history, newest first, 20 entries per page,
each as a readable action, a subject and a UTC timestamp. It is read-only, and it is actor-only, as
Slice 12 recorded.

No endpoint, schema, domain rule or authorization rule changed.

## Architecture
- `TODO_ACTIONS` maps a lifecycle action to its HTTP method, and `buildTodoActionUrl` builds the
  path. The method is data, not a branch, so adding a transition does not mean adding a code path.
- `requiresConfirmation` names the one destructive action. The confirmation itself is held in the
  button's `dataset.confirmed`, so no modal, no global flag, and a re-render resets it.
- `LIFECYCLE_ACTIONS` carries each action's label, explanation and the statuses it applies to;
  `renderLifecycleActions` filters by the ToDo's status, which is what keeps the client from
  offering a transition the server will reject.
- `buildSnoozeBody` clamps to at least one whole day, and `buildRelatedTodoUrl` refuses an invalid
  id or a self-link, so the two most misusable inputs are pure and unit-tested.
- Activity reuses the existing paging machinery: `buildActivityUrl` delegates to
  `buildRecordListUrl`, and `activity` is a `VIEW_LOADERS` entry like every other view.
- `formatActivityEntry` turns a raw record into `{ action, subject, when }`. Timestamps are rendered
  in UTC deliberately: a locale-dependent rendering would be untestable without pinning a timezone.
- The parent and related pickers offer the ToDos currently listed, read from `state.todoItems`. No
  endpoint returns candidate ToDos, and adding one is out of scope.
- The module was not split; the client security suite still reads the whole `webroot/js/` directory,
  and all new text reaches the DOM through `textContent`.

## Accessibility and responsive review
- The detail panel is a labelled `section` with a heading, and every control in it — pickers,
  textareas, per-row unlink buttons, lifecycle buttons, snooze fields — has a programmatic name that
  includes the ToDo it acts on, so the controls are distinguishable out of context.
- Lifecycle explanations are on the page as text, not in a `title` attribute or a hover affordance.
- `tests/e2e/accessibility.spec.mjs` audits the Activity view and the opened detail panel with axe at
  WCAG 2.1 A/AA, and includes both in the 320/375/768/1280 overflow loop.

## Tests and verification
- `tests/js/app.test.mjs`: lifecycle action URLs and methods, rejection of invalid ids and unknown
  actions, related-ToDo URLs including the refused self-link, the confirmation predicate, snooze
  body clamping, activity URL paging and clamping, activity action labels, UTC timestamp formatting
  and whole-entry formatting.
- `tests/e2e/todo-lifecycle.spec.mjs`: setting and clearing a parent, linking and unlinking a
  related ToDo, saving an objective, reporting a result and seeing it from the parent's side; the
  archive → trash → restore → trash → permanent delete path verified through the API's `404`; a
  single click on permanent delete sending no `DELETE` and leaving the ToDo retrievable; and
  snoozing a ToDo from the review queue, verified by its future `next_review_at`.
- `tests/e2e/activity.spec.mjs`: the empty history of an actor who has never acted, pagination
  boundaries for the seeded actor, and a ToDo captured in the client appearing at the top of the
  history with a UTC timestamp.
- Global setup now creates two extra accounts: one that never acts, which is the only way to observe
  the empty activity history, and one that owns nothing, which is the only way to see a one-item
  review queue. Neither disturbs the seeded dataset the smoke journey depends on.
- `PagesControllerTest` additionally asserts the detail panel, its forms, the lifecycle container
  and the Activity view markup.
- Full gate: 634 PHPUnit tests / 1739 assertions (one pre-existing, unrelated DebugKit failure in
  the local sandbox only), 33 client unit and security tests, 43 browser tests, PHPCS clean.

## Known limitations
- The parent and related pickers only offer the ToDos currently listed. Linking to something outside
  the current search means searching for it first.
- Children are shown one level deep. The hierarchy endpoint's depth is not exposed as a control.
- Restore returns a ToDo to its previous status, which is what the server does; the client does not
  offer to choose a different one.
- A reported result cannot be edited or withdrawn from the client, because the API reports once.
- Activity cannot be filtered by subject, action or date, and it remains actor-only.

## Slice Completion Report

Slice 17 finishes the single-user client: relationships, objectives and results, the full lifecycle
with a two-step destructive confirmation, snooze scheduling, and the actor's own activity history
are all reachable from `/app`. No endpoint, schema, domain rule or authorization rule changed. The
slice is approved after the focused and full regression suites pass.

Slice Decision: **APPROVED**

# Slice 8 — Related ToDos, Parent/Child Relationships, and Terminal Objectives

## Slice Contract

### Goal
Represent related work, hierarchical investigations and explicit completion objectives, and let a
child report its result back to its parent without any automatic parent mutation.

### In scope
- Symmetric related-ToDo links between ToDos owned by the same user.
- Parent/child hierarchy with self-reference and cycle protection.
- `terminal_objective` describing what completion means.
- `objective_satisfied_at` and `result_summary` recorded by an explicit child callback.
- Hierarchy view returning parent, children and related ToDos.

### Out of scope
- Cross-Project request workflow (Slice 11), agent callbacks, arbitrary graph execution, automatic
  parent completion.

### Schema changes
Migration `20260916170000_CreateTodoRelationships`:
- `todos.parent_todo_id` (nullable self FK, `ON DELETE RESTRICT`) plus index.
- `todos.terminal_objective`, `todos.objective_satisfied_at`, `todos.result_summary`.
- `todos_parent_self_chk`: a ToDo can never be its own parent.
- `todos_result_requires_objective_chk`: a result may only exist when the objective is satisfied.
- `related_todos` join table with `CASCADE` foreign keys, unique index on the pair, and
  `related_todos_order_chk` requiring canonical ordering (`todo_id < related_todo_id`).

Storing pairs canonically makes the database unique index reject duplicates in either direction, so
symmetry is an invariant rather than an application convention.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `POST` | `/api/todos/{id}/related/{relatedId}` | Creates a related link (`201`) |
| `DELETE` | `/api/todos/{id}/related/{relatedId}` | Removes the link, never the ToDos |
| `PATCH` | `/api/todos/{id}/parent` | Sets or clears `parent_todo_id` |
| `PATCH` | `/api/todos/{id}/objective` | Sets or clears `terminal_objective` |
| `POST` | `/api/todos/{id}/result` | Child reports its result to its parent |
| `GET` | `/api/todos/{id}/hierarchy` | Returns the ToDo with parent, children and related ToDos |

The ToDo serializer now exposes `parent_todo_id`, `terminal_objective`, `objective_satisfied_at`
and `result_summary`.

---

## Slice Completion Report

### Delivered behaviour
All contracted behaviour was delivered, including cycle-aware parent assignment, canonical related
links, terminal objectives, the non-mutating child callback, and the hierarchy view.

### Shared components
- **Added:** `App\Service\TodoRelationshipService` — the single authority for relationship
  integrity (same-owner checks, self-reference, cycles, canonical pairs, objective and result
  rules). `App\Model\Table\RelatedTodosTable` owns the join-table invariants.
- **Reused:** owner-scoped ToDo resolution, lifecycle status constants, tag containment, the API
  envelope and error mapping.
- **Extended:** `TodoLifecycleService::permanentlyDelete()` now refuses to delete a ToDo that still
  has children, matching the `RESTRICT` foreign key and turning a would-be `500` into a `409`.
- No generic graph framework was introduced; hierarchy walking is a bounded loop with a depth cap.

### Tests added
- `TodoRelationshipServiceTest` — canonical storage, self-reference rejection, cross-user rejection,
  duplicates in either direction, unrelate semantics, direct database CHECK/unique-constraint tests
  (self relation, non-canonical order, duplicate pair, self-parent, result without objective),
  parent assignment and detachment, direct and indirect cycles, `wouldCreateCycle`, objective
  trimming/blanking/length, result recording with proof the parent is unchanged, missing-parent,
  blank result, repeat callback, trashed child callback, deletion blocked by children, relationship
  rows removed only for the deleted ToDo, and relationship survival across archive/trash/restore.
- `TodosControllerTest` — every endpoint, duplicate/self/cross-user/unknown targets, unrelate of a
  missing link, parent cycle and malformed parent value, objective set/clear and type rejection,
  child callback with parent left untouched, non-repeatable callback, malformed callback payload,
  permanent delete blocked by children, hierarchy on another user's ToDo, anonymous denial, and
  proof that objective changes never alter lifecycle status.

### Test results
- `composer test` — 337 tests, 673 assertions, OK.
- `composer cs-check` — no violations.
- `composer stan` — no errors.
- Migrations: standalone `migrate`/`rollback`/`migrate` for this migration plus a full
  `rollback --target=0` and `migrate` on the test connection, with the suite re-run afterwards.

### Authorization rules
Both endpoints of every relationship resolve each ToDo through the owner-scoped finder, so a
relationship id can never confirm the existence of another user's ToDo — unauthorized targets are
indistinguishable from missing ones (`404`). The service re-checks same-owner as defense in depth.
The callback requires an existing parent relationship.

### Security review
- **IDOR / inference:** relationship ids are always resolved within the caller's ownership scope.
- **Callback safety:** the callback writes only to the reporting child; a test asserts the parent
  row is byte-for-byte unchanged.
- **Mass assignment:** `parent_todo_id`, `terminal_objective`, `objective_satisfied_at` and
  `result_summary` are not accessible on the entity and are only written by the service.
- **Data integrity:** cycles are rejected in PHP, self-parenting additionally by a CHECK
  constraint, duplicates by a unique index, and orphaned results by a CHECK constraint.
- **Non-destructive:** removing a relationship never deletes ToDos; deleting a parent with children
  is refused rather than cascading.

### Code-bloat review
One service and one join-table class were added, both with concrete callers and real invariants.
Relationship rules live in exactly one place.

### Known limitations / deferred work
- Only one kind of related link exists; no `relation_type` column was added, since no current
  behaviour distinguishes link types and speculative columns are discouraged.
- The hierarchy view returns direct parent, direct children and related ToDos only; deeper trees
  are traversed by repeated calls.
- Detaching children must be done explicitly before permanently deleting a parent.

### Decision
```text
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.
```

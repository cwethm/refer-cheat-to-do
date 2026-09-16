# Slice Contract

## Slice Name
Slice 4 — Projects and ProjectSections

## Roadmap Reference
Phase 4 — Projects and Project Sections

## Objective
Add goal-oriented organizational context for ToDos.

## User-Visible Outcome
A user can create a Project, divide it into ordered sections, assign ToDos to a ProjectSection, and view a Project with its sections and ToDos.

## Included Scope
- `projects` and `project_sections` schema, entities, and table classes
- Project CRUD API
- ProjectSection CRUD API nested under an owned Project
- deterministic section ordering and explicit reordering
- assign/move a ToDo to a ProjectSection through `PATCH /api/todos/{id}`
- Project detail view including ordered sections and their ToDos
- ToDo filtering by project and section, combinable with existing tag/status/text filters

## Excluded Scope
- Notebooks, Libraries, collaboration, cross-project requests
- project lifecycle/archive/trash (Slice 6)
- project templates or nested sub-projects

## Dependencies
- Slice 2 ToDo ownership model
- Slice 3 tag/search filtering behavior in `TodosController`
- API response/error conventions

## Existing Reusable Components
- `AppController::respond()`, `AppController::requireUserId()`
- `TodosTable::findWithTags()` / `loadTags()` for ToDo serialization
- existing pagination/filter validation pattern

## Proposed New Reusable Components
- none yet; ordering logic stays local to `ProjectSectionsTable` until Slice 5 confirms genuine NotebookSection commonality (rule of two)

## Data Changes
- `projects`: `id`, `user_id`, `name`, `description`, `created`, `modified`
- `project_sections`: `id`, `project_id`, `name`, `position`, `created`, `modified`
- `todos`: add nullable `project_section_id`
- foreign keys use `RESTRICT` so no organizational delete silently destroys ToDos
- indexes on `projects.user_id`, `project_sections.(project_id, position)`, `todos.project_section_id`

## API Changes
- `GET/POST /api/projects`
- `GET/PATCH/DELETE /api/projects/{id}`
- `GET/POST /api/projects/{id}/sections`
- `PATCH/DELETE /api/projects/{id}/sections/{sectionId}`
- `PATCH /api/todos/{id}` accepts `project_section_id` (including `null` to unassign)
- `GET /api/todos?project=&section=`

## Authorization Rules
- Projects are owned by the authenticated user
- sections inherit Project ownership and are only reachable through an owned Project
- a ToDo may only be assigned to a section owned by the same user
- ownership (`user_id`, `project_id`) is never accepted from client input
- anonymous access denied

## Failure Conditions
- nonexistent/cross-user Project or section → `404`
- invalid reorder position → `400`
- deleting a Project that still has sections → `409`
- deleting a section that still has ToDos → `409`
- cross-user assignment attempt → `404`

## Transaction Boundaries
- section create and reorder run inside a single transaction so positions stay contiguous

## Test Plan
### Unit
- position assignment on create
- reorder recalculation (move up, move down, no-op)

### Integration
- Project and section CRUD
- assign/move/unassign ToDo
- refuse to delete non-empty section and non-empty Project

### API
- all Project/section endpoints, success and failure payloads
- ToDo filtering by project/section combined with tag/status/text

### Authorization
- cross-user Project/section access denied
- cross-user assignment denied
- anonymous denied
- ownership not mass-assignable

### Negative
- invalid ids, invalid position, malformed JSON, invalid pagination

### Regression
- unassigned ToDos remain valid
- Slice 3 tags/search behavior unchanged

### Performance
- Project detail loads sections and ToDos with containment, not per-section queries

## Security Considerations
- server-side ownership resolution for every project/section/todo reference
- `user_id`/`project_id` protected from mass assignment
- RESTRICT foreign keys prevent destructive cascades

## Rollback / Reversal
- migration `down()` drops `todos.project_section_id`, `project_sections`, and `projects`

## Documentation Changes
- this document plus completion report

## Completion Criteria
Projects and sections provide working organization without affecting unassigned Inbox use, with the full quality gate passing.

---

# Slice Completion Report

## Delivered Behavior
- Project CRUD scoped to the authenticated user
- ProjectSection CRUD nested under an owned Project, with append-on-create ordering
- explicit reordering via `PATCH /api/projects/{id}/sections/{sectionId}` with `position`
- contiguous renumbering after section deletion
- ToDo assignment/move/unassign through `PATCH /api/todos/{id}` with `project_section_id`
- Project detail view returning ordered sections with their ToDos
- ToDo filtering by `project` and `section`, combinable with `q`, `status`, and `tag`

## Schema / API Changes
- migration `20260916130000_CreateProjectsAndProjectSections`
  - `projects(id, user_id, name, description, created, modified)` with `projects_user_idx`
  - `project_sections(id, project_id, name, position, created, modified)` with
    `project_sections_project_position_idx`
  - `todos.project_section_id` nullable with `todos_project_section_idx`
  - all new foreign keys use `RESTRICT`, so no organizational delete can destroy ToDos
- new endpoints listed in the Slice Contract; `GET /api/todos` accepts `project` and `section`
- ToDo payloads now include `project_section_id`

## Deletion Policy (explicit, non-destructive)
- deleting a Project that still has sections → `409 Conflict`
- deleting a section that still has ToDos → `409 Conflict`
- the database `RESTRICT` foreign keys enforce the same invariant independently

## Tests Added
- `ProjectSectionsTableTest`: next position, move up/down/no-op, out-of-range and zero position,
  `project_id`/`position` not mass-assignable, database refuses deleting a referenced section
- `ProjectsControllerTest`: owner-scoped listing, ownership on create, blank name, ordered detail
  view with ToDos, cross-user and malformed ids, ownership not reassignable, non-empty project and
  non-empty section deletion conflicts, section append/rename/reorder, invalid and non-numeric
  positions, section from another project, renumbering after delete, anonymous denial,
  invalid pagination
- `TodosControllerTest`: assign, move, unassign, cross-user section denial, invalid section value,
  project filter, section+status filter, cross-user project filter, invalid project filter,
  unassigned ToDos still listed

## Test Results
- `composer test` — 126 tests, all passing
- `composer cs-check` — passing
- `composer stan` — no errors
- migrations: new migration `migrate`/`rollback`/`migrate`, plus full-chain
  `migrate` → `rollback --target=0` → `migrate` on a clean database

## Authorization Rules Enforced
- Projects resolved by `(id, user_id)`; sections resolved by `(id, project_id)` of an owned Project
- ToDo assignment resolves the section through an inner join on the owning Project
- `user_id`, `project_id`, and `position` are not mass-assignable
- cross-user access returns `404`; anonymous access returns `401`

## Shared Components Added / Reused
- reused `AppController::respond()` / `requireUserId()` and the ToDo serializer with tags
- ordering logic (`nextPosition`, `moveToPosition`, `renumberPositions`) stays local to
  `ProjectSectionsTable`; per the rule of two, extraction is deferred until Slice 5 shows that
  NotebookSections genuinely need identical behavior

## Intentionally Local Logic
- pagination/id-filter parsing is still duplicated across three controllers; it is small,
  validates different fields, and will be revisited if Slice 5 adds a fourth genuine consumer

## Known Limitations / Deferred Work
- section names are not uniquely constrained within a project (no stated domain invariant)
- reordering rewrites affected sibling rows in one transaction; adequate for MVP section counts
- Project lifecycle (archive/trash) arrives in Slice 6

## Security Review
- authentication required on all endpoints; ownership resolved server-side for every reference
- mass assignment cannot move Project or section ownership or forge positions
- filters are parameter-bound and validated; invalid values return `400`
- destructive operations blocked at both the API layer and the database (`RESTRICT`)
- cross-user identifiers never leak resource existence beyond `404`

## Code Bloat Review
- one new controller, two entities, two table classes, one migration — no speculative abstractions
- no generic "SectionManager" introduced

## Decision
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.

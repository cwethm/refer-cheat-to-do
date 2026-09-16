# Slice Contract

## Slice Name
Slice 5 — Notebooks and NotebookSections

## Roadmap Reference
Phase 5 — Notebooks and Notebook Sections

## Objective
Add continuing knowledge/reference contexts distinct from Projects.

## User-Visible Outcome
A user can create a Notebook, divide it into ordered sections, assign ToDos to NotebookSections, and filter ToDos by notebook context.

## Included Scope
- `notebooks` and `notebook_sections` schema, entities, and table classes
- Notebook CRUD API and NotebookSection CRUD API nested under an owned Notebook
- section ordering reusing the ordering component extracted in this slice
- assign/move/unassign a ToDo to a NotebookSection through `PATCH /api/todos/{id}`
- Notebook detail view with ordered sections and their ToDos
- ToDo filtering by `notebook` and `notebook_section`
- ADR 0001 and its enforcement: a ToDo belongs to at most one organizational section

## Excluded Scope
- Libraries, research collections, generated documents, cross-notebook interactions
- lifecycle/archive/trash (Slice 6)

## Dependencies
- Slice 4 Projects/ProjectSections, whose ordering behavior is the reuse baseline
- Slice 3 filtering behavior in `TodosController`

## Reuse Review (required by the slice definition)
ProjectSections and NotebookSections need genuinely identical ordering mechanics
(append on create, explicit 1-based move, contiguous renumbering, scoped to a parent container).
This is the second genuine occurrence, so ordering was extracted into
`App\Model\Behavior\SectionOrderingBehavior`, configured per table with a `parentField`.

Deliberately **not** shared:
- validation rules, associations, and `existsIn` rules stay per domain
- Projects and Notebooks remain separate domain models; no generic "Container" was introduced
- controllers remain separate because their resource vocabulary, payloads, and routes differ

## Data Changes
- `notebooks(id, user_id, name, description, created, modified)` with `notebooks_user_idx`
- `notebook_sections(id, notebook_id, name, position, created, modified)` with
  `notebook_sections_notebook_position_idx`
- `todos.notebook_section_id` nullable, `todos_notebook_section_idx`, `RESTRICT` foreign key
- `todos_single_section_chk` check constraint enforcing ADR 0001

## API Changes
- `GET/POST /api/notebooks`
- `GET/PATCH/DELETE /api/notebooks/{id}`
- `GET/POST /api/notebooks/{id}/sections`
- `PATCH/DELETE /api/notebooks/{id}/sections/{sectionId}`
- `PATCH /api/todos/{id}` accepts `notebook_section_id` (including `null`)
- `GET /api/todos?notebook=&notebook_section=`

## Authorization Rules
- Notebooks owned by the authenticated user; sections reachable only through an owned Notebook
- ToDos may only be assigned to sections of Notebooks owned by the same user
- `user_id`, `notebook_id`, and `position` are not mass-assignable
- anonymous access denied

## Failure Conditions
- nonexistent/cross-user Notebook or section → `404`
- invalid reorder position → `400`
- deleting a Notebook with sections or a section with ToDos → `409`
- assigning both a ProjectSection and a NotebookSection → `409`

## Test Plan
Mirrors the Slice 4 depth, plus cross-context tests and reuse regression across both domains.

## Security Considerations
- server-side ownership resolution for every notebook/section/todo reference
- mass-assignment protection on ownership and ordering fields
- `RESTRICT` foreign keys and a check constraint enforce integrity independently of the app

## Rollback / Reversal
- migration `down()` drops the check constraint, `todos.notebook_section_id`,
  `notebook_sections`, and `notebooks`

## Completion Criteria
Notebook organization works independently, Project behavior is unchanged, and the reuse is proven by tests exercising both domains.

---

# Slice Completion Report

## Delivered Behavior
- Notebook CRUD and NotebookSection CRUD with ordering identical to Projects
- ToDo assignment/move/unassign for notebook sections
- Notebook detail view with ordered sections and their ToDos
- ToDo filtering by `notebook` and `notebook_section`, combinable with tag/status/text filters
- ADR 0001 enforced at API and database layers

## Schema / API Changes
As listed in the Slice Contract; migration `20260916140000_CreateNotebooksAndNotebookSections`.

## Shared Components Added / Reused
- **added**: `SectionOrderingBehavior` (`nextPosition`, `moveToPosition`, `renumberPositions`),
  now used by both `ProjectSectionsTable` and `NotebookSectionsTable` through thin typed delegates
- reused: `AppController::respond()` / `requireUserId()`, ToDo serializer, filter-validation pattern

## Intentionally Local Logic
- Notebook and Project controllers, validators, and rules remain separate; only the ordering
  mechanics were shared
- pagination/id-filter parsing remains per controller; it validates different fields and is small

## Tests Added
- `SectionOrderingBehaviorTest`: data-provider driven across **both** section domains — behavior
  configuration, move, invalid position, renumbering, and parent scoping
- `NotebookSectionsTableTest`: mirrors the ProjectSections table tests
- `NotebooksControllerTest`: mirrors the Projects API tests (CRUD, ordering, conflicts,
  cross-user denial, anonymous denial, invalid pagination)
- `TodosControllerTest`: notebook assignment, cross-user denial, simultaneous assignment `409`,
  single-request context move, database check-constraint rejection, notebook filters

## Test Results
- `composer test` — 176 tests, all passing (includes full Slice 3 and Slice 4 regression)
- `composer cs-check` — passing
- `composer stan` — no errors
- migrations: new migration `migrate`/`rollback`/`migrate`, plus full-chain
  `migrate` → `rollback --target=0` → `migrate` on a clean database

## Shared Module Regression
After extracting `SectionOrderingBehavior`, the complete Projects suites
(`ProjectSectionsTableTest`, `ProjectsControllerTest`) and the complete ToDo suites were rerun and
pass unchanged, alongside the new Notebook suites.

## Known Limitations / Deferred Work
- section names are not uniquely constrained within a container
- `section` remains the query parameter for project sections; `notebook_section` is the notebook
  equivalent. Renaming `section` to `project_section` is deferred to avoid an unnecessary
  API break inside the MVP.

## Security Review
- authentication/authorization enforced on every endpoint, ownership resolved server-side
- mass assignment cannot set ownership, parent container, or position
- no cross-user leakage: cross-user ids return `404`, cross-user filters return empty result sets
- destructive operations blocked at API and database layers
- ADR 0001 invariant enforced in the database, so no application path can corrupt it

## Code Bloat Review
- one behavior extracted on the second genuine occurrence, justified by tests across both domains
- no generic container/manager abstraction; domain models stay distinct

## Decision
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.

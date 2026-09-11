# Slice Contract

## Slice Name
Slice 2 — ToDo Capture and Inbox

## Roadmap Reference
Phase 2 — ToDo Capture and Inbox

## Objective
Deliver the first persistent ToDo workflow so an authenticated user can create, list, view, and edit their own ToDos in Inbox with enforced ownership boundaries.

## User-Visible Outcome
An authenticated user can create a ToDo quickly, see it in an Inbox list, open it, and update it later without exposure to other users' ToDos.

## Included Scope
- ToDos persistence model (`todos` table, entity, table class)
- Minimal user persistence needed for ownership (`users` table, entity, table class)
- API endpoints:
  - `POST /api/todos`
  - `GET /api/todos`
  - `GET /api/todos/{id}`
  - `PATCH /api/todos/{id}`
- Inbox filtering via `status=inbox`
- Ownership enforcement on read/write operations
- Input validation for title/status and protected ownership assignment
- Pagination bounds for listing
- Tests for API, authorization, and negative behavior

## Excluded Scope
- Tags
- Projects
- Notebooks
- Review scheduling
- Archive/trash lifecycle
- Parent/child relationships
- Libraries
- Capability model and cross-context requests

## Dependencies
- Existing API success envelope (`App\Controller\Api\AppController::respond`)
- Existing API exception renderer (`App\Error\ApiExceptionRenderer`)
- CakePHP ORM, validation, and integration test framework

## Existing Reusable Components
- `App\Controller\Api\AppController::respond()` for JSON response shape
- `App\Error\ApiExceptionRenderer` for error contract
- existing test bootstrap and integration test trait usage

## Proposed New Reusable Components
- authenticated user resolver helper in API base controller for current-user lookup from session context

## Data Changes
- Add migration for `users`:
  - `id`, `email`, `password`, `created`, `modified`
  - unique index on `email`
- Add migration for `todos`:
  - `id`, `user_id`, `title`, `notes`, `status`, `created`, `modified`
  - foreign key `todos.user_id -> users.id`
  - index on (`user_id`, `status`) for Inbox queries

## API Changes
- Add ToDo capture and inbox endpoints under `/api/todos`
- Responses continue using `data` envelope and optional `meta`
- Validation/authorization failures return existing JSON error envelope

## Authorization Rules
- anonymous users are denied create/read/update for ToDos
- authenticated users may operate only on their own ToDos
- incoming `user_id` is ignored/rejected as client-controlled ownership input

## Failure Conditions
- missing or blank title
- invalid status value
- malformed request body
- missing authentication context
- nonexistent ToDo ID
- access to another user's ToDo
- attempted mass-assignment of protected ownership fields

## Transaction Boundaries
- ToDo create and update operations run as single-entity writes; no multi-aggregate transaction is introduced in this slice.

## Test Plan
### Unit
- validation for title and status

### Integration
- successful persistence for valid ToDo
- rejection of invalid ToDo
- ownership association persisted to `user_id`

### API
- create returns 201 and expected payload
- list returns only authenticated user's ToDos
- detail returns owned ToDo
- patch updates allowed fields

### Authorization
- owner create/read/update allowed
- cross-user read/update denied
- anonymous create/read/update denied

### Negative Cases
- missing title rejected
- invalid status rejected
- invalid/nonexistent ID rejected
- malformed request rejected
- attempted client ownership assignment blocked

### Regression
- health endpoint still passes
- API envelope conventions unchanged
- existing suite remains passing

### Migration Verification
- migrate up for default/test
- rollback latest migration in test datasource and reapply

## Security Considerations
- ownership checks on query layer for detail/update
- no password fields returned in API payloads
- no client-controlled ownership mutation
- no cross-user listing leakage

## Rollback / Reversal
- rollback latest migrations with Cake migrations rollback commands
- remove Slice 2 routes/controllers/models if full reversal is required

## Documentation Changes
- `docs/slices/2-todo-capture-and-inbox.md`
- Slice completion report appended after implementation

## Completion Criteria
- authenticated user can create/list/view/edit own ToDos
- cross-user and anonymous access is denied by tests
- migrations and rollback/reapply checks pass
- coding standards, static analysis, and full regression suite pass
- slice completion report recorded with decision

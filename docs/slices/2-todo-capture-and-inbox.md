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

---

# Slice Completion Report

## Slice
Slice 2 — ToDo Capture and Inbox

## Objective
Deliver persistent ToDo capture and Inbox listing with user ownership boundaries and complete quality-gate verification.

## Delivered
- users and todos persistence schema via migrations
- ToDo API endpoints for create/list/view/edit
- authentication context endpoints (`/api/auth/login`, `/api/auth/logout`, `/api/auth/me`)
- ownership enforcement for ToDo list/detail/update queries
- validation and failure-path handling for title/status/query/filter inputs
- pagination metadata on ToDo list responses
- regression-safe JSON response and error conventions maintained

## Files Added
- `config/Migrations/20260911095500_CreateUsersAndTodos.php`
- `src/Controller/Api/AuthController.php`
- `src/Controller/Api/TodosController.php`
- `src/Http/Exception/ValidationException.php`
- `src/Model/Entity/User.php`
- `src/Model/Entity/Todo.php`
- `src/Model/Table/UsersTable.php`
- `src/Model/Table/TodosTable.php`
- `tests/Fixture/UsersFixture.php`
- `tests/Fixture/TodosFixture.php`
- `tests/TestCase/Controller/Api/AuthControllerTest.php`
- `tests/TestCase/Controller/Api/TodosControllerTest.php`
- `tests/TestCase/Model/Table/TodosTableTest.php`

## Files Modified
- `.github/workflows/ci.yml`
- `config/routes.php`
- `src/Controller/Api/AppController.php`
- `docs/slices/2-todo-capture-and-inbox.md`

## Database Changes
- created `users` table with unique email constraint
- created `todos` table with owner foreign key and status support
- added `todos_user_status_idx` index for inbox/status filtering

## API Changes
- added `POST /api/auth/login`
- added `POST /api/auth/logout`
- added `GET /api/auth/me`
- added `POST /api/todos`
- added `GET /api/todos`
- added `GET /api/todos/{id}`
- added `PATCH /api/todos/{id}`
- all success responses remain under `data`; errors remain under `error`

## Reusable Components Added
- `App\Controller\Api\AppController::requireUserId()` reusable authenticated-user resolver for API controllers
- `App\Http\Exception\ValidationException` reusable HTTP 422 exception for validation failures

## Existing Shared Components Reused
- `App\Controller\Api\AppController::respond()` for JSON success envelopes
- `App\Error\ApiExceptionRenderer` for JSON error envelopes

## Intentionally Local Logic
- ToDo serialization and filter parsing were kept local to `TodosController` because there is a single caller in this slice.

## Authorization Rules
- anonymous users denied for ToDo create/list/view/update
- users can read/update only ToDos where `todos.user_id` matches session user
- client-supplied ownership assignment does not override server-side owner assignment

## Tests Added
- `tests/TestCase/Controller/Api/AuthControllerTest.php`
- `tests/TestCase/Controller/Api/TodosControllerTest.php`
- `tests/TestCase/Model/Table/TodosTableTest.php`
- `tests/Fixture/UsersFixture.php`
- `tests/Fixture/TodosFixture.php`

## Test Results

### Focused Tests
PASS — new AuthController, TodosController, and TodosTable tests

### Integration Tests
PASS — table persistence and controller integration flows

### API Tests
PASS — ToDo and auth endpoint request/response behavior

### Authorization Tests
PASS — cross-user access denied and anonymous access denied

### Negative Tests
PASS — invalid status, missing title, invalid IDs, and missing auth cases

### Full Regression Suite
PASS — CI run #39 attempt #2 (`composer test`)

### Coding Standards
PASS — CI run #39 attempt #2 (`composer cs-check`)

### Static Analysis
PASS — CI run #39 attempt #2 (`composer stan`)

### Migration Verification
PASS — CI run #39 attempt #2
- `bin/cake migrations migrate -c default`
- `bin/cake migrations migrate -c test`
- `bin/cake migrations rollback -c test`
- `bin/cake migrations migrate -c test`

## Security Review
PASS — ownership is enforced server-side in list/detail/update queries, unauthorized access returns 401/404, and no cross-user read/write path remained.

## Scope Review
All delivered behavior stayed within Slice 2 scope (ToDo capture, inbox listing, edit/view, ownership, validation, and tests).

## Code-Bloat Review
No generic manager/factory abstraction introduced; only small table/controller/exception components with direct slice value were added.

## Known Limitations
- Session-based auth context is minimal and intended only as current-slice ownership context.

## Deferred Work
- tag/search lifecycle and richer filtering move to Slice 3
- deeper authentication hardening can be expanded in future slices if required by evolving policy

## Documentation Updated
- `docs/slices/1-authentication-and-user-workspace.md`
- `docs/slices/2-todo-capture-and-inbox.md`

## Slice Decision
APPROVED

## Next Slice
Slice 3 — Tags, Search, and Filtering

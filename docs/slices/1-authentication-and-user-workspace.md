# Slice Contract

## Slice Name

Slice 1 — Authentication and User Workspace

## Roadmap Reference

Phase 1 — Authentication and User Workspace

## Objective

Introduce authenticated user identity, secure password handling, login/logout/me API behavior, and workspace ownership checks that future slices can reuse.

## User-Visible Outcome

A user can log in, log out, and read their authenticated workspace identity. Anonymous and cross-user workspace access is denied.

## Included Scope

- users persistence model and migration
- password hashing and uniqueness rules
- API auth endpoints (`/api/auth/login`, `/api/auth/logout`, `/api/auth/me`)
- authenticated workspace endpoints (`/api/workspace`, `/api/workspace/{userId}`)
- ownership policy foundation
- authentication request middleware
- slice tests and documentation updates

## Excluded Scope

- invitations, teams, collaboration
- OAuth, password recovery, account verification
- ToDo schema and all later roadmap domains

## Dependencies

- existing CakePHP API foundation and JSON error/response conventions
- CakePHP ORM, session, middleware, and validation components

## Existing Reusable Components

- `App\Controller\Api\AppController::respond()`
- `App\Error\ApiExceptionRenderer`

## Proposed Reusable Components

- `App\Middleware\ApiAuthenticationMiddleware`
- `App\Policy\OwnershipPolicy`
- `AppController::requireAuthenticatedIdentity()` helper

## Data Changes

- add `users` table with unique `email`, `password`, and timestamps

## API Changes

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/workspace`
- `GET /api/workspace/{userId}`

## Authorization Rules

- anonymous users can call login and health only
- authenticated users can call `me` and workspace endpoints
- only the owner may access `/api/workspace/{userId}` for that `userId`

## Failure Conditions

- missing login credentials
- invalid credentials
- anonymous access to authenticated endpoints
- cross-user workspace access
- duplicate user identity

## Transaction Boundaries

- login writes only session state after credential verification
- user creation persists atomically through ORM save operation

## Test Plan

### Unit

- ownership policy owner/different/anonymous outcomes

### Integration

- password hashing on save
- duplicate identity handling

### API

- login success/failure
- logout invalidation
- me behavior
- workspace behavior

### Authorization

- owner allowed
- different authenticated user denied
- anonymous denied

### Negative Cases

- missing login credentials
- invalid credentials

### Regression

- health endpoint still passes and JSON conventions still apply

### Security

- password excluded from API payload
- server-side identity enforcement

## Rollback / Reversal

- migration rollback drops users table
- auth routes/middleware/controllers can be removed as one bounded slice

## Documentation Changes

- add this slice contract and completion report in `docs/slices/`

## Completion Criteria

- login/logout/me implemented and tested
- workspace ownership enforcement tested
- user persistence and hashing rules tested
- full quality gate executed

# Slice Completion Report

## Slice

Slice 1 — Authentication and User Workspace

## Objective

Introduce authenticated identity, secure credential handling, and ownership boundaries for future domain slices.

## Delivered

- users migration, table, entity, and validation/rules
- API auth endpoints and workspace endpoints
- authentication middleware and ownership policy
- unit/integration/api/authorization coverage for slice behavior

## Files Added

- `config/Migrations/20260911070000_CreateUsers.php`
- `src/Controller/Api/AuthController.php`
- `src/Controller/Api/WorkspaceController.php`
- `src/Middleware/ApiAuthenticationMiddleware.php`
- `src/Model/Entity/User.php`
- `src/Model/Table/UsersTable.php`
- `src/Policy/OwnershipPolicy.php`
- `tests/Fixture/UsersFixture.php`
- `tests/TestCase/Controller/Api/AuthControllerTest.php`
- `tests/TestCase/Controller/Api/WorkspaceControllerTest.php`
- `tests/TestCase/Model/Table/UsersTableTest.php`
- `tests/TestCase/Policy/OwnershipPolicyTest.php`
- `tests/TestCase/ApplicationTest.php`
- `README.md`

## Files Modified

- `config/routes.php`
- `src/Application.php`
- `src/Controller/Api/AppController.php`
- `src/Controller/Api/AuthController.php`
- `src/Controller/Api/WorkspaceController.php`
- `src/Middleware/ApiAuthenticationMiddleware.php`
- `src/Model/Entity/User.php`
- `src/Model/Table/UsersTable.php`
- `tests/TestCase/Controller/Api/AuthControllerTest.php`

## Database Changes

- created `users` table with case-insensitive unique index on `LOWER(email)`

## API Changes

- added auth and workspace API endpoints for slice scope

## Reusable Components Added

- API authentication middleware
- ownership policy
- authenticated identity helper

## Existing Shared Components Reused

- API JSON response conventions from base API controller and exception renderer

## Intentionally Local Logic

- login validation and credential verification kept local to auth controller in this slice

## Authorization Rules

- anonymous denied on auth-only/workspace routes
- authenticated user restricted to own workspace by ownership policy

## Tests Added

- auth controller tests
- workspace authorization tests
- users table tests
- ownership policy tests

## Test Results

### Focused Tests
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Integration Tests
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Authorization Tests
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Full Regression Suite
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Coding Standards
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Static Analysis
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

### Migration Verification
FAIL (blocked by missing dependencies: `vendor/autoload.php`)

## Security Review

- session-based authentication is server enforced
- password hashes are never returned in API responses
- no secrets were added in the changed files
- dependency install failure prevented full runtime security test execution

## Scope Review

No out-of-scope roadmap features intentionally implemented.

## Code-Bloat Review

No speculative abstraction layers introduced.

## Known Limitations

- no rate limiting in this slice

## Deferred Work

- rate limiting and advanced auth mechanisms (OAuth/recovery)

## Documentation Updated

Yes

## Slice Decision

BLOCKED

## Next Slice

N/A while Slice 1 remains blocked

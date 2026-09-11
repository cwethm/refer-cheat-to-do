# MVP Development Slices

## Status

**Implementation Plan — MVP 1**

This document converts the MVP roadmap into explicit development slices.

Every slice in this document must be implemented according to:

- `docs/application-design.md`
- `docs/roadmap.md`
- `docs/mvp-feature-list.md`
- `docs/development-slice-protocol.md`

The governing rule is:

> Build one small, complete, testable vertical slice at a time. Do not begin the next slice until the current slice satisfies its completion criteria and regression gate.

---

# 1. Global Slice Rules

Every slice must:

- have a written Slice Contract before coding begins;
- define included and excluded scope;
- reuse existing stable shared modules where appropriate;
- avoid speculative abstractions;
- keep controllers thin;
- enforce authorization server-side;
- include positive and negative tests;
- run focused tests and the full regression suite;
- document schema and API changes;
- review for code duplication and bloat;
- produce a Slice Completion Report;
- stop after completion.

---

# 2. Global Test Gate

Every slice must pass the following gate before it is complete.

## Required Checks

- [ ] slice-specific unit tests pass
- [ ] slice-specific integration tests pass
- [ ] slice-specific API/controller tests pass
- [ ] authorization tests pass
- [ ] negative/failure-path tests pass
- [ ] related-module tests pass
- [ ] full project test suite passes
- [ ] coding-standard/lint checks pass
- [ ] static analysis passes or documented exception exists
- [ ] migrations apply cleanly
- [ ] migrations reverse cleanly where practical
- [ ] no unexpected schema drift
- [ ] no unrelated files changed
- [ ] no untested destructive behavior introduced
- [ ] no duplicated business rule introduced without justification
- [ ] documentation updated

## Test Philosophy

Testing should concentrate on:

- domain invariants;
- authorization;
- lifecycle transitions;
- destructive actions;
- transaction boundaries;
- relationship integrity;
- review/resurfacing behavior;
- cross-context boundaries;
- regression prevention.

Do not create low-value tests only to increase a coverage percentage.

---

# Slice 0 — Repository and Engineering Foundation

## Roadmap Reference

Phase 0 — Repository and Engineering Foundation

## Objective

Create a bootable, testable, documented CakePHP 5 repository with PostgreSQL, CI, development tooling, and a stable base for future slices.

## User-Visible Outcome

No major product functionality yet. Developers can reliably boot, test, analyze, and modify the application.

## Included Scope

- CakePHP 5 application skeleton
- PHP 8.3+ support
- PostgreSQL development/test configuration
- `.env.example`
- local or devcontainer setup
- PHPUnit configuration
- coding standards
- static analysis
- GitHub Actions CI
- health-check endpoint
- base JSON error envelope
- README setup instructions
- documentation directory structure

## Excluded Scope

- user authentication
- ToDo schema
- Projects
- Notebooks
- Libraries
- agents
- research
- document generation

## Reusable Components

Potential shared components introduced here:

- API error response helper/formatter
- environment/config conventions
- base test utilities if they provide clear value

Avoid introducing generic service layers without real callers.

## Data Changes

None beyond framework-required infrastructure.

## API Changes

Add a minimal endpoint such as:

```text
GET /api/health
```

Expected response:

```json
{
  "status": "ok"
}
```

## Authorization Rules

Health endpoint may be public.

No private application data exists yet.

## Failure Conditions

- missing database connection
- invalid environment variables
- CI cannot install dependencies
- PHPUnit cannot connect to test database

## Test Plan

### Unit

- API response formatter if custom logic exists

### Integration

- database connection test
- migration framework boots
- test database can be reset

### API

- `GET /api/health` returns 200
- response is valid JSON
- expected content type returned

### Negative

- invalid route returns expected JSON error format where applicable

### Regression

- full default CakePHP test suite or project baseline passes

### Tooling

- `composer validate`
- PHPUnit
- coding standard check
- static analysis
- syntax validation

## Security Testing

- verify `.env` files are ignored
- verify secrets are not committed
- verify debug configuration is environment-specific

## Completion Criteria

- repository boots locally
- PostgreSQL development and test DB work
- CI passes
- health endpoint works
- README documents setup/test commands
- no future-domain tables created

---

# Slice 1 — Authentication and User Workspace

## Roadmap Reference

Phase 1 — Authentication and User Workspace

## Objective

Establish authenticated user identity, ownership boundaries, and the authorization foundation used by every later slice.

## User-Visible Outcome

A user can sign in, sign out, and access an authenticated application shell.

## Included Scope

- Users table/entity
- secure password handling
- authentication middleware/plugin
- login/logout flow
- authenticated workspace shell
- ownership helper/policy foundation
- basic user preferences container if needed

## Excluded Scope

- invitations
- teams
- multi-user collaboration
- Library permissions
- OAuth unless explicitly chosen
- password recovery unless required for initial deployment

## Reusable Components

- current-user resolver
- ownership authorization policy
- authentication test helpers

These are justified as cross-cutting infrastructure.

## Data Changes

Suggested:

```text
users
- id
- email / username
- password
- created
- modified
```

Use unique constraints for identity fields.

## API Changes

Possible endpoints:

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

## Authorization Rules

- anonymous users may only access public/auth endpoints
- authenticated users may access only their own private workspace
- authorization must not rely solely on UI hiding

## Failure Conditions

- invalid credentials
- missing credentials
- disabled/invalid account if supported
- attempt to access another user's resource
- expired/invalid session

## Test Plan

### Unit

- password validation rules if custom
- ownership policy evaluation

### Integration

- user persistence
- password hashing
- session/auth middleware flow

### API

- login succeeds with correct credentials
- login fails with wrong password
- logout invalidates session
- `/auth/me` returns current user
- anonymous `/auth/me` denied

### Authorization Matrix

- owner: allowed
- different authenticated user: denied
- anonymous user: denied

### Negative

- malformed login request
- missing email/username
- missing password
- duplicate user identity rejected
- invalid session token rejected

### Regression

- health endpoint still works
- public routes remain public
- authenticated route protection does not break API errors

## Security Testing

- passwords never returned by API
- hashes never logged
- session fixation protections verified through framework defaults
- CSRF handling appropriate for selected auth/API architecture
- rate limiting documented as future or implemented if required

## Completion Criteria

- login/logout works
- private workspace inaccessible anonymously
- cross-user access denied by tests
- authentication helpers reusable by future slices

---

# Slice 2 — ToDo Capture and Inbox

## Roadmap Reference

Phase 2 — ToDo Capture and Inbox

## Objective

Deliver the first meaningful product behavior: fast capture of an unresolved ToDo and display it in the user's Inbox.

## User-Visible Outcome

A user can create a ToDo in seconds, see it in Inbox, open it, edit it, and return later to its notes/context.

## Included Scope

- ToDos table/entity
- create ToDo
- edit ToDo
- view ToDo
- Inbox listing
- ownership enforcement
- basic status field
- context notes
- created/modified timestamps

## Excluded Scope

- Tags
- Projects
- Notebooks
- review scheduling
- archive/trash
- parent/child
- Libraries

## Reusable Components

Potential:

- ownership query scope
- standard API validation/error responses

Do not create a generic CRUD service unless meaningful shared behavior exists.

## Data Changes

Initial ToDo fields:

```text
todos
- id
- user_id
- title
- notes
- status
- created
- modified
```

Initial status may be limited to:

```text
inbox
active
```

Do not add future columns unless needed now.

## API Changes

```text
POST  /api/todos
GET   /api/todos
GET   /api/todos/{id}
PATCH /api/todos/{id}
```

Inbox may be:

```text
GET /api/todos?status=inbox
```

## Authorization Rules

- only owner can create in own workspace
- only owner can read/update own ToDos
- changing ownership through input must be rejected/ignored

## Failure Conditions

- blank title
- excessively long title if constrained
- invalid status
- nonexistent ToDo
- another user's ToDo
- malformed JSON

## Test Plan

### Unit

- ToDo status validation
- title validation

### Integration

- save valid ToDo
- reject invalid ToDo
- created/modified timestamps
- ownership association

### API

- create returns expected status and JSON
- list returns only current user's ToDos
- detail returns expected object
- edit updates allowed fields
- editing unknown ID returns 404

### Authorization

- owner create/read/update allowed
- other user read denied
- other user update denied
- anonymous create/read/update denied
- client cannot assign another `user_id`

### Negative

- missing title
- invalid status
- invalid ID
- malformed request
- attempted mass-assignment of protected fields

### Regression

- auth remains intact
- API error format remains consistent

### Query/Performance

- verify user/status index exists if Inbox query requires it
- prevent unbounded listing; introduce sensible pagination

## Security Testing

- notes are safely escaped in rendered web UI
- ownership enforced in query layer or policy
- mass-assignment protections checked

## Completion Criteria

A user can:

1. sign in;
2. create a ToDo;
3. see it in Inbox;
4. open it;
5. edit it;
6. never see another user's ToDo.

---

# Slice 3 — Tags, Search, and Filtering

## Roadmap Reference

Phase 3 — Tags, Search, and Filtering

## Objective

Make captured ToDos classifiable and reliably rediscoverable.

## User-Visible Outcome

A user can tag a ToDo and later find it by text or Tag.

## Included Scope

- Tag CRUD
- ToDo/Tag many-to-many relationship
- add/remove Tags on ToDos
- text search for title/notes
- filter by status
- filter by Tag
- pagination
- deterministic sorting

## Excluded Scope

- tag clouds
- AI tag suggestions
- Project/Notebook context
- semantic/vector search
- saved filters

## Reusable Components

Potential shared modules:

- query/filter parser if multiple endpoints need it
- pagination parameter validator
- search normalization helper only if needed by more than one caller

## Data Changes

```text
tags
- id
- user_id
- name
- created
- modified

todos_tags
- todo_id
- tag_id
```

Constraints:

- unique tag name per user, using defined normalization rules
- unique ToDo/Tag pair

## API Changes

```text
POST   /api/tags
GET    /api/tags
PATCH  /api/tags/{id}
DELETE /api/tags/{id}

POST   /api/todos/{id}/tags/{tagId}
DELETE /api/todos/{id}/tags/{tagId}

GET /api/todos?q=...&tag=...&status=...
```

## Authorization Rules

- user may only use own Tags
- cannot attach another user's Tag
- search returns only authorized ToDos

## Failure Conditions

- blank Tag name
- duplicate normalized Tag
- another user's Tag
- invalid filter
- invalid pagination
- duplicate relationship insertion

## Test Plan

### Unit

- Tag normalization if custom
- filter parser if extracted

### Integration

- create Tag
- enforce uniqueness
- attach/detach Tag
- prevent duplicate join row
- search title
- search notes
- filter by Tag
- combined filters
- pagination consistency

### API

- CRUD success cases
- attach/remove Tag
- text search
- filter query parameters
- stable JSON pagination metadata

### Authorization

- cannot read another user's private Tags
- cannot attach another user's Tag
- another user's tagged ToDo never appears in search
- anonymous access denied

### Negative

- malformed Tag ID
- malformed filter syntax
- nonexistent Tag
- duplicate attachment
- injection-like search terms treated safely
- invalid sort fields rejected

### Regression

- Inbox still returns correct ToDos
- ToDo create/edit unaffected without Tags

### Performance

- indexes on user/name
- join indexes
- inspect generated search queries for obvious N+1 behavior

## Security Testing

- query parameters are safely bound
- rendered Tag names escaped
- authorization applied before search result serialization

## Completion Criteria

A user can create Tags, apply them, and reliably find ToDos by words and Tags.

---

# Slice 4 — Projects and Project Sections

## Roadmap Reference

Phase 4 — Projects and Project Sections

## Objective

Add goal-oriented organizational context for ToDos.

## User-Visible Outcome

A user can create a Project, divide it into sections, and assign ToDos to a ProjectSection.

## Included Scope

- Project CRUD
- ProjectSection CRUD
- section ordering
- assign/move ToDo to ProjectSection
- Project view with ToDos
- Project filters

## Excluded Scope

- Notebooks
- Libraries
- Project collaboration
- cross-project requests
- complex project lifecycle

## Reusable Components

Potential:

- ordered-section behavior/service if later NotebookSections can reuse it
- context assignment validation if designed narrowly enough

Apply rule-of-two: avoid premature generic "SectionManager" until Notebook section behavior confirms commonality.

## Data Changes

```text
projects
- id
- user_id
- name
- description
- created
- modified

project_sections
- id
- project_id
- name
- position
- created
- modified

todos
+ project_section_id nullable
```

## API Changes

Project and section CRUD plus:

```text
PATCH /api/todos/{id}
{
  "project_section_id": ...
}
```

## Authorization Rules

- Projects owned by current user in MVP
- sections inherit Project ownership
- ToDo and ProjectSection must belong to same user

## Failure Conditions

- nonexistent Project
- nonexistent section
- section from another user
- invalid reorder
- delete section containing ToDos
- attempted cross-user assignment

## Test Plan

### Unit

- ordering calculations if custom
- context assignment invariant if extracted

### Integration

- Project CRUD
- section CRUD
- ordering/reordering
- assign ToDo to section
- move between sections
- behavior when deleting nonempty section

### API

- Project endpoints
- section endpoints
- assign/move endpoint behavior
- filtering ToDos by Project/section

### Authorization

- other user cannot view Project
- other user cannot alter section
- cannot assign ToDo to another user's section
- anonymous denied

### Negative

- invalid section ID
- invalid position
- duplicate section name if constrained
- deleting Project with dependent sections follows explicit safe policy
- assigning trashed/deleted ToDo if lifecycle later exists must be handled when introduced

### Regression

- unassigned ToDos remain valid
- Tags/search still work
- Project filter combines correctly with Tag/status filters

### Performance

- indexes on user/project relationships
- Project detail avoids N+1 loading

## Security Testing

- section ownership validated server-side
- mass assignment cannot move Project ownership

## Completion Criteria

Projects and sections provide working organization without affecting unassigned Inbox use.

---

# Slice 5 — Notebooks and Notebook Sections

## Roadmap Reference

Phase 5 — Notebooks and Notebook Sections

## Objective

Add continuing knowledge/reference contexts distinct from Projects.

## User-Visible Outcome

A user can create a Notebook, divide it into sections, and assign ToDos to NotebookSections.

## Included Scope

- Notebook CRUD
- NotebookSection CRUD
- section ordering
- assign/move ToDo to NotebookSection
- Notebook view
- Notebook filtering

## Excluded Scope

- Libraries
- research collections
- generated documents
- cross-notebook interactions

## Reuse Review

This slice must explicitly compare ProjectSection behavior.

If ordering, ownership, and assignment rules are genuinely the same, extract a small shared component only where it reduces duplication.

Do not force Projects and Notebooks into one generic "Container" model if domain meanings remain distinct.

## Data Changes

```text
notebooks
- id
- user_id
- name
- description
- created
- modified

notebook_sections
- id
- notebook_id
- name
- position
- created
- modified

todos
+ notebook_section_id nullable
```

## Important Open Rule

The implementation must follow the current ADR/documented decision regarding whether a ToDo may simultaneously belong to both a ProjectSection and NotebookSection.

If unresolved, create an ADR before implementing conflicting behavior.

## Test Plan

Mirror the Project test depth and add cross-context tests.

### Unit

- any extracted ordered-section logic

### Integration

- Notebook CRUD
- NotebookSection CRUD
- ordering
- ToDo assignment
- simultaneous Project/Notebook membership according to policy

### API

- Notebook endpoints
- section endpoints
- filtering

### Authorization

- cross-user Notebook access denied
- cross-user section assignment denied

### Negative

- invalid Notebook/section
- invalid reorder
- delete nonempty section
- conflicting Project/Notebook assignment if disallowed

### Regression

- Project functionality unchanged
- shared section abstraction behaves correctly for both domains
- search filters combine correctly

### Reuse Regression

If shared section code is extracted:

- run full Project tests
- run full Notebook tests
- ensure domain-specific validation remains distinct

## Completion Criteria

Notebook organization works independently and any reuse with Projects is proven rather than speculative.

---

# Slice 6 — Lifecycle, Archive, Trash, and Restore

## Roadmap Reference

Phase 6 — Lifecycle, Archive, and Trash

## Objective

Introduce safe, explicit ToDo lifecycle transitions and recoverable deletion.

## User-Visible Outcome

A user can move ToDos through Inbox, Active, Done, Archived, Trash, restore them, and explicitly permanently delete Trash items.

## Included Scope

- lifecycle states
- transition rules
- Archive
- Trash
- Restore
- permanent deletion
- timestamps for archive/trash
- lifecycle API actions
- exclusion rules for default lists

## Excluded Scope

- automated retention
- bulk delete
- archive cascade across Projects/Notebooks
- published documents

## Reusable Components

Create `TodoLifecycleService` if transitions contain meaningful business rules.

Do not duplicate lifecycle checks in controllers.

## Data Changes

Possible fields:

```text
todos
- status
- archived_at
- trashed_at
```

Avoid ambiguous duplicated state where possible.

## API Changes

Prefer explicit actions for important transitions:

```text
POST /api/todos/{id}/activate
POST /api/todos/{id}/complete
POST /api/todos/{id}/archive
POST /api/todos/{id}/trash
POST /api/todos/{id}/restore
DELETE /api/todos/{id}/permanent
```

Exact REST design may vary but must be documented consistently.

## Authorization Rules

Only authorized owner may transition or permanently delete.

Permanent delete requires stronger explicit intent.

## Failure Conditions

- invalid transition
- repeat transition
- restore nontrashed item
- permanently delete active item
- unauthorized deletion
- dependent relationship policy conflict

## Test Plan

### Unit — Lifecycle Matrix

Test every valid and invalid transition.

At minimum:

```text
Inbox → Active
Inbox → Done
Inbox → Archived
Inbox → Trash

Active → Done
Active → Archived
Active → Trash

Done → Active (if allowed)
Done → Archived
Done → Trash

Archived → Active
Archived → Trash

Trash → previous/restored state
Trash → permanent delete
```

Also test disallowed direct permanent delete from non-Trash state.

### Integration

- timestamps set/cleared correctly
- restore preserves relationships
- archived/trashed items excluded from default Inbox/search as specified
- permanent delete removes only intended records
- transaction behavior on failure

### API

- transition endpoints return correct status
- invalid transition returns domain error
- restore works
- permanent delete requires explicit endpoint/action

### Authorization

- owner allowed
- other user denied
- anonymous denied
- Library/context membership must not grant deletion rights in future tests

### Negative

- repeated archive
- repeated Trash
- permanent delete non-Trash
- invalid ID
- corrupted/unknown status
- relationship constraints

### Regression

- Tags preserved through archive/trash/restore
- Project/Notebook assignments preserved through recoverable transitions
- search respects lifecycle filters

## Security Testing

- permanent delete cannot be triggered by ordinary update payload
- CSRF/request-method protection appropriate
- audit hooks considered for later ActivityRecord slice

## Completion Criteria

No irreversible deletion can occur through normal lifecycle actions, and transition rules are centrally tested.

---

# Slice 7 — Review Scheduling, Orphan Detection, and Resurfacing

## Roadmap Reference

Phase 7 — Review Engine and Resurfacing

## Objective

Implement the core continuity feature that resurfaces unresolved or context-poor ToDos.

## User-Visible Outcome

A user receives a review queue containing overdue, stale, or orphaned ToDos and can review, snooze, organize, complete, archive, or Trash them.

## Included Scope

- `last_reviewed_at`
- `next_review_at`
- review interval
- mark reviewed
- snooze/reschedule
- orphan rule evaluation
- review queue
- reason codes
- simple rule-based suggested actions

## Excluded Scope

- AI recommendations
- autonomous changes
- notifications outside the app unless separately approved
- semantic related-item recommendations

## Reusable Components

Likely justified:

- `ReviewSchedulingService`
- `OrphanDetectionService` or equivalent rule evaluator

Keep rules composable and individually testable.

Do not create a generic rules engine unless actual complexity requires one.

## Data Changes

Possible:

```text
todos
- last_reviewed_at
- next_review_at
- review_interval_days
```

Or equivalent structured representation.

## Orphan Signals

Initial possible reasons:

- no ProjectSection
- no NotebookSection
- no Tags
- review overdue
- stale beyond configured period
- no terminal objective where rule says one is useful

The system should record/display why an item is in review.

## Authorization Rules

Review queue returns only current user's eligible ToDos.

Review action never grants additional permissions.

## Failure Conditions

- invalid review interval
- review date in invalid format
- archived/trashed ToDo appears unexpectedly
- multiple orphan rules produce duplicate queue entries
- snooze without authorization

## Test Plan

### Unit

For review scheduling:

- calculate next review date
- timezone/date boundary behavior
- explicit date overrides interval
- snooze calculation
- invalid interval

For orphan detection:

- each rule independently true/false
- multiple reasons accumulated
- organized ToDo not orphaned
- archived/trashed excluded
- Done inclusion/exclusion according to policy

### Integration

- queue query returns correct set
- queue ordering
- mark reviewed updates timestamps
- snooze updates next review
- adding Tag/context removes applicable orphan reason
- archive removes from queue
- restoration reevaluates rules

### API

- review queue endpoint
- mark reviewed
- snooze
- review reason serialization

### Authorization

- another user's overdue ToDo never returned
- direct review action on another user's ToDo denied

### Negative

- invalid interval
- invalid snooze date
- duplicate requests
- stale client state
- archived item review action

### Regression

- regular search unaffected
- lifecycle filters still correct
- Tag/Project/Notebook changes properly affect orphan state

### Time Testing

Use controllable/frozen clock utilities where practical.

Test:

- midnight boundaries
- daylight-saving changes if date-time handling is affected
- exactly due vs one second before due

## Security Testing

- review rules cannot expose names/counts of another user's objects
- no automatic mutation occurs solely because an orphan rule fires

## Completion Criteria

The review queue is deterministic, explainable, and thoroughly tested around time and organization changes.

---

# Slice 8 — Related ToDos, Parent/Child Relationships, and Terminal Objectives

## Roadmap Reference

Phase 8 — ToDo Relationships and Objectives

## Objective

Represent related work, hierarchical investigations, and explicit completion objectives.

## User-Visible Outcome

A user can link ToDos, create child investigations, define what success means, and return a child completion/result to its parent.

## Included Scope

- related ToDo relationship
- parent/child relationship
- terminal objective field
- objective satisfied indicator/event
- basic child callback/completion signal
- hierarchy display

## Excluded Scope

- cross-Project request workflow
- agent callbacks
- arbitrary graph execution
- automatic parent completion

## Reusable Components

Potential:

- relationship validation service if multiple relationship types require cycle/integrity checks
- callback payload structure that later cross-context callbacks can reuse if sufficiently stable

Avoid building a generic graph framework.

## Data Changes

Possible:

```text
todos
+ parent_todo_id
+ terminal_objective
+ objective_satisfied_at

related_todos
- todo_id
- related_todo_id
- relation_type
```

Exact schema should prevent duplicate/self-relations where appropriate.

## Authorization Rules

Relationships only between ToDos the actor is authorized to reference.

MVP may restrict relationships to same owner.

## Failure Conditions

- self-parent
- parent cycle
- duplicate relation
- nonexistent target
- relation to another user's ToDo
- callback to missing/deleted parent

## Test Plan

### Unit

- cycle detection
- self-reference rejection
- terminal objective validation
- callback validation

### Integration

- create related link
- remove related link
- create child
- hierarchy query
- child completes and callback/result recorded
- parent is not automatically changed beyond defined behavior
- restore/archive interactions

### API

- relationship endpoints
- child creation
- terminal objective update
- callback action

### Authorization

- unauthorized related target rejected
- unauthorized parent rejected
- callback requires child/parent relation

### Negative

- cyclic hierarchy
- duplicate relation
- deleted parent
- trashed child
- malformed callback
- callback repeated if not idempotent

### Regression

- review engine responds correctly to terminal objective additions
- lifecycle unaffected by relationships
- deleting relationship does not delete ToDos

## Security Testing

- relationship IDs cannot be used to infer inaccessible ToDos
- callbacks never mutate unrelated parent fields

## Completion Criteria

Relationships are safe, non-destructive, cycle-aware, and compatible with future callback concepts.

---

# Slice 9 — Libraries and Many-to-Many Context Membership

## Roadmap Reference

Phase 9 — Libraries

## Objective

Introduce Libraries as curated shared-context boundaries for Projects and Notebooks.

## User-Visible Outcome

A user can create Libraries and place Projects and Notebooks into multiple Libraries for shared discovery.

## Included Scope

- Library CRUD
- Project/Library many-to-many membership
- Notebook/Library many-to-many membership
- Library detail view
- Library-scoped discovery/filtering
- ownership checks

## Excluded Scope

- mutation capabilities
- collaboration between users
- cross-context task requests
- agent permissions

## Reusable Components

Many-to-many membership behavior may share narrowly scoped code if Projects and Notebooks use identical membership mechanics.

Do not collapse Project and Notebook domain models.

## Data Changes

```text
libraries
- id
- user_id
- name
- description
- created
- modified

libraries_projects
- library_id
- project_id

libraries_notebooks
- library_id
- notebook_id
```

Unique join constraints required.

## Authorization Rules

In MVP, only owner-managed context is visible unless otherwise specified.

Library membership grants context only.

It grants **no implicit edit/delete authority** over another object.

## Failure Conditions

- duplicate membership
- membership across owners
- nonexistent object
- delete Library with memberships
- remove membership while references exist

## Test Plan

### Unit

- membership validation if custom

### Integration

- create Library
- add/remove Project
- add/remove Notebook
- same Project in multiple Libraries
- same Notebook in multiple Libraries
- Library-scoped query
- deleting Library does not delete Projects/Notebooks

### API

- Library CRUD
- add/remove membership
- list members
- Library scoped filters

### Authorization

- another user cannot inspect Library
- cross-owner membership denied
- membership alone does not change Project/Notebook authorization

### Negative

- duplicate join
- invalid IDs
- malformed membership type
- delete Library does not cascade destructively

### Regression

- Project/Notebook behavior unchanged outside Library
- search filters continue working
- lifecycle unaffected

## Security Testing

Explicit test:

> A Project and Notebook sharing a Library do not gain mutation authority over each other.

This test should remain as a long-term regression guard.

## Completion Criteria

Libraries provide discoverability/context only and are demonstrably non-authoritative.

---

# Slice 10 — Capability and Permission Model

## Roadmap Reference

Phase 10 — Capability and Permission Model

## Objective

Create an explicit, default-deny authorization model for cross-context operations before cross-context workflows or agents exist.

## User-Visible Outcome

Where the UI exposes cross-context actions, users see only actions allowed by explicit capability policy.

## Included Scope

Capability vocabulary:

```text
discover
read
reference
query
suggest
send_task
callback
contribute
edit
archive
delete
manage_permissions
```

Also:

- capability assignment representation
- policy evaluation service
- scope checking
- backend enforcement
- authorization matrix tests

## Excluded Scope

- complex role hierarchy
- organization-wide IAM
- external identity providers
- autonomous agent permissions beyond foundational support

## Reusable Components

`CapabilityService` or equivalent is justified as cross-cutting.

There must be one authoritative capability evaluation path.

Do not replicate logic per controller.

## Data Changes

Exact model should be chosen via ADR.

Potential concepts:

- subject
- capability
- resource/context
- scope
- grant/deny
- timestamps

Avoid over-generalizing beyond current needs.

## Authorization Rules

Default deny.

Library membership does not imply mutation capabilities.

Destructive permissions are never inferred from `read`, `query`, or `reference`.

## Failure Conditions

- unknown capability
- expired/revoked grant if supported
- resource outside scope
- subject mismatch
- implicit inheritance attempt
- conflicting grants

## Test Plan

### Unit — Capability Matrix

For every capability:

- explicit grant allows intended action
- absence denies action
- unrelated capability does not allow action
- scope mismatch denies
- revoked/disabled grant denies if supported

Examples:

```text
read       != edit
edit       != delete
reference  != contribute
query      != archive
send_task  != callback
```

### Integration

- policy checks against actual Project/Notebook/Library contexts
- capability assignments persist
- removing grant immediately affects authorization
- Library membership without grant remains insufficient

### API

- any capability management endpoint
- attempts to use protected cross-context actions

### Authorization

- only authorized actor can manage permissions
- `manage_permissions` does not accidentally imply delete unless policy says so
- no privilege escalation through self-assignment

### Negative

- unknown resource
- unknown subject
- invalid capability
- duplicate assignment
- scope tampering
- attempted self-escalation

### Regression

- ownership rules still work
- owner behavior remains explicitly documented
- all previous Library non-authority tests pass

## Security Testing

This slice requires a dedicated security-focused test suite.

Test:

- privilege escalation
- confused deputy scenarios
- horizontal authorization bypass
- capability substitution
- resource-ID tampering
- mass assignment of grants
- destructive action without capability

## Completion Criteria

All cross-context mutation can be routed through one tested policy layer, and default-deny behavior is proven.

---

# Slice 11 — Cross-Context Requests and Callbacks

## Roadmap Reference

Phase 11 — Cross-Context Requests and Callbacks

## Objective

Allow safe cooperation between contexts without granting direct remote mutation authority.

## User-Visible Outcome

A Project/Notebook/ToDo can send a request to another permitted context, where the recipient can accept, modify, reject, link, or create a ToDo, then return a callback.

## Included Scope

- CrossContextRequest model
- request status lifecycle
- source/target metadata
- send request
- recipient inbox
- accept
- modify
- reject
- link to existing ToDo
- create ToDo from request
- callback/result
- capability enforcement
- transactional handling

## Excluded Scope

- autonomous agent orchestration
- arbitrary remote code/action execution
- hidden automatic acceptance
- bulk request processing

## Reusable Components

Likely:

- `CrossContextRequestService`
- callback representation reused from ToDo relationship slice if compatible
- capability service
- activity recorder later or placeholder hook if Slice 12 follows

## Data Changes

Possible:

```text
cross_context_requests
- id
- source_type
- source_id
- target_type
- target_id
- request_type
- payload
- status
- created_by
- created
- resolved_by
- resolved_at
```

Prefer constrained polymorphism or explicit typed relationships based on ADR.

## Authorization Rules

- source must have `send_task` or relevant capability
- recipient action requires target-context authority
- callback requires `callback` capability where cross-context
- acceptance never grants ongoing edit permission

## Failure Conditions

- source deleted
- target deleted
- stale request
- double resolution
- capability revoked between send and accept
- malformed payload
- recipient tries to mutate unauthorized source

## Test Plan

### Unit

- request state transition matrix
- payload validation
- callback validation

### Integration

- send request
- recipient sees request
- accept
- reject
- modify
- link existing ToDo
- create new ToDo
- callback recorded
- transaction rollback if ToDo creation fails
- revoked capability behavior

### API

- request creation
- inbox
- resolve actions
- callback

### Authorization

Test all combinations:

```text
send_task granted / not granted
target access granted / not granted
callback granted / not granted
Library shared / not shared
direct edit granted / not granted
```

Critical regression:

> `send_task` must not allow direct edit of target content.

### Negative

- duplicate resolution
- stale version
- malformed payload
- invalid source/target type
- missing target
- request to Trash/archived context according to policy

### Regression

- capability tests all pass
- Library non-authority tests all pass
- ToDo parent/child callbacks unaffected

### Transaction Tests

Simulate failure after partial work and verify:

- request status unchanged if dependent creation fails
- no orphan ToDo created
- no partial callback persisted

## Security Testing

- payload injection
- resource enumeration
- privilege escalation
- callback spoofing
- replay/double-submit
- direct-object-reference tampering

## Completion Criteria

Cross-context cooperation occurs through an explicit, auditable, capability-checked request workflow.

---

# Slice 12 — Activity History and Audit Foundation

## Roadmap Reference

Phase 12 — Dashboard and Activity History

## Objective

Record important changes in a consistent reusable activity stream.

## User-Visible Outcome

A user can see recent important actions and understand how an item changed.

## Included Scope

Activity records for:

- create
- edit where meaningful
- lifecycle transition
- tag relationship changes
- Project/Notebook assignment changes
- Library membership changes
- cross-context request actions
- capability changes
- destructive actions

## Excluded Scope

- immutable compliance-grade audit ledger
- external SIEM
- full event sourcing
- reconstructing all object state from events

## Reusable Components

`ActivityRecorder` is justified as cross-cutting.

It should have a small, explicit API.

Do not convert the whole application into an event-driven architecture solely to support activity history.

## Data Changes

Possible:

```text
activity_records
- id
- user_id / actor_id
- action
- subject_type
- subject_id
- context_type
- context_id
- metadata JSON
- created
```

Avoid storing secrets or full sensitive payloads.

## Authorization Rules

Users may only view activity records they are authorized to see.

## Failure Conditions

- activity write failure
- deleted subject
- metadata serialization issue
- unauthorized history access

## Test Plan

### Unit

- ActivityRecorder input normalization
- redaction rules if any

### Integration

- records created for required actions
- records not duplicated unexpectedly
- activity write participates in transaction where correctness requires it
- failure policy documented: whether activity failure rolls back domain operation

### API

- activity list endpoint
- filtering/pagination

### Authorization

- cross-user activity hidden
- target-context rules enforced

### Negative

- malformed filters
- missing referenced object
- sensitive metadata not exposed

### Regression

- lifecycle and request workflows still complete correctly
- no unexpected excessive activity rows for read-only actions

### Performance

- pagination mandatory
- indexes for actor/context/time queries
- avoid loading full metadata unnecessarily

## Security Testing

- no passwords/tokens/secrets stored
- metadata safely serialized
- inaccessible object names/details not leaked through activity text

## Completion Criteria

Important mutations produce consistent, safe, queryable activity records.

---

# Slice 13 — Dashboard and MVP Integration Polish

## Roadmap Reference

Phase 12 — Dashboard and Activity History / MVP Release Gate

## Objective

Integrate the existing MVP capabilities into a coherent daily-use interface and close usability gaps without adding new major domain features.

## User-Visible Outcome

The user sees what requires attention immediately after login.

## Included Scope

Dashboard showing:

- Inbox count
- due-for-review count
- orphan count
- recent ToDos
- recent Projects
- recent Notebooks
- recent Libraries
- recent activity

Also:

- navigation consistency
- empty states
- loading/error states
- pagination consistency
- accessibility review
- responsive web behavior
- MVP documentation polish

## Excluded Scope

- research collections
- AI
- document generation
- native apps
- vector search
- major redesign

## Reusable Components

UI/API query components may be shared if already repeated.

Avoid introducing a broad design system unless actual UI duplication justifies it.

## Data Changes

Ideally none.

If dashboard performance requires indexes, add only evidence-based indexes.

## API Changes

May add a dashboard summary endpoint if it reduces repeated queries meaningfully.

Do not create duplicate business rules in dashboard code.

## Test Plan

### Unit

Only for nontrivial summary logic.

### Integration

- counts match underlying queries
- archived/trashed exclusions correct
- recent lists correctly scoped

### API

- dashboard response
- empty workspace
- populated workspace
- pagination/limits

### Authorization

- no cross-user counts
- no cross-context leakage

### UI / End-to-End

Test critical user journeys:

#### Journey A — Capture and rediscover

1. login
2. create ToDo
3. add Tag
4. search Tag
5. open ToDo

#### Journey B — Organize

1. create Project/section
2. assign ToDo
3. create Notebook/section
4. assign according to membership policy
5. find by context

#### Journey C — Review

1. create review-due ToDo
2. see dashboard count
3. open review queue
4. snooze
5. verify it leaves current due list
6. advance clock
7. verify it returns

#### Journey D — Lifecycle

1. archive
2. verify excluded
3. restore
4. Trash
5. restore
6. Trash
7. permanent delete with explicit confirmation

#### Journey E — Library safety

1. create Library
2. add Project and Notebook
3. verify shared discovery
4. verify no implied mutation capability

#### Journey F — Cross-context request

1. grant send capability
2. send request
3. receive
4. accept/create ToDo
5. callback
6. verify history

### Negative End-to-End

- unauthorized resource URL
- invalid form/API payload
- stale action
- deleted target
- revoked permission
- repeated destructive action

### Regression

Run entire suite across all slices.

## Accessibility Testing

At minimum:

- keyboard navigation for primary flows
- form labels
- error announcements/association
- focus behavior
- semantic headings
- no color-only status meaning

## Performance Testing

Reasonable MVP checks:

- Inbox with representative number of ToDos
- review queue
- dashboard
- Tag-filtered search
- Project/Notebook pages

Look for:

- N+1 queries
- excessive API calls
- unbounded result sets
- slow count queries

Do not optimize beyond evidence.

## Security Testing

Perform an MVP authorization sweep:

- attempt IDOR across each major resource
- attempt cross-user mutations
- attempt cross-context mutations without capability
- attempt permanent deletion through normal update endpoint
- attempt capability escalation
- inspect API payloads for secret/internal leakage

## Completion Criteria

MVP 1 is usable as a coherent daily application and every major domain boundary has regression tests.

---

# 3. MVP Release Verification Suite

Before calling MVP 1 complete, run a dedicated release verification pass.

## 3.1 Functional Matrix

Verify:

- authentication
- ToDo create/edit/view
- Tags
- search
- Projects
- ProjectSections
- Notebooks
- NotebookSections
- lifecycle
- Archive
- Trash
- restore
- permanent deletion
- review scheduling
- orphan detection
- review queue
- related ToDos
- parent/child
- terminal objectives
- Libraries
- capabilities
- cross-context requests
- callbacks
- activity history
- dashboard

---

## 3.2 Authorization Matrix

For each protected resource:

```text
anonymous
owner
different user
authorized cross-context actor
unauthorized cross-context actor
```

For each mutation:

```text
create
read
edit
archive
Trash
restore
delete
link
assign
send request
callback
manage permission
```

Every denied path must be tested explicitly.

---

## 3.3 Destructive-Action Matrix

Test:

- Trash before permanent delete
- permanent delete requires explicit action
- restoring retains expected relationships
- deleting Library does not delete Projects/Notebooks
- deleting Tag does not delete ToDos
- removing relationship does not delete either ToDo
- deleting section follows documented policy
- failed transaction does not leave partial deletion

---

## 3.4 Data Integrity Matrix

Verify:

- foreign keys
- unique Tag constraints
- unique join rows
- no invalid ownership references
- no invalid parent cycles
- no duplicate Library membership
- no invalid capability grants
- no unresolved transaction partials

---

## 3.5 Review Engine Matrix

Verify:

- orphan with no tags
- orphan with no Project/Notebook
- overdue review
- not-yet-due review
- stale item
- archived exclusion
- Trash exclusion
- snooze
- exact due boundary
- multiple orphan reasons
- reason removal after organization

---

## 3.6 Regression Matrix for Shared Modules

For every shared module added, maintain direct tests plus consumer tests.

Examples:

### CapabilityService

- direct unit tests
- Project consumer tests
- Notebook consumer tests
- Library consumer tests
- CrossContextRequest tests

### ReviewSchedulingService

- direct unit tests
- review queue integration
- dashboard count integration

### ActivityRecorder

- direct tests
- lifecycle integration
- request integration
- permission change integration

### Ordered Section Logic

- ProjectSection tests
- NotebookSection tests

A shared module is not considered safe merely because its isolated unit tests pass.

---

# 4. Slice Bloat Review Checklist

At the end of every slice, answer:

- [ ] Did we add a class that has only one trivial caller?
- [ ] Did we create an interface with only one implementation and no clear boundary need?
- [ ] Did we wrap a CakePHP feature without adding domain value?
- [ ] Did we introduce a factory/registry/plugin before multiple implementations exist?
- [ ] Did we add columns/tables for future features not used by this slice?
- [ ] Did we add a dependency that duplicates framework/native behavior?
- [ ] Did we duplicate a business rule already implemented elsewhere?
- [ ] Can any new abstraction be removed while preserving clarity?
- [ ] Is the total conceptual complexity lower or justified by the behavior delivered?

If the answer exposes unnecessary architecture, simplify before merging.

---

# 5. Slice Reuse Review Checklist

At the end of every slice, document:

## Existing Code Reused

List shared code used by this slice.

## New Shared Code

List reusable modules introduced and why they qualify.

## Intentionally Local Code

List logic kept local because reuse is not yet proven.

## Remaining Duplication

List any deliberate duplication and why it is safer or clearer than extraction.

## Defense-in-Depth Duplication

Identify repeated safety checks that exist at different layers intentionally.

---

# 6. Recommended Repository Layout for Slice Documents

Create a directory:

```text
docs/slices/
```

For active implementation, copy the appropriate slice into a specific contract file:

```text
docs/slices/000-foundation.md
docs/slices/001-authentication.md
docs/slices/002-todo-capture.md
docs/slices/003-tags-search.md
...
```

Each file may contain:

```text
Slice Contract
Implementation Notes
Test Plan
Completion Report
```

This creates a permanent implementation history without requiring the roadmap document itself to contain transient development notes.

---

# 7. Copilot Prompt Prefix for Every Slice

Use the following prefix when asking Copilot to implement a slice:

```text
Before changing code, read:

- docs/application-design.md
- docs/roadmap.md
- docs/mvp-feature-list.md
- docs/development-slice-protocol.md
- docs/mvp-development-slices.md
- the current docs/slices/<slice>.md contract
- any ADRs relevant to this slice

Implement ONLY the current slice.

Before coding:
1. restate the slice objective;
2. list included and excluded scope;
3. identify likely files to change;
4. identify existing reusable components;
5. identify any proposed shared component and justify it;
6. present the complete test plan;
7. identify authorization/security risks.

Then implement the slice.

Before completion:
1. run focused tests;
2. run related integration tests;
3. run the full test suite;
4. run lint/coding standards;
5. run static analysis;
6. review migration reversibility;
7. inspect the diff for scope leakage;
8. inspect for duplicated business logic;
9. inspect for unnecessary abstractions/code bloat;
10. update documentation;
11. produce the Slice Completion Report.

STOP after this slice. Do not implement the next slice.
```

---

# 8. MVP Completion Principle

MVP 1 should emerge from a chain of independently trustworthy slices.

The target is not merely:

```text
all features exist
```

The target is:

```text
all required features exist
+ their boundaries are tested
+ destructive behavior is controlled
+ authorization is proven
+ shared logic is reusable
+ regressions are guarded
+ unnecessary abstractions were avoided
```

The final rule for every slice is:

> A slice is not complete because the happy path works. It is complete when the intended behavior, denied behavior, failure behavior, integration behavior, and regression behavior are all understood and tested.

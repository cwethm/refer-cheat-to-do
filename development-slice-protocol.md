# Development Slice Protocol

## Status

**Repository Policy — Mandatory for all implementation slices**

This document defines how every development slice must be planned, implemented, tested, reviewed, and closed.

Its purpose is to keep development aligned with the product roadmap, prevent uncontrolled scope expansion, encourage reusable code where it provides real value, reduce redundant implementations, and prevent architectural overgrowth.

This protocol applies to human developers, GitHub Copilot, coding agents, and automated development workflows.

---

# 1. Core Rule

Every development slice must be:

- small enough to understand;
- independently testable;
- independently reviewable;
- independently reversible where practical;
- explicitly connected to roadmap requirements;
- bounded by a written scope;
- complete enough to produce a meaningful user-visible or system-visible outcome.

A slice must not become a general-purpose refactor, architectural rewrite, or excuse to implement unrelated future features.

---

# 2. Slice Definition

A **development slice** is a bounded unit of implementation that delivers one coherent capability across the layers required to make that capability work.

A slice may include:

- database migration;
- entity/model changes;
- service logic;
- authorization;
- API endpoint;
- UI behavior;
- validation;
- tests;
- documentation.

A slice should include only the layers necessary to make that capability complete.

Example:

```text
Slice:
Create and view a ToDo

May include:
- migration
- ToDo entity/table
- validation
- creation service
- API endpoint
- authorization
- Inbox display
- tests

Should not include:
- Projects
- Libraries
- agent infrastructure
- document generation
- unrelated refactors
```

---

# 3. Mandatory Slice Lifecycle

Every slice must follow this sequence:

```text
1. Read governing documents
2. Define slice contract
3. Identify dependencies
4. Identify reusable existing code
5. Design minimum implementation
6. Define tests before coding
7. Implement
8. Run focused tests
9. Run regression tests
10. Review scope leakage
11. Review duplication / bloat
12. Update documentation
13. Produce slice completion report
14. Stop
```

Copilot or another coding agent must not automatically continue into the next slice.

---

# 4. Governing Documents

Before modifying code, the developer or agent must consult the relevant repository documents.

At minimum:

```text
docs/application-design.md
docs/roadmap.md
docs/mvp-feature-list.md
docs/development-slice-protocol.md
```

If relevant ADRs exist, they must also be consulted.

The slice implementation must not silently contradict these documents.

If a contradiction is discovered:

1. stop architectural expansion;
2. document the conflict;
3. propose an ADR or documentation change;
4. do not invent a hidden workaround.

---

# 5. Slice Contract

Before implementation begins, every slice must define a **Slice Contract**.

The contract must contain:

## 5.1 Slice Name

Short and specific.

Example:

```text
Slice 2A — Create and Edit Tags
```

## 5.2 Objective

One paragraph describing the capability being delivered.

## 5.3 User-Visible Outcome

What can the user do after the slice that they could not do before?

## 5.4 Included Scope

Explicit list of work permitted in the slice.

## 5.5 Excluded Scope

Explicit list of related work that must not be implemented yet.

## 5.6 Dependencies

Existing modules, schemas, services, APIs, or policies required by the slice.

## 5.7 Data Changes

Tables, columns, indexes, relationships, migrations, or fixtures affected.

## 5.8 Security / Authorization Rules

Who may perform each operation?

## 5.9 Failure Conditions

Expected validation, authorization, persistence, and integration failure cases.

## 5.10 Test Plan

Tests that must exist before the slice can be considered complete.

## 5.11 Rollback / Reversal Notes

How the change can be reverted or disabled if necessary.

## 5.12 Completion Criteria

Objective statements that determine whether the slice is finished.

---

# 6. Scope Encapsulation Policy

A slice must remain encapsulated.

## Allowed Changes

Changes directly required to deliver the stated slice.

## Conditionally Allowed Changes

Small enabling changes to shared code when:

- the slice cannot be implemented safely without them;
- the change is backward-compatible where practical;
- tests cover the shared behavior;
- the change benefits existing callers or removes verified duplication.

## Disallowed Changes

Unless separately approved:

- unrelated formatting across many files;
- renaming unrelated classes;
- repository-wide refactors;
- speculative abstraction layers;
- implementing future roadmap phases;
- adding unused libraries;
- adding unused tables or columns;
- introducing new frameworks for a single small feature;
- changing unrelated API behavior;
- rewriting working modules merely for stylistic preference.

---

# 7. Vertical Slice Rule

Prefer complete vertical behavior over horizontal scaffolding.

Good:

```text
Create ToDo
→ validate
→ authorize
→ persist
→ return via API
→ display in Inbox
→ test
```

Avoid:

```text
Create all database tables first
Create all entities second
Create all controllers third
Create all UI later
```

The first approach produces independently testable working software.

---

# 8. Reuse Policy

Reusable code is encouraged where multiple systems genuinely perform the same operation.

However, reuse must not create unnecessary abstraction.

The goal is:

> Reuse stable behavior, not hypothetical future behavior.

---

# 9. Shared Module Rule

Before writing new logic, check whether equivalent logic already exists.

If the same responsibility appears in multiple places, prefer one shared implementation where practical.

Examples of likely reusable responsibilities:

- authorization checks;
- lifecycle transition validation;
- soft-delete handling;
- review-date calculation;
- search/filter parsing;
- pagination;
- audit/activity recording;
- cross-context capability checks;
- identifier validation;
- API error formatting;
- JSON serialization conventions;
- timestamp handling;
- request/callback state transitions.

Shared modules should be narrowly defined and independently testable.

---

# 10. Anti-Redundancy Rule

Do not create multiple independent implementations of the same rule unless there is a documented reason.

Bad:

```text
ProjectsController contains its own permission logic.
NotebooksController contains different permission logic.
LibrariesController contains a third permission implementation.
```

Preferred:

```text
CapabilityPolicy / AuthorizationService

Projects
Notebooks
Libraries
    ↓
shared policy evaluation
```

The system should have one authoritative implementation for a business rule whenever practical.

---

# 11. Redundant Safety Checks Are Allowed

The anti-redundancy rule does **not** forbid layered safety checks.

Independent checks may exist when they protect different boundaries.

Example:

```text
UI disables Delete button
        +
API authorization rejects unauthorized request
        +
Domain service validates destructive transition
        +
Database constraints protect referential integrity
```

These are not considered harmful duplication because each protects a different layer.

Rule:

> Duplicate business logic is discouraged. Independent defense-in-depth checks are encouraged.

---

# 12. Rule of Two / Rule of Three for Abstraction

Do not automatically create a reusable framework after the first occurrence of a pattern.

Use this guideline:

## First occurrence

Implement clearly and locally.

## Second occurrence

Compare implementations.

If they are truly the same responsibility, consider extracting a shared helper/service.

## Third occurrence

A shared abstraction should normally be created unless there is a strong reason not to.

Exceptions:

Create a shared module on first use when the responsibility is obviously cross-cutting, such as:

- authorization;
- audit logging;
- API error envelopes;
- encryption;
- authentication;
- transaction handling.

---

# 13. Shared Module Qualification Test

Before extracting reusable code, answer all of these:

1. Do at least two callers perform substantially the same task?
2. Is the shared behavior stable enough to name clearly?
3. Can the module have one responsibility?
4. Can it be tested independently?
5. Will extraction reduce total code or risk?
6. Does it avoid requiring callers to understand unnecessary concepts?
7. Is the abstraction simpler than the duplicated code?

If several answers are "no", do not extract yet.

---

# 14. Anti-Bloat Policy

Reusable code must reduce complexity, not merely relocate it.

Avoid:

- generic "manager" classes;
- generic "helper" classes with unrelated functions;
- abstract base classes with only one implementation;
- unnecessary interfaces;
- unnecessary factories;
- wrappers around framework APIs without added domain value;
- dependency injection for trivial stateless utilities;
- service classes that only forward one call;
- speculative plugin systems;
- generic repositories duplicating CakePHP ORM;
- custom event buses where normal application services suffice.

Prefer:

- framework conventions;
- small domain services;
- specific names;
- direct dependencies;
- composition;
- small pure functions where suitable.

---

# 15. Abstraction Budget

Each slice has an implicit abstraction budget.

A slice should introduce the minimum number of new concepts needed to solve its problem cleanly.

Before adding a new:

- interface;
- base class;
- trait;
- service;
- factory;
- registry;
- event;
- plugin;
- adapter;

the developer must be able to state:

```text
Problem this abstraction solves:
Existing duplication or coupling:
Why a simpler solution is insufficient:
Known callers:
```

If there is only one caller and no strong architectural boundary, prefer a simpler implementation.

---

# 16. Framework-First Policy

Use CakePHP and language-standard functionality before creating custom infrastructure.

Prefer built-in mechanisms for:

- ORM associations;
- validation;
- middleware;
- routing;
- authentication;
- request handling;
- serialization;
- migrations;
- transactions;
- testing.

Custom abstractions should exist because the domain requires them, not because an equivalent generic framework feature can be rewritten locally.

---

# 17. Domain Service Policy

Create a domain/application service when logic:

- spans multiple tables or aggregates;
- implements an important business rule;
- requires transaction management;
- is used from multiple entry points;
- should be usable by HTTP, CLI, workers, or future agents;
- needs isolated unit testing.

Examples:

```text
TodoLifecycleService
ReviewSchedulingService
CapabilityService
CrossContextRequestService
ActivityRecorder
```

Do not create services for simple one-table CRUD unless meaningful domain behavior exists.

---

# 18. Controller Policy

Controllers must remain thin.

Controllers may:

- parse requests;
- call authorization;
- invoke application/domain services;
- format responses.

Controllers should not:

- contain complex permission rules;
- contain lifecycle algorithms;
- calculate review schedules;
- coordinate several unrelated persistence operations;
- contain reusable domain logic.

---

# 19. Transaction Boundary Policy

Operations that must succeed or fail together should use an explicit transaction.

Examples:

- accept cross-context request + create ToDo + record callback;
- permanently delete object + clean required dependent records;
- change capability assignment + write audit record;
- move object between ownership contexts.

Tests should verify rollback behavior where practical.

---

# 20. Database Integrity Policy

Application validation is not sufficient where the database can enforce a critical invariant.

Use appropriate:

- foreign keys;
- unique constraints;
- NOT NULL constraints;
- indexes;
- check constraints where practical.

Do not rely on database cascades for destructive behavior unless explicitly designed and documented.

---

# 21. Migration Policy

Each schema change must:

- be represented by a migration;
- be reversible where practical;
- avoid destructive data loss unless explicitly approved;
- include indexes required by known query patterns;
- preserve compatibility with existing data;
- avoid adding speculative future columns.

A slice should not create tables for distant roadmap features merely because they may eventually be needed.

---

# 22. Test-First Planning Rule

Tests do not have to be written before every line of implementation, but the **test plan must be written before implementation begins**.

The test plan determines:

- expected behavior;
- failure behavior;
- authorization boundaries;
- edge cases;
- regression risks.

---

# 23. Mandatory Test Layers

Each slice must consider the following.

## Unit Tests

For isolated algorithms and domain services.

Examples:

- review date calculation;
- lifecycle transition rules;
- capability evaluation.

## Integration Tests

For collaboration between services, ORM, transactions, and database rules.

## API / Controller Tests

For:

- request validation;
- response structure;
- authentication;
- authorization;
- status codes.

## Regression Tests

When fixing a bug, create a test that fails before the fix whenever practical.

---

# 24. Permission Test Matrix

Any slice involving authorization must test both allowed and denied cases.

Minimum pattern:

```text
Owner allowed
Authorized collaborator allowed
Unauthorized user denied
Anonymous user denied
Cross-context caller denied by default
Explicit capability allows intended operation
Explicit capability does not grant unrelated operation
```

Destructive operations require especially thorough denied-case coverage.

---

# 25. Lifecycle Test Matrix

Any object with lifecycle states must test:

- valid transitions;
- invalid transitions;
- repeated transitions;
- restore behavior;
- archived behavior;
- Trash behavior;
- permanent deletion behavior;
- authorization for each transition.

Example:

```text
Inbox → Active             allowed
Active → Done              allowed
Done → Active              allowed if policy permits
Active → Trash             allowed
Trash → Active             restore path
Trash → permanent delete   explicit only
Archived → review queue    normally excluded
```

---

# 26. Cross-Context Test Requirements

When a slice interacts across Projects, Notebooks, Libraries, or other contexts, tests must verify:

- source is identified;
- target is identified;
- relationship exists where required;
- Library membership alone does not grant mutation;
- explicit capability is required;
- capability scope is honored;
- denied operations do not partially mutate data;
- activity/audit information is recorded when required.

---

# 27. Negative Testing Requirement

Do not test only successful behavior.

Every slice should identify negative cases such as:

- invalid IDs;
- malformed input;
- missing required fields;
- duplicate data;
- unauthorized access;
- invalid state transition;
- stale request;
- deleted parent;
- missing relationship;
- transaction failure.

---

# 28. Regression Gate

Before a slice is complete:

1. run tests specific to the slice;
2. run related module tests;
3. run the full project test suite;
4. run linting;
5. run static analysis;
6. run migration checks where applicable.

A slice cannot be declared complete while known regressions remain.

---

# 29. Coverage Philosophy

Do not optimize for a meaningless coverage percentage.

Prioritize coverage of:

- domain rules;
- authorization;
- destructive actions;
- state transitions;
- transaction boundaries;
- data integrity;
- complex queries;
- previously broken behavior.

Simple framework-generated getters/setters do not need artificial tests merely to increase coverage numbers.

---

# 30. Slice Isolation Check

At the end of implementation, review the diff and ask:

- Did this slice modify unrelated modules?
- Did it introduce behavior not mentioned in the contract?
- Did it change existing APIs unnecessarily?
- Did it introduce abstractions with no current use?
- Did it duplicate existing logic?
- Did it create hidden coupling to a future feature?
- Could any file changes be removed without affecting the slice?

Unnecessary changes should be removed before completion.

---

# 31. Code Reuse Review

Before closing a slice, identify:

## New reusable code

What shared logic was introduced?

## Existing reusable code used

What existing modules prevented duplicate implementation?

## Remaining duplication

What duplication remains intentionally, and why?

## Premature abstraction avoided

What logic was deliberately kept local because reuse is not yet justified?

This forces reuse decisions to be explicit.

---

# 32. Documentation Update Rule

A slice must update documentation when it changes:

- domain behavior;
- API contracts;
- data model;
- permissions;
- configuration;
- environment setup;
- roadmap status;
- architecture decisions.

Documentation should be changed in the same pull request as the code.

---

# 33. ADR Trigger Rule

Create an Architecture Decision Record when a slice introduces or resolves a decision that:

- significantly constrains future architecture;
- changes ownership or authorization boundaries;
- changes object relationships;
- selects a major dependency;
- creates a new cross-cutting abstraction;
- changes persistence strategy;
- changes client/API contracts substantially.

Do not create ADRs for trivial implementation details.

---

# 34. Dependency Introduction Policy

Do not add a new dependency unless:

1. the slice requires functionality not reasonably provided by existing dependencies;
2. the dependency is actively maintained;
3. its purpose is documented;
4. licensing is acceptable;
5. it does not duplicate existing capabilities;
6. the expected benefit exceeds its maintenance cost.

For small utilities, prefer native PHP/CakePHP functionality.

---

# 35. Performance Policy

Do not prematurely optimize.

However, avoid obvious structural problems.

Check for:

- N+1 queries;
- missing indexes;
- loading unbounded result sets;
- repeated identical database queries;
- expensive work in loops;
- synchronous work that clearly belongs in a queue.

Optimization should be driven by known usage or measurable risk.

---

# 36. Security Gate

Every slice must answer:

- Does this expose new data?
- Does this create a new mutation path?
- Does this introduce a new upload/input surface?
- Does this alter authorization?
- Does this affect deletion?
- Does this affect cross-context access?
- Does this introduce secrets/configuration?

If yes, relevant security tests and documentation are mandatory.

---

# 37. No Hidden Feature Policy

Do not add undocumented automatic behavior.

Examples of prohibited hidden behavior:

- automatic deletion;
- automatic cross-project edits;
- automatic permission inheritance;
- automatic publication;
- silent background reassignment;
- unlogged agent mutation.

Automatic behavior must be:

- documented;
- visible where appropriate;
- testable;
- reversible where practical.

---

# 38. Copilot Operating Protocol

When Copilot is asked to implement a slice, it must:

## Before Coding

1. read the relevant documentation;
2. summarize the slice objective;
3. list files/modules likely to change;
4. state dependencies;
5. identify existing reusable code;
6. present the test plan;
7. state excluded scope.

## During Coding

Copilot must:

- stay inside the slice contract;
- prefer existing framework features;
- reuse stable shared modules;
- avoid speculative abstractions;
- keep controllers thin;
- enforce authorization server-side;
- add migrations only when required;
- add tests alongside implementation.

## Before Declaring Completion

Copilot must:

1. run focused tests;
2. run full regression tests;
3. run lint/static analysis;
4. inspect the diff for scope leakage;
5. inspect for duplicated business logic;
6. inspect for unnecessary abstractions;
7. update documentation;
8. report remaining issues.

## Stop Rule

After completing the slice, Copilot must stop.

It must not begin the next roadmap slice unless explicitly instructed.

---

# 39. Slice Completion Report

Every completed slice should produce a report in this format.

```markdown
# Slice Completion Report

## Slice
<name>

## Objective
<objective>

## Delivered
- ...

## Files Added
- ...

## Files Modified
- ...

## Database Changes
- ...

## API Changes
- ...

## Reusable Components Added
- ...

## Existing Shared Components Reused
- ...

## Authorization Rules
- ...

## Tests Added
- ...

## Test Results
- focused tests:
- full suite:
- lint:
- static analysis:

## Security Review
- ...

## Scope Review
- ...

## Known Limitations
- ...

## Deferred Work
- ...

## Documentation Updated
- ...

## Recommended Next Slice
<name only; do not implement>
```

---

# 40. Pull Request Policy

Prefer one coherent slice per pull request.

A pull request should be easy to explain as:

> This PR adds X capability.

Avoid PRs that require:

> This PR adds X, rewrites Y, prepares Z, replaces A, and also refactors B.

If substantial unrelated work is discovered, create a separate issue or future slice.

---

# 41. Commit Policy

Commits should reflect meaningful implementation steps.

Examples:

```text
feat(todo): add ToDo creation service
test(todo): add creation authorization tests
feat(api): expose ToDo create endpoint
docs(todo): document capture slice
```

Avoid commits containing large mixtures of unrelated formatting, dependency changes, and feature work.

---

# 42. Definition of Done

A slice is **Done** only when:

- [ ] Slice Contract exists
- [ ] included functionality works
- [ ] excluded scope was respected
- [ ] authorization is implemented
- [ ] validation is implemented
- [ ] required migrations exist
- [ ] focused tests pass
- [ ] regression tests pass
- [ ] lint passes
- [ ] static analysis passes or documented exceptions exist
- [ ] security implications reviewed
- [ ] duplicate business logic reviewed
- [ ] unnecessary abstractions removed
- [ ] documentation updated
- [ ] completion report produced
- [ ] next slice not started automatically

---

# 43. Reusable Slice Template

Copy this section when beginning a new slice.

```markdown
# Slice Contract

## Slice Name

## Roadmap Reference

## Objective

## User-Visible Outcome

## Included Scope
- 

## Excluded Scope
- 

## Dependencies
- 

## Existing Reusable Components
- 

## Proposed New Reusable Components
- 

## Data Changes
- 

## API Changes
- 

## Authorization Rules
- 

## Failure Conditions
- 

## Transaction Boundaries
- 

## Test Plan

### Unit
- 

### Integration
- 

### API
- 

### Authorization
- 

### Negative Cases
- 

### Regression
- 

## Security Considerations
- 

## Rollback / Reversal
- 

## Documentation Changes
- 

## Completion Criteria
- 
```

---

# 44. Final Engineering Principle

The protocol should produce software that grows through **small, stable, comprehensible capabilities**.

The repository should prefer:

```text
fewer concepts
+ stronger boundaries
+ shared proven behavior
+ thorough tests
```

over:

```text
more layers
+ speculative abstractions
+ duplicated business rules
+ broad slices
```

The guiding rule is:

> Build the smallest complete slice, reuse proven behavior, centralize true business rules, duplicate only independent safety checks, test boundaries aggressively, and stop when the slice is complete.

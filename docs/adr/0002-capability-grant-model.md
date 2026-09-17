# ADR 0002 — Capability grants are explicit, flat and default deny

## Status
Accepted (Slice 10)

## Context
Slice 10 introduces authorization for cross-context operations before any cross-context workflow or
agent exists. The MVP needs a model that is small enough not to become an IAM system, but explicit
enough that later slices (cross-context requests, agents) cannot accidentally inherit authority.

Options considered:

1. **Role hierarchy** — roles such as `viewer`, `editor`, `admin` per resource.
2. **Flat capability grants** — one row per `(subject, capability, resource)`.
3. **Policy expressions** — rule documents evaluated per request.

## Decision
Use option 2: a single flat `capability_grants` table holding one row per
`(subject_user_id, resource_type, resource_id, capability)`, with an explicit `revoked_at`.

- The capability vocabulary is fixed and closed: `discover`, `read`, `reference`, `query`,
  `suggest`, `send_task`, `callback`, `contribute`, `edit`, `archive`, `delete`,
  `manage_permissions`.
- Resource kinds are `project`, `notebook`, `library`.
- Evaluation is **default deny**. A decision is `true` only when the subject owns the resource or an
  active grant names exactly that capability on exactly that resource.
- **Nothing is inferred.** No capability implies another, `manage_permissions` does not imply
  `delete`, and Library membership contributes nothing to a decision.
- Owners implicitly hold every capability on resources they own. This is the only implicit rule and
  it is stated here so it cannot be reintroduced accidentally elsewhere.
- Self-granting is rejected in the application *and* by a database check constraint, so a subject
  cannot escalate its own authority even if it holds `manage_permissions`.

`App\Service\CapabilityService` is the single evaluation path. Controllers ask it a question; they
never re-derive authorization.

## Consequences
- The matrix is easy to test exhaustively: for each capability, a grant allows it and allows nothing
  else. That test is the specification.
- Roles, groups, expiry windows and delegation chains are not supported. If they are needed later
  they can be layered on top of grants without changing the evaluation contract.
- Enforcement is added to endpoints deliberately, one at a time. Slice 10 enforces only the `read`
  capability on Project and Notebook detail views; every mutation remains owner-only until a later
  slice adds a reviewed cross-context path.

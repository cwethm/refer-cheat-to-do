# Slice 10 — Capability and Permission Model

## Slice Contract

### Goal
Introduce an explicit, default-deny authorization model for cross-context operations, before any
cross-context workflow or agent exists.

### In scope
- The closed capability vocabulary, persisted grant representation, and revocation.
- One authoritative evaluation path (`CapabilityService`).
- Capability management API.
- Backend enforcement of `read` on Project and Notebook detail views.
- An exhaustive authorization matrix and a dedicated security test suite.

### Out of scope
- Role hierarchies, organization IAM, external identity providers, agent permissions, expiry
  windows, and cross-context mutation (Slice 11).

### Schema changes
Migration `20260916190000_CreateCapabilityGrants` creates `capability_grants`:
- `subject_user_id` (FK `CASCADE`), `grantor_user_id` (FK `RESTRICT`).
- `resource_type`, `resource_id`, `capability`, `revoked_at`, timestamps.
- Unique index on `(subject_user_id, resource_type, resource_id, capability)`.
- `capability_grants_resource_type_chk` restricting resource kinds.
- `capability_grants_capability_chk` restricting the vocabulary to the twelve capabilities.
- `capability_grants_subject_not_grantor_chk` making self-granting impossible in the database.

The design rationale is recorded in `docs/adr/0002-capability-grant-model.md`.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `GET` | `/api/capabilities?resource_type=&resource_id=` | Active grants on a resource the caller may administer |
| `POST` | `/api/capabilities` | Creates a grant (`201`) |
| `DELETE` | `/api/capabilities/{id}` | Revokes a grant immediately |

`grantor_user_id` and `revoked_at` are not mass-assignable; the grantor is the session identity.

### Authorization rules
- **Default deny.** Ownership or an exact active grant, nothing else.
- Owners hold every capability on resources they own; this is the only implicit rule.
- No capability implies another: `read != edit`, `edit != delete`, `reference != contribute`,
  `query != archive`, `send_task != callback`, and `manage_permissions` implies neither `edit` nor
  `delete`.
- Library membership contributes nothing to a decision.
- A subject may never grant to itself, even holding `manage_permissions`.
- Resources the caller may not administer return `404`, so ids are not enumerable.

---

## Slice Completion Report

### Delivered behaviour
Grant creation, listing, revocation, delegation by `manage_permissions` holders, and read
enforcement on Project and Notebook detail views. Revocation takes effect on the next evaluation.

### Shared components
- **Added:** `App\Service\CapabilityService` — the single evaluation path, owning the vocabulary,
  resource map, ownership rule, grant/revoke transitions and duplicate handling.
- **Added:** `AppController::capabilities()` so controllers share one evaluator rather than
  constructing policy logic locally.
- **Reused:** the API envelope, `404`-for-unauthorised convention, the `23505` conflict translation
  pattern established in Slices 3 and 9, and the owner-scoped listing behaviour of Slices 4–5.

### Tests added
- `CapabilityServiceTest` — 45 tests. For every one of the twelve capabilities: absence denies, an
  explicit grant allows exactly that capability and no other (a full 12×12 non-implication matrix).
  Plus owner-holds-all, resource scope mismatch, subject mismatch, immediate revocation, reissue of
  a revoked grant, duplicate rejection, unknown capability / resource type / resource / subject,
  non-manager rejection, self-escalation rejection, `manage_permissions` non-implication,
  delegation, revoke authority, double revoke, Library-membership non-implication across the whole
  vocabulary, and direct database constraint checks for the capability whitelist and uniqueness.
- `CapabilitiesControllerTest` — 25 tests covering anonymous `401`, grantor taken from session,
  `revoked_at` not mass-assignable, duplicate `409`, unknown capability/resource-type `404`, missing
  fields `400`, invalid query parameters `400`, non-owner grant and list denial, self-escalation
  `403`, delegation, immediate revocation, subject cannot revoke its own grant, read grant enabling
  a cross-user view, read grant not enabling edit or delete, read grant not leaking into listings,
  revoked read grant denying the view, Library membership plus a Library `read` grant still being
  insufficient for a member Project, and an ownership regression.

### Test results
```
PHPUnit: OK (453 tests, 1026 assertions)
PHPCS:   no errors
PHPStan: no errors
```

### Migration verification
`bin/cake migrations rollback --target 20260916180000` dropped `capability_grants`;
`bin/cake migrations migrate` recreated it with all three check constraints, both foreign keys and
the unique index, verified directly in PostgreSQL.

### Security review
Privilege escalation is blocked at three layers: the service rejects self-grants, the database
rejects them with a check constraint, and management authority is required for both granting and
revoking. Confused-deputy scenarios are covered: a `manage_permissions` holder can delegate but
gains no data authority, and a subject cannot revoke the grant that records its own access. Read
access is deliberately narrow — it exposes a detail view only and never listings, mutations or
lifecycle transitions.

### Anti-bloat / reuse review
One table, one service, one controller, one ADR. No policy-expression engine, no role tables, and no
per-controller policy code.

### Known limitations
- No expiry windows; revocation is manual and immediate.
- Enforcement currently covers only `read` on Project and Notebook detail views. The remaining
  capabilities are defined and evaluable but not yet wired to endpoints, which is intentional: they
  are consumed by the cross-context slices that follow.

### Deferred work
- Wiring `send_task`, `callback`, `suggest` and `contribute` to endpoints (Slice 11).
- Recording grant changes in activity history (Slice 12).

---

## Slice Decision: APPROVED

Reason:
All required tests and quality gates pass. The default-deny matrix, non-implication rules, and
anti-escalation protections are verified at service, database and API level. No blocking security,
data-integrity, architecture, scope, or regression issue remains.

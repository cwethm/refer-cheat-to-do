# Slice 11 — Cross-Context Requests and Callbacks

## Slice Contract

### Goal
Allow contexts to cooperate safely — a Project or Notebook can ask another context to do something —
without granting any direct remote mutation authority.

### In scope
- `CrossContextRequest` model with a `pending → accepted | rejected` lifecycle.
- Send, recipient inbox, sender outbox, view, modify, accept, reject, link/create ToDo, callback.
- Capability enforcement on every transition, re-evaluated at the moment of the transition.
- Transactional acceptance.

### Out of scope
- Agent orchestration, remote action execution, automatic acceptance, bulk processing.

### Schema changes
Migration `20260916200000_CreateCrossContextRequests` creates `cross_context_requests` with source
and target type/id pairs, `request_type`, `title`, `body`, `status`, creator and resolver foreign
keys, `resolved_at`, `resolution_note`, `resulting_todo_id` (FK `SET NULL`), `callback_summary` and
`callback_at`. Check constraints enforce:
- `source_type` / `target_type` ∈ {`project`, `notebook`};
- `request_type` ∈ {`task`, `question`, `suggestion`};
- `status` ∈ {`pending`, `accepted`, `rejected`};
- a pending row has no resolver and no `resolved_at`, and a resolved row has both;
- a context cannot address itself.

Constrained polymorphism (a typed pair validated by check constraints) was chosen over separate
tables per context pair, consistent with the flat model in ADR 0002.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `POST` | `/api/cross-context-requests` | Sends a request (`201`) |
| `GET` | `/api/cross-context-requests/inbox` | Pending requests the caller may resolve |
| `GET` | `/api/cross-context-requests/outbox` | Requests the caller sent |
| `GET` | `/api/cross-context-requests/{id}` | Sender or resolver view |
| `PATCH` | `/api/cross-context-requests/{id}` | Recipient rewords a pending request |
| `POST` | `/api/cross-context-requests/{id}/accept` | Accepts, optionally linking or creating a ToDo |
| `POST` | `/api/cross-context-requests/{id}/reject` | Rejects with an optional note |
| `POST` | `/api/cross-context-requests/{id}/callback` | Records the outcome back to the source |

### Authorization rules
- **Send** requires `read` on the source *and* `send_task` on the target.
- **Resolve** (modify / accept / reject) requires `contribute` on the target.
- **Callback** requires target-context authority *and* `callback` on the source.
- Acceptance grants nothing ongoing: the sender still cannot read or edit the target.
- Status, creator, resolver, resolution and callback fields are never mass-assignable.
- A created ToDo is owned by the target owner; a linked ToDo must already be owned by them.

---

## Slice Completion Report

### Delivered behaviour
The full request lifecycle with transactional acceptance. A failed link or ToDo creation rolls the
request back to `pending`, so a request is never half-resolved.

### Shared components
- **Added:** `App\Service\CrossContextRequestService` — the single authority for request transitions
  and their authorization.
- **Reused:** `CapabilityService` for every decision (no policy logic in the controller),
  `TodoLifecycleService::STATUS_INBOX` for created ToDos, the API envelope, the `DomainException` →
  HTTP translation pattern from Slices 9 and 10, and the `404`-for-unauthorised convention.

### Tests added
- `CrossContextRequestServiceTest` — 30 tests: send authority on both ends, self-addressed request,
  unknown context type / request type / target, malformed payload, inbox and outbox scoping, sender
  can view but not resolve, unrelated user cannot view, modify (including empty change set),
  accept creating a ToDo owned by the target owner, accept linking an existing ToDo, rejection of a
  foreign ToDo **with rollback verification**, accept without a ToDo, reject, double resolution,
  modify after resolution, capability revoked between send and resolve, callback ordering, callback
  capability, single callback, empty summary, `send_task` non-implication, Library membership not
  enabling sending, and two direct database constraint checks.
- `CrossContextRequestsControllerTest` — 22 tests: anonymous `401` on send and inbox, send denied
  without `send_task`, mass-assignment of `status` / creator / `resulting_todo_id`, malformed
  payload `400`, invalid context type `404`, recipient inbox, empty sender inbox, unrelated user
  `404`, sender cannot accept, modify-then-accept creating a ToDo, linking an existing ToDo,
  rejected foreign link with rollback, reject, double resolution `409`, callback capability gate,
  and the critical regressions that `send_task` grants neither read nor edit of target content and
  that acceptance confers no ongoing authority.

### Test results
```
PHPUnit: OK (505 tests, 1156 assertions)
PHPCS:   no errors
PHPStan: no errors
```

### Migration verification
`bin/cake migrations rollback --target 20260916190000` dropped the table; `migrate` recreated it
with all six check constraints and three foreign keys, verified in PostgreSQL.

### Security review
Every transition re-evaluates capabilities at the time of the action, so revocation between send and
accept is effective immediately. The sender gains no read or write access to the target as a result
of sending or of acceptance — asserted explicitly. Recipients cannot mutate the source; they can
only record a callback, and only with the `callback` capability. Unauthorised access is `404`.

### Anti-bloat / reuse review
One table, one service, one controller. No workflow engine, no polymorphic base classes, no
speculative agent hooks.

### Known limitations
- Requests have no expiry or reminder; stale requests simply stay pending.
- Only Projects and Notebooks are valid contexts; ToDo-to-ToDo requests are covered by the Slice 8
  relationship model instead.
- Callbacks are a single recorded summary, not a thread.

### Deferred work
- Recording request transitions in activity history (Slice 12).
- Surfacing inbox counts on the dashboard (Slice 13).

---

## Slice Decision: APPROVED

Reason:
All required tests and quality gates pass. Capability enforcement, transactional resolution, and the
critical `send_task`-is-not-edit regression are verified. No blocking security, data-integrity,
architecture, scope, or regression issue remains.

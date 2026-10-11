# Slice 20 — Operational maturity: performance, logging and observability

## Slice Contract

### Status
IMPLEMENTED

### Goal
Make the running system diagnosable and keep the dashboard fast as a user's data grows, without
changing any externally visible behaviour.

### In scope
- Replacing the dashboard's per-ToDo PHP iteration for orphan reasoning with a single bounded query,
  behind the existing response shape.
- A repeatable performance regression test that fails if the dashboard's query count or row scan
  grows with the number of ToDos.
- Structured, correlated application logging with a request identifier, excluding credentials and
  personal content from log output.
- Error reporting for unhandled exceptions, and a documented asset caching strategy that does not
  rely solely on modification-time query strings.

### Out of scope
- Any change to API response shapes, routes, or authorization.
- Caching layers, queues, or background workers.
- Third-party telemetry services.

### Dependencies
Slice 13 (dashboard). The known limitation "dashboard orphan reasoning iterates the user's
non-hidden ToDos in PHP" is recorded in `docs/mvp-1-completion-report.md` and is what this slice
closes.

### Design constraints
- The response body must be byte-for-byte equivalent for a given dataset before and after the
  change; the existing dashboard tests are the contract.
- Logs must never contain passwords, tokens, session identifiers, or ToDo content.

### Test plan
- PHPUnit: existing dashboard assertions unchanged and passing; a new test asserting a constant
  query count across datasets of 1, 100 and 1000 ToDos; a test asserting correct orphan counts at the
  boundaries the PHP implementation handled.
- A log-content test asserting that no credential or content field appears in emitted log lines.
- Full regression suite plus the browser suite, since the dashboard is the client's landing view.

### Risks
- Rewriting reasoning logic in SQL can silently change edge-case counts. Boundary cases must be
  asserted before the rewrite, not after.
- Logging changes are easy to classify as trivial; they are not, because they can leak data.

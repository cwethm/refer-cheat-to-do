# Slice 25 — Huginn orchestration

## Slice Contract

### Status
PROPOSED — fourth slice of the post-MVP domain program; Phase G, scheduled after Phase F

### Goal
Allow external, event-driven automation to trigger the capabilities built in Slices 22–24 without
granting that automation broader access than a human user would have.

### In scope
- An inbound webhook surface authenticated per automation identity, with replay protection.
- An outbound event surface emitting the events automations subscribe to.
- Reuse of the agent identity and capability model from Slice 24 — no new authorization concept.
- Idempotency keys so a repeated delivery cannot duplicate a mutation.

### Out of scope
- Operating or configuring Huginn itself.
- Arbitrary outbound requests to user-supplied URLs without an allowlist.
- Any bypass of the human approval step defined in Slice 24.

### Dependencies
Slice 24 (agent identity and capability grants), Slice 20 (structured logging and correlation
identifiers, needed to make automated activity diagnosable).

### Required threat and authorization review
Before implementation the slice must state: how inbound requests are authenticated and how replays
are rejected; how outbound destinations are restricted against server-side request forgery; what
payload content is emitted and whether it can contain user content; and what rate limits apply.

### Test plan
- PHPUnit: unauthenticated and mis-signed inbound requests are rejected; a replayed delivery is
  rejected; a repeated delivery with the same idempotency key mutates once; automation cannot exceed
  its granted capabilities; outbound destinations outside the allowlist are refused; rate limits are
  enforced.
- A test asserting that an inbound trigger cannot perform a destructive action that a human approval
  step would otherwise gate.

### Risks
- This slice turns the application into a network client and a network callee simultaneously, so it
  carries both SSRF and replay exposure.
- Automation volume can hide a single malicious action inside ordinary traffic; logging and
  attribution from Slice 20 are a prerequisite, not an optional extra.

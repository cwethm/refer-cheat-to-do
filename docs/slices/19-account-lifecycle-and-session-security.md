# Slice 19 — Account lifecycle and session security

## Slice Contract

### Status
PROPOSED

### Goal
Replace operator-only account creation with a controlled account lifecycle, so the product can be
used without a shell on the server, without opening uncontrolled public registration.

### In scope
- An invitation-based account creation flow: an existing operator issues a single-use, expiring
  invitation; the recipient sets a password and signs in.
- Password change for a signed-in user and password reset for a locked-out user.
- Session hardening: session regeneration on privilege change, explicit session expiry, and rate
  limiting of authentication attempts.
- Keeping `bin/cake set_user_password` as the operator recovery path.

### Out of scope
- Open self-service public registration.
- External identity providers, SSO, and multi-factor authentication.
- Organization or team membership models.

### Dependencies
Slice 1 (authentication). `HostHeaderMiddleware` already guarantees that generated links cannot be
poisoned through the `Host` header; reset and invitation links depend on that guarantee.

### Design constraints
- Invitation and reset tokens must be single-use, time-limited, stored hashed, and compared in
  constant time. The plain token exists only in the delivered link.
- Enumeration must not be possible: requesting a reset for an unknown address returns the same
  response as for a known one.
- `App.fullBaseUrl` must be correct wherever links are generated; this is already enforced in
  production by `HostHeaderMiddleware`.

### Test plan
- PHPUnit: token issuance, single use, expiry, hashed storage, constant-time comparison, enumeration
  resistance, rate limiting, session regeneration, and the denied path for every new endpoint.
- Migration reversibility for the new token table.
- Browser tests for: redeeming an invitation; changing a password; requesting and completing a
  reset; an expired token; and a reused token.
- Accessibility audit and responsive overflow coverage for the new views.

### Risks
- This slice adds the first credential-bearing links the system emits. Token handling mistakes here
  are account-takeover bugs, so the threat model belongs in the contract review, not in code review.
- Rate limiting introduces shared state; its storage must be explicit rather than incidental.

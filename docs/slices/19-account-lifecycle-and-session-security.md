# Slice 19 — Account lifecycle and session security

## Slice Contract

### Status
IMPLEMENTED — built out of phase order as the prerequisite of Slice 36 (sharing cheat sheets with
named people): a recipient must be able to have an account, and the rate limiting here protects the
share lookup. The decisions taken where the contract left room are listed under "Delivered
decisions" below.

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

### Delivered decisions
- **Who may invite.** There is no role model, so any signed-in account may invite an address. An
  invitation grants nothing: the new account owns nothing and receives no capability. Issuing is
  rate limited per inviter.
- **Where the link goes.** The invitation and reset links are emailed only to the invited or
  resetting address, never returned to the caller, so redeeming one proves control of the address.
  Slice 36 relies on that when it shares by identifier.
- **Tokens.** 32 random bytes, base64url encoded (43 characters), stored only as a SHA-256 hash in
  `account_tokens`, looked up by hash and checked with `hash_equals`. A token is single use
  (a conditional `UPDATE … WHERE used IS NULL AND expires > now` inside the redeeming
  transaction), expires (invitation 7 days, reset 1 hour; `Accounts.*` in `config/app.php`), and
  issuing a new link of the same purpose for the same address retires the earlier one. A rejected
  password rolls the transaction back, so the link stays usable.
- **Tokens in the fragment.** Links are `App.fullBaseUrl` + `/app#invite=…` or `/app#reset=…`.
  The fragment is never sent in a request line, so tokens stay out of access logs and `Referer`
  headers. The client reads it once, removes it from the address bar and posts it in a JSON body.
- **Session invalidation.** `users.credential_version` advances whenever a password changes, by
  any path — change, reset or `bin/cake set_user_password`. A session records the version and its
  sign-in time; a session whose version is stale, or older than `Accounts.sessionLifetime`
  (12 hours), is destroyed and answered with `401`. Changing a password therefore keeps the
  current session (it is regenerated with the new version) and ends every other one; a reset ends
  all of them. A password change also retires outstanding reset links.
- **Session hardening.** The session id is regenerated on sign-in and on password change, and
  destroyed on sign-out. The cookie is `HttpOnly` and `SameSite=Strict`, PHP's strict mode rejects
  uninitialised ids, and the idle timeout is explicit (`Session.timeout`, 120 minutes).
- **Rate limiting.** Explicit storage in `rate_limit_hits` (scope, HMAC-SHA256 bucket of the key
  under the application salt, time). Addresses and IPs are never stored in clear. Limits live in
  `RateLimit.*` in `config/app.php`: failed sign-ins per address and per client, reset requests per
  address and per client, failed token redemptions per client, wrong current passwords per account
  and invitations per inviter. A full bucket is refused with `429` and `Retry-After` before any
  credential is checked. Counting is not atomic, so concurrent requests can overshoot a limit by the
  number in flight.
- **Enumeration.** Sign-in failures are identical for unknown addresses and wrong passwords (an
  unknown address is still checked against a bcrypt hash). Reset requests and invitations answer
  `202` with the same message whether or not an account uses the address. A reset delivery failure
  is logged without detail, never reported. Sending mail only for known addresses leaves a timing
  difference; the per-client and per-address limits bound what it can reveal.
- **CSRF.** The new mutating endpoints are covered by the CSRF middleware. Sign-in stays outside
  it, as before.
- **Activity.** Account events are not written to the activity history: its subject types describe
  owned records, and adding account events is a separate audit decision.

### Delivered API
- `POST /api/auth/password` — signed in; `current_password`, `new_password`. A wrong current
  password is `422` (and counted); the same or an invalid new password is `422`. Success regenerates
  the session and ends every other session of the account.
- `POST /api/auth/password-reset` — `email`; always `202` with the same message.
- `POST /api/auth/password-reset/complete` — `token`, `password`; `200`. An unknown, used or
  expired token is `400` "This link is invalid or has expired."; an unacceptable password is `422`.
  The caller signs in afterwards.
- `POST /api/invitations` — signed in; `email`; `202` with the same message whether or not the
  address has an account; `503` if the message could not be sent.
- `POST /api/invitations/accept` — `token`, `password`; `201` with the new account's email. The
  new user signs in afterwards.
- `POST /api/auth/login` now answers `429` when a limit is full; `POST /api/auth/logout` destroys the
  session.

Mail is sent through the `default` email transport (`EMAIL_TRANSPORT_DEFAULT_URL`, e.g.
`smtp://host:25`) from `EMAIL_FROM`.

The `/app` client adds "Forgot your password?" on the sign-in panel, a panel that redeems an
invitation or reset link from the address, and an **Account** view with password change and
invitation forms.

### Completion
- Migration `20261011090000_CreateAccountTokensAndRateLimitHits` applies, rolls back and reapplies
  on PostgreSQL.
- PHPUnit: `AccountTokenServiceTest`, `RateLimiterTest` and `AccountLifecycleControllerTest` cover
  issuance, single use, expiry, hashed storage, constant-time comparison, enumeration resistance,
  every rate limit, session regeneration and destruction, stale-version and over-age session
  rejection, CSRF denial and the denied path of each new endpoint. Existing tests now sign in
  through `SignedInSessionTrait`. The full suite passes (834 tests).
- PHPCS and PHPStan pass.
- Client unit and security tests pass (`composer ui-test`).
- `tests/e2e/account.spec.mjs` uses a local SMTP sink (`tests/e2e/mailbox.mjs`, started by global
  setup) and covers redeeming an invitation, a reused invitation, changing a password (this
  session kept, the other ended), requesting and completing a reset, a reused reset link, an
  expired reset link, an unknown address, a malformed link and a planted session id. The
  accessibility spec audits the Account view and the reset and link panels and checks overflow.
  The whole browser suite passes (70 tests).

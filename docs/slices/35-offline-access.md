# Slice 35 — Offline access

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). **Requires an ADR and the owner's explicit approval
before any implementation**, because it deliberately amends standing client security rules.

### Goal
Let the owner keep chosen bundles (Slice 34) available on their phone without a connection,
read-only: for material that is frequently needed and for material needed only for a particular
meeting or discussion.

### In scope
- A per-device **Make available offline** action on a bundle, a list of what is stored on this
  device with its size and `as_of` time, **Remove from this device**, and an explicit refresh when
  online. Nothing is stored until the owner asks.
- Read-only viewing of a stored bundle offline, with the same summary-first layout as online.
- Only the bundle's contents as returned by the Slice 34 contents endpoint are stored. Sensitive
  members are present only if the bundle allows them, and are marked.
- Limits: a documented cap on stored size, on the number of bundles on a device, and a maximum age
  for a copy since its last refresh. A temporary bundle's copy is removed when the bundle expires.
- Removal of all stored copies on explicit logout. How copies are protected, and whether they can be
  read when the session has expired, is the ADR's decision (below).
- A clear statement in the client that an offline copy is best-effort, because mobile browsers may
  remove stored data for sites that have not been used recently. A printed cheat sheet (Slice 23) is
  the fallback.

### Out of scope
- Recording or editing anything while offline, an offline write queue, background sync and conflict
  resolution. Offline capture (for example a record made after an event with no signal) would be its
  own slice, and a much larger one.
- Caching API responses generally, or any content that is not part of a bundle the owner selected.
- Push notifications, sharing a stored copy between devices, or a native application.

### Dependencies
Slice 34 (bundles and the contents endpoint), Slice 26 (cards and cheat sheets). A secure context:
service workers require HTTPS, which the README already requires for production.

### Required decision (ADR)
The ADR must resolve each of the following before implementation:

1. **Amend the standing client rules, narrowly.** `docs/front-end-testing-policy.md` forbids an
   offline queue, service worker or background retry without an ADR, and its security checklist
   (enforced by `tests/js/client-security.test.mjs`) forbids `localStorage`, `sessionStorage`,
   `indexedDB` and `document.cookie`. The ADR must name the one module allowed to use browser
   storage, require that it never touches credentials, cookies or session state, amend the test so
   the exception is that module only (every other module stays prohibited), and update the policy.
2. **At-rest protection and unlock.** Options: (a) readable offline with only the device's own lock
   protecting it; (b) encrypted with a key derived from a PIN or passphrase held only on the device
   and unlocked per use, wiped after repeated failures or after its maximum age; (c) readable only
   while an earlier online unlock is still valid. The leaning is (b). The ADR must say what a lost,
   stolen or shared phone can and cannot reveal, and that the server cannot revoke a stored copy.
3. **No-store and caching policy.** `docs/application-design.md` requires authenticated responses to
   be `private, no-store`. The ADR must state that the service worker does not cache API responses
   through the HTTP cache, and that the single, explicit store of bundle copies is the only
   persistence, so the existing policy is not relaxed anywhere else.
4. **App shell.** If the application's scripts and styles must also be stored for the app to open
   offline, that cache must follow the release-manifest and invalidation strategy in
   `docs/application-design.md`, so a stale shell is never served against a changed API.
5. **Eviction and refresh.** What the client does when storage is evicted or full, when the session
   has expired, and when the stored `as_of` is older than the maximum age.

### Required threat and authorization review
- This slice creates the largest new local exposure in the project: private content at rest on a
  device the server does not control. The controls are explicit selection, sensitive-type exclusion,
  the ADR's at-rest protection, a maximum age, expiry removal and logout removal. Each needs a test.
- A stored copy must never be larger in scope than what the owner could read online at the time it
  was fetched. Nothing in the store may be fetched by a path other than the contents endpoint.
- The existing no-HTML-sink rule (`textContent` only) is the main defence against script access to
  the store and must keep passing.
- A service worker widens the cross-site and caching surface; its scope, the origins it will fetch
  and the requests it may intercept are limited to the application's own origin.
- Nothing is logged about stored content. The server learns only the ordinary authenticated request
  for the contents endpoint.

### Test plan
- Client unit tests for the store module: size cap, count cap, maximum-age expiry, wipe on logout,
  wipe on bundle expiry, and encryption round-trip with the chosen at-rest design.
- Updated `tests/js/client-security.test.mjs`: the storage APIs are allowed in the one named module
  only; no other module uses them; that module does not reference cookies or credentials.
- Browser tests using Playwright's offline mode: make a bundle available; go offline; read it;
  confirm a sensitive member is absent by default; confirm expiry and logout remove the copy;
  confirm that nothing is stored before the owner asks and that no API response outside a bundle
  is persisted.
- Accessibility and responsive checks for the new controls and the offline reading view.

### Risks
- A caching or unlock bug here discloses private content without any server-side check, so the
  denied and expiry paths matter more than the happy path.
- Browser storage behaves differently across mobile browsers; the owner's device and browser are an
  input to the ADR, not an afterthought.
- Offline reading invites offline writing. Holding the line at read-only keeps conflict handling out
  of this slice.

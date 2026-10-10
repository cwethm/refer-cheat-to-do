# Slice 14 — Client-facing daily workflow

## Slice Contract

### Goal
Provide a small, accessible browser client for everyday capture, organization, rediscovery, and review
using the existing API.

### In scope
- Same-origin browser application at `/app`.
- Session sign-in/sign-out and the existing dashboard summary.
- ToDo capture, paginated search/status/tag filtering, and completion/reopening.
- Tag creation, attachment/detachment, deletion, and review queue actions with readable reasons.
- Responsive layout, keyboard-visible focus, accessible form labels and status announcements.

### Out of scope
- New domain rules, schema or API endpoints.
- Public account registration or client-side credential persistence.
- Project, Notebook, Library, capability, or cross-context request management screens.

## Delivered behaviour
The client calls the existing session-based API with same-origin credentials. It provides a dashboard,
capture form, bounded ToDo list with server-side search and filters, tag management, and the review
queue. Review actions are based on the API's `suggested_actions`; lifecycle actions use the existing
explicit endpoints. Pagination metadata remains visible to the client.

User-provided API content is inserted as text, never parsed as HTML. Resource IDs are checked before
being used in action URLs. Session credentials are not copied into browser storage. Asset URLs include
the file modification time so deployments do not depend on stale cached client code.

## Architecture
- `Pages::display('app')` serves the client at `/app`; `/` remains the health endpoint.
- CakePHP's application template path is configured to the repository's existing top-level `templates/`
  directory.
- The UI is plain HTML, CSS, and an ES module; it adds no runtime JavaScript dependencies.
- Existing API authorization and validation remain authoritative.

## Accessibility and responsive review
- A skip link, semantic page landmarks, ordered headings, labeled controls, native keyboard-operable
  buttons, visible focus indicators, live status/error messages, and selected navigation state are
  provided.
- Key text color contrast ratios were checked against their backgrounds: body text 12.72:1, muted text
  4.59:1, primary accent 5.67:1, and error text 5.55:1.
- Chromium verified no horizontal overflow at 320, 375, 768, and 1280 CSS pixels.

## Tests and verification
- `PagesControllerTest`: app route, essential labeled controls, same-origin assets, and no remote scripts
  or fonts.
- `tests/js/app.test.mjs`: pagination/search URL construction, API envelope and error handling, ID
  validation, readable review reasons, text-only insertion, same-origin session requests, and checks
  against unsafe HTML sinks and persistent credential storage.
- Chromium end-to-end smoke: sign-in, capture, tag creation/attachment, search and tag filtering,
  tag detachment, review scheduling, completion, and sign-out.
- Full repository quality gate: 630 PHPUnit tests / 1702 assertions; 9 client tests; PHP lint, PHPCS,
  and PHPStan all pass. Composer validation is clean.

## Known limitations
- The client does not yet manage Projects, Notebooks, Libraries, or section assignment. The review
  queue explains when a ToDo needs a section, but assigning one remains outside this slice.
  Slices 15-17 close this.
- Accounts remain operator-managed through `bin/cake set_user_password`. Slice 19 closes this.
- Assistive-technology testing with real screen readers remains future work. The automated axe
  WCAG 2.1 A/AA audit, keyboard-reachability checks and responsive overflow assertions added in
  Phase A (`tests/e2e/accessibility.spec.mjs`) replace the one-off manual measurements recorded
  above.

## Follow-up
The manual Chromium smoke pass described under "Tests and verification" has been superseded by the
automated suite in `tests/e2e/`. See `docs/front-end-testing-policy.md` for the strategy every
later client slice inherits.

## Slice Completion Report

Slice 14 integrates the existing API into a responsive daily-use web client without adding domain
features, schema changes, dependencies, or new authorization rules. The slice is approved after the
focused and full regression suites pass.

Slice Decision: **APPROVED**

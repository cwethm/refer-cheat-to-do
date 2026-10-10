# Front-end testing policy

This policy applies to every slice that changes the web client served at `/app`. It exists so
client slices inherit a known testing shape instead of re-deciding it each time.

## Test layers

| Layer | Location | Runs with | Covers |
| --- | --- | --- | --- |
| Server render | `tests/TestCase/Controller/PagesControllerTest.php` | `composer test` | The `/app` route, required landmarks and labelled controls, same-origin asset URLs, absence of remote scripts and fonts. |
| Client unit | `tests/js/app.test.mjs` | `composer ui-test` / `npm test` | Pure exported functions: URL construction, pagination clamping, API envelope unwrapping, error-message selection, ID validation, review-reason labels, text-only insertion. |
| Client security | `tests/js/client-security.test.mjs` | `composer ui-test` / `npm test` | The standing security checklist below, across every file in `webroot/js/`. |
| Browser end-to-end | `tests/e2e/app.spec.mjs` | `npx playwright test` | Real user journeys against a real PHP server and database: session lifecycle, capture, tag management, search and filtering, pagination boundaries, empty states, API error surfacing, expired-session recovery. |
| Accessibility and responsive | `tests/e2e/accessibility.spec.mjs` | `npx playwright test` | An axe WCAG 2.1 A/AA audit of each view, keyboard reachability of the sign-in path, and horizontal-overflow assertions at 320, 375, 768 and 1280 CSS pixels. |

### Which layer to use

- A pure function gets a **client unit** test. Do not drive the browser to test a string builder.
- Anything that depends on markup, focus, layout, or a real API response gets a **browser**
  test. Do not assert on hand-built DOM fixtures; they drift from the template.
- Every new view, panel, or dialog gets an **accessibility** audit and is added to the
  responsive overflow loop.
- Every new client file is automatically covered by the **client security** suite; it reads the
  directory rather than a fixed file list, so nothing can be added without being checked.

## Running the suites

```bash
composer check        # PHP lint, coding standards, static analysis, PHPUnit, client unit tests
npm test              # client unit and client security tests only
npx playwright test   # browser end-to-end, accessibility and responsive tests
```

The browser suite owns a dedicated database (`refer_cheat_to_do_e2e` by default) and a dedicated
account. `tests/e2e/global-setup.mjs` drops and recreates that database, applies migrations and
seeds the account on every run, so results do not depend on prior state. It must never be pointed
at the `default` connection or the PHPUnit `test` connection.

The server under test runs with `DEBUG=false`, which means `APP_FULL_BASE_URL` must equal the
served base URL or `HostHeaderMiddleware` rejects every request with a 400 response. This is
handled by `tests/e2e/environment.mjs`; override it through the `E2E_*` environment variables
rather than by editing the config.

CI runs the PHP gate and client unit tests in the `quality` job and the browser suites in the
`browser` job. Both must pass.

## Browser support and progressive enhancement

- The client is a dependency-free ES module. The supported baseline is the current and previous
  stable release of Chrome, Edge, Firefox and Safari — that is, browsers with native support for
  ES modules, `fetch`, `URLSearchParams`, optional chaining, nullish coalescing and
  `Element.replaceChildren`.
- No transpilation, bundler, or polyfill step exists and none may be introduced without an ADR.
  Code must therefore target that baseline directly.
- The client is **not** progressively enhanced: `/app` requires JavaScript. The API remains fully
  usable without it, and that is the supported fallback. A slice that wants a no-JavaScript
  experience must say so in its contract.
- Assets are versioned with the file modification time so a deployment cannot serve stale client
  code. Any new asset must follow the same pattern.

## Offline and slow-network behaviour

Quick capture is the most-used path, so its failure behaviour is specified rather than incidental:

- A failed `fetch` is reported as "The server could not be reached. Check your connection and try
  again." and the entered text is left in the form so nothing is lost.
- A non-JSON or unparseable error body still produces a readable message containing the HTTP
  status; the client never shows a raw exception.
- A `401` returns the visitor to the sign-in panel with "Your session has ended. Please sign in
  again." rather than failing silently.
- There is no offline queue, service worker, or background retry, and none may be added without an
  ADR. Unsent captures are not persisted; this is a deliberate consequence of storing no client
  state.

## Client security regression checklist

These invariants are enforced by `tests/js/client-security.test.mjs` and must keep passing in
every future client slice:

1. No HTML or code-execution sink: no `innerHTML`, `outerHTML`, `insertAdjacentHTML`,
   `document.write`, `eval` or `new Function`. API text is inserted with `textContent`.
2. No persisted credentials or session state: no `localStorage`, `sessionStorage`, `indexedDB` or
   `document.cookie` access. The session cookie is managed by the server alone.
3. Same-origin session requests only: every `fetch` sets `credentials: 'same-origin'`, never
   `include`, and never targets an absolute remote URL.
4. No remote scripts, styles or fonts in the client template, and no inline script blocks.
5. Resource IDs are validated with `isValidId` before being interpolated into an action URL.
6. Authorization stays on the server. The client may hide a control, but hiding is never the
   enforcement mechanism.

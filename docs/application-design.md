# Application Design

## Scope

This repository provides the API and first daily-use client for an information continuity and ToDo system.

## Architectural direction

- API-first CakePHP 5 application
- PostgreSQL as the primary datastore
- thin controllers with business rules delegated to services/entities/tables
- backend-enforced authorization and validation
- JSON response and error conventions for API endpoints
- same-origin browser client using the session-based API

## Deferred capabilities

The following future systems are intentionally not implemented in this initialization slice:

- research-agent orchestration
- document generation pipelines
- advanced AI-provider integrations
- full domain schema and workflow engine

## Initial API convention

Successful API responses use a consistent `data` envelope.

Error responses use:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted data is invalid.",
    "details": {}
  }
}
```

## Operational maturity (Slice 20)

### Dashboard query budget

The original dashboard loaded every eligible ToDo and its tags before evaluating review and
orphan predicates in PHP. A single SQL aggregate now returns the two counts without hydrating
those ToDos or tags. Recent lists retain their existing capped limits and serialization.
The regression suite compares datasets of 1, 100 and 1000 ToDos, checking both query count and
rows returned to the application, as well as exact review/staleness boundaries and response bytes.

“Bounded rows” refers to rows transferred and processed in PHP, not constant physical database
work: exact uncached counts necessarily examine matching records in PostgreSQL. The aggregate
does not introduce a cache or precomputed counters. Tag existence checks must not multiply
ToDo counts when more than one tag is attached.

### Application logs and exception reporting

Application logs are local structured JSON records, correlated by a server-generated request
identifier. Request input is not a source of trusted logging metadata. Unhandled exceptions and
PHP errors are reported through CakePHP's configured error logger, without changing exception
rendering or API response bodies. Exception messages and trace arguments are intentionally omitted:
database exceptions can contain SQL values, and application exceptions can contain personal content.

Every record has `timestamp`, `level`, and `requestId`; HTTP scopes also have an allowlisted `method`.
`request.completed` records include `status` and `durationMs`. Error records contain only an event
type, exception class or PHP error level, a validated relative code location, and line number.
Other application log calls discard their free-text message and arbitrary context. CLI reports use
`logs/cli-error.log` and `logs/cli-debug.log`; web reports use `logs/error.log` and `logs/debug.log`.
The local safe logger configuration is deliberate: `LOG_DEBUG_URL` and `LOG_ERROR_URL` overrides
are not supported because substituting a generic engine could bypass redaction.

Never log passwords, authorization tokens, cookies, session identifiers, request bodies, query
strings, ToDo titles/notes, email addresses, or arbitrary logging context. Keep database query logging
disabled in deployed environments. Web-server, proxy, and PostgreSQL logs are separate operational
surfaces: disable body/header capture and redact query strings there too. Do not enable verbose
request dumps to investigate a production failure.

Keep `logs/` outside the served document root, writable only by the application and operators;
rotate local logs with the host's existing rotation facility and apply a short, access-controlled
retention policy. No third-party telemetry, queue, or background worker is required.
Logging is best-effort: sink failures must not change responses and do not trigger an unsafe
fallback. Operators must check log-directory permissions and available disk space independently.

### Asset caching and deployment

The current `/app` template adds modification-time query strings to the CSS and entry module.
That is a development convenience, **not** a sufficient production invalidation strategy:
deployment tools may preserve timestamps, and imported ES modules have their own URLs.
CakePHP's `Asset.cacheTime` also does not configure the web server's handling of files served
directly from `webroot`.

For the current unversioned asset URLs, configure the serving web server to send
`Cache-Control: no-cache` with content-derived ETags (or equivalent conditional validation).
This permits storage but requires revalidation before reuse; do not send `immutable` or a
year-long freshness lifetime for `/js/*.mjs` or `/css/*.css`. Apply the policy to every imported
module, not just the entry script. Authenticated HTML and API responses must not enter shared
caches; serve them with `Cache-Control: private, no-store`.

For a production release pipeline, prefer content-hashed asset filenames and a release manifest.
Hash the complete module graph, rewrite import URLs to the corresponding hashed files, and have
the template select CSS and the entry module from the same manifest. Serve these immutable URLs
with `Cache-Control: public, max-age=31536000, immutable`. Publish assets first, atomically switch
the application/manifest together, and retain prior release assets so open tabs and rollbacks
continue to resolve their imports. This is a deployment strategy, not a new build tool or runtime
caching layer introduced by Slice 20.

Deployment verification must change asset bytes while preserving timestamps, then confirm the
new bytes reach a browser with a warm cache, including imported modules. For hashed releases,
verify that changed bytes yield new URLs, unchanged assets remain reusable, and rollback restores
a matching manifest and asset graph.

## Sessions and account links (Slice 19)

A session is valid only while it carries the account's current `credential_version` and was
created less than `Accounts.sessionLifetime` ago; otherwise `AppController::requireUserId()`
destroys it and answers `401`. Sign-in and password change regenerate the session id through
`AppController::startAuthenticatedSession()`; any password change advances the version, which
ends every other session. Tests sign in with `SignedInSessionTrait::signIn()` rather than writing
`Auth.user_id` alone.

Invitation and reset tokens are stored only as SHA-256 hashes in `account_tokens` and delivered in
the URL fragment of a link built from `App.fullBaseUrl`. Rate limits are counted in
`rate_limit_hits` under HMAC-hashed keys and configured in `RateLimit.*`; a full bucket answers
`429` with `Retry-After` and the error code `TOO_MANY_REQUESTS`. See
`docs/slices/19-account-lifecycle-and-session-security.md` for the full contract and decisions.

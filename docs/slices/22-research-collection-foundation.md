# Slice 22 — ResearchCollection foundation

## Slice Contract

### Status
IMPLEMENTED

### Goal
Introduce the ResearchCollection domain: a user-owned, capability-aware container for gathered
source material that later document-generation and agent work will read from.

### In scope
- Schema for ResearchCollections and their items, with user ownership and the existing capability
  resource vocabulary extended to cover them.
- CRUD API following the established response conventions (`data`/`meta`, `error.code`/`message`/
  `details`).
- Relating a ResearchCollection to an existing ToDo, Project or Notebook through the existing
  relationship mechanisms.
- Activity history entries for ResearchCollection mutations, recorded through the existing explicit
  post-mutation calls.

### Out of scope
- Any automated content retrieval, crawling, scraping, or outbound network request.
- Document generation (Slice 23), AI agents (Slice 24), Huginn orchestration (Slice 25).
- Full-text search over collected material.

### Dependencies
Slices 10 (capabilities), 12 (activity history). The capability vocabulary is a closed set enforced
by a database `CHECK`; extending it is a deliberate migration, not an incidental change.

### Required threat and authorization review
#### Access and sharing
- Creation assigns the authenticated creator as owner; ownership is immutable through these endpoints.
  Collections and items are private unless the owner explicitly grants access.
- The existing `CapabilityService` is authoritative. Owners hold all capabilities; non-owners receive
  only exact, active grants. `read`, `edit`, `delete`, `reference`, and `manage_permissions` never
  imply one another. Managing grants alone never grants access to collection contents.
- Grants can name only an existing user and are managed through `/api/capabilities`; there is no
  all-users or public-live-collection grant. Public access belongs to a future, separately versioned
  published-document feature. Publishing and document generation are not part of this slice.
- Collection listings include owned collections and collections with an explicit active `read` grant.
  Items are visible only with collection `read`. Sharing does not widen actor-only activity visibility.
- Every endpoint first requires a valid session (`401`). For authenticated requests, inaccessible and
  nonexistent collection/item ids both return `404`; malformed fields and pagination values return
  `400`, while entity validation failures return `422`. List endpoints omit inaccessible rows.
- Collection/item reads require `read`; collection updates and item create/update/link/unlink require
  `edit`; collection and item deletion require `delete`. Grant list/create/revoke require
  `manage_permissions`, enforced by the existing capability endpoints. Grants are re-evaluated for
  every request, so revocation is immediate.
- Linking a ToDo requires ownership of that ToDo. Linking a Project or Notebook requires the exact
  `reference` capability on that resource. The caller must also have `edit` on the collection.
  Links never mutate the referenced resource; inaccessible targets return `404`.

#### Deletion and recovery
- Deleting a collection cascades to its items. Deleting an item never deletes the collection or its
  referenced resource. Deleting a referenced ToDo, Project, or Notebook clears the matching item link
  and preserves the item and its content.
- The owner foreign key is `RESTRICT`: deleting an account that still owns collections is blocked
  rather than silently transferring or deleting user material. This repository has no admin identity,
  account-quarantine, or audited claim workflow yet; implementing those is a prerequisite to changing
  this policy. Capability grants are removed with a deleted subject, as in Slice 10.
- Activity remains actor-only and is not exposed through collection endpoints. API collection and item
  mutations record one explicit activity entry after the successful mutation. Referential unlinking
  caused by deleting a linked resource is database integrity cleanup, not a separately requested item
  mutation.

#### Content limits and handling
- An item has a required title (≤255 characters), plain-text content (≤65,536 UTF-8 bytes), and an
  optional source URI (≤2,048 characters). Empty content is permitted for source-reference-only items.
- Content is stored and returned as text, never interpreted as HTML or fetched by the server. No
  crawling, scraping, URL preview, or outbound request is performed. Any future renderer/generator
  must treat content as untrusted data, not executable markup or instructions.
- Collection descriptions are limited to 5,000 characters. Lists paginate with `page` (default 1) and
  `limit` (default 20, maximum 100).

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership enforcement, the full denied-path matrix for every endpoint, validation
  boundaries, pagination boundaries, cascade and restrict behaviour on delete, and activity
  recording.
- No client work is in scope, so no browser tests are expected.

### Risks
- Storing arbitrary user-supplied source material invites both oversized payloads and stored-content
  injection into later generated documents. Size limits and content handling must be decided here,
  not in Slice 23.

### Delivered API
- `GET`/`POST /api/research-collections`
- `GET`/`PATCH`/`DELETE /api/research-collections/{id}`
- `GET`/`POST /api/research-collections/{id}/items`
- `PATCH`/`DELETE /api/research-collections/{id}/items/{itemId}`

The existing capability API manages named-user grants using `resource_type: research_collection`.
No publish endpoint or client UI is included.

### Completion
- Migration `20261010220000_CreateResearchCollections` applies, rolls back to
  `20260916210000_CreateActivityRecords`, and reapplies successfully on PostgreSQL.
- ResearchCollection API and portability tests pass (37 tests, 435 assertions).
- PHP syntax checks, PHPCS, and PHPStan over all changed application classes pass.
- Full PHPUnit ran 701 tests: 700 passed; the unrelated `ApplicationTest::testBootstrapInDebug` fails
  because this checkout does not provide the optional DebugKit plugin. No new browser tests are
  required because this slice adds no client work.

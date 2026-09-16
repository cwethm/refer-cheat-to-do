# Slice Contract

## Slice Name
Slice 3 — Tags, Search, and Filtering

## Roadmap Reference
Phase 3 — Tags, Search, and Filtering

## Objective
Make captured ToDos classifiable and rediscoverable by adding user-owned tags and searchable/filterable ToDo listing.

## User-Visible Outcome
A user can create and manage tags, attach/detach tags on their ToDos, and reliably find ToDos by text, status, and tag filters.

## Included Scope
- tags table/entity/table class
- todos_tags join table and many-to-many association
- tag CRUD API (`POST/GET/PATCH/DELETE /api/tags`)
- attach/detach API (`POST/DELETE /api/todos/{id}/tags/{tagId}`)
- ToDo text search for `title` and `notes`
- ToDo filter by `status` and `tag`
- bounded pagination and deterministic sort
- authorization enforcement for tag ownership and tag attachment boundaries

## Excluded Scope
- tag clouds
- AI tag suggestions
- Project/Notebook context tagging
- semantic/vector search
- saved filters

## Dependencies
- Slice 2 ToDo model and ownership rules
- API response and error conventions from API base controller and exception renderer
- users/todos persistence schema from previous slice

## Existing Reusable Components
- `App\Controller\Api\AppController::respond()`
- `App\Controller\Api\AppController::requireUserId()`
- `App\Model\Table\TodosTable` validation/status constants
- existing pagination query validation pattern in `TodosController`

## Proposed New Reusable Components
- evaluate whether pagination/filter parsing should be extracted only if needed by multiple controllers in this slice

## Data Changes
- add `tags` table (`id`, `user_id`, `name`, `created`, `modified`)
- add `todos_tags` join table (`todo_id`, `tag_id`)
- enforce unique normalized tag name per user
- enforce unique (`todo_id`, `tag_id`) pair
- add supporting indexes for owner/tag-filter queries

## API Changes
- `POST /api/tags`
- `GET /api/tags`
- `PATCH /api/tags/{id}`
- `DELETE /api/tags/{id}`
- `POST /api/todos/{id}/tags/{tagId}`
- `DELETE /api/todos/{id}/tags/{tagId}`
- enhanced `GET /api/todos` with `q`, `tag`, and `status` filtering

## Authorization Rules
- users may only CRUD their own tags
- users may only attach their own tags to their own ToDos
- search/filter results include only owned ToDos
- anonymous access denied for tag and filtered-ToDo operations

## Failure Conditions
- blank tag name
- duplicate normalized tag name per user
- invalid tag/todo identifiers
- attach/detach against another user's resources
- duplicate join attachment
- invalid filter or pagination inputs

## Transaction Boundaries
- tag CRUD and attach/detach operations are single-aggregate writes; attach/detach should execute atomically per request.

## Test Plan
### Unit
- tag normalization/validation behavior
- filter parsing and validation behavior if extracted

### Integration
- create/update/delete tag
- enforce per-user uniqueness
- attach/detach tag relationships
- prevent duplicate relationship rows
- query behavior for text/tag/status combined filters

### API
- tag CRUD success/failure responses
- attach/detach success/failure responses
- filtered ToDo list returns deterministic paginated results

### Authorization
- cross-user tag access denied
- cross-user tag attachment denied
- cross-user ToDos excluded from filtered search results
- anonymous access denied

### Negative Cases
- malformed ids and filters rejected
- duplicate attachment rejected
- injection-like search strings handled safely
- invalid sort/pagination rejected

### Regression
- Slice 2 ToDo create/list/view/edit behavior unchanged without tags
- health endpoint and auth endpoints unchanged

### Performance
- verify index coverage for user/tag/status filters and join traversal

## Security Considerations
- parameter binding for text/tag filters
- owner checks applied before serialization
- escaped tag names in rendered output paths

## Rollback / Reversal
- rollback tag-related migrations in reverse order
- disable tag routes/controllers if slice rollback is required

## Documentation Changes
- `docs/slices/3-tags-search-and-filtering.md`
- completion report appended after slice implementation

## Completion Criteria
- users can CRUD tags, attach/detach them from owned ToDos, and find ToDos by text/tag/status
- authorization and negative-path tests pass
- full regression, coding standards, static analysis, and migration verification pass
- completion report written with final decision

---

# Slice Completion Report

## Delivered Behavior
- user-owned Tag CRUD (`POST/GET/PATCH/DELETE /api/tags`) with per-user normalized uniqueness
- Tag attach/detach on owned ToDos (`POST/DELETE /api/todos/{id}/tags/{tagId}`)
- ToDo search/filter (`q`, `status`, `tag`) with bounded pagination and deterministic ordering
- every ToDo API payload produced by the shared ToDo serializer now carries its attached Tags

## Schema / API Changes
- `tags` and `todos_tags` created in `20260911190000_CreateTagsAndTodosTags`
- `tags` uniqueness enforced by functional unique index on
  `(user_id, lower(regexp_replace(trim(name), '\s+', ' ', 'g')))`
- `20260916120000_AlignTodosTagsPrimaryKey` removes the surrogate `todos_tags.id` column and
  promotes `(todo_id, tag_id)` to the primary key so the ORM key definition and the database agree
- no API contract changes beyond correct Tag representation on create/update responses

## Corrections Applied In This Pass
1. **Inconsistent Tag loading** — Tag containment is now defined once in `TodosTable`
   (`TAG_CONTAIN`, `findWithTags()`, `loadTags()`), and `index`, `view`, `add`, and `edit`
   all serialize ToDos loaded through that single mechanism. Previously `POST` and `PATCH`
   returned an empty `tags` array.
2. **Tag attachment concurrency** — the `exists()` → `save()` check-then-insert was removed.
   `TodosTagsTable::attachIfMissing()` now attempts the insert with `checkExisting => false`
   and translates a unique-constraint violation (SQLSTATE 23505) into `409 Conflict`.
   While fixing this, a latent defect was found: because `todos_tags` had a surrogate `id`
   primary key while the table class declared a composite key, CakePHP silently converted
   duplicate inserts into no-op updates and returned `201`. The migration above fixes the
   schema so the database is the authority.
3. **Normalization semantics** — `TagsTable::normalizeWhitespace()` and
   `TagsTable::normalizeName()` are the only application normalization rules, used by both
   `beforeMarshal` and the uniqueness rule, and `TagsTable::NORMALIZED_NAME_SQL` documents the
   exactly equivalent database expression. Equivalence is proven by tests that exercise the
   application rule and the raw database index with the same inputs.
4. **Whitespace-only search** — documented policy: empty or whitespace-only `q` applies
   **no text filter** rather than degrading into `LIKE '%%'`. Covered by tests.
5. **Tag ownership mass assignment** — `user_id` remains non-accessible on `Tag` and is set from
   the authenticated session; regression tests submit a foreign `user_id` on create and update.

## Tests Added
- ToDo API: tags present in `POST`/`PATCH` responses, whitespace-only and empty `q`,
  wildcard escaping, combined filters, notes search, cross-user tag filter, invalid pagination,
  deterministic ordering, malformed id, malformed JSON, unknown tag, cross-user ToDo,
  tag deletion removing only the relationship
- `TagsTableTest`: normalization data provider (`"Research Queue"`, `" Research Queue "`,
  `"Research   Queue"`, `"Research     Queue"`, `"research queue"`) at both the application and
  database level, cross-user allowance, authoritative normalization helpers, protected `user_id`
- `TagsControllerTest`: ownership reassignment attempt, blank name, whitespace-variant duplicate,
  invalid pagination, cross-user delete
- `TodosTagsTableTest` (new): attach, duplicate attach, idempotency, database duplicate rejection,
  foreign-key rejection, tag-delete cascade limited to the join row, relationship survives updates

## Test Results
- `composer test` — 84 tests, all passing
- `composer cs-check` — passing
- `composer stan` — no errors
- `composer lint` — no syntax errors
- migrations: `migrate`, `rollback --target=0`, `migrate` verified on a clean database, plus
  `migrate` / `rollback` / `migrate` for the new migration on the test connection

## Authorization Rules Enforced
- all Tag and ToDo reads/writes scoped to the authenticated user
- cross-user Tags and ToDos return `404`, never leak content
- anonymous access returns `401`
- ownership is never accepted from client input

## Shared Components Added / Reused
- added: `TodosTable::findWithTags()` / `TodosTable::loadTags()` (single Tag-loading authority)
- added: `TagsTable::normalizeWhitespace()` / `normalizeName()` / `NORMALIZED_NAME_SQL`
- reused: `AppController::respond()`, `AppController::requireUserId()`, `ApiExceptionRenderer`

## Intentionally Local Logic
- pagination/filter parsing remains duplicated in `TagsController` and `TodosController`;
  only two occurrences exist and they validate different filters, so extraction is deferred
  until a third genuine consumer appears

## Known Limitations / Deferred Work
- tag uniqueness lookup remains a single indexed `SELECT` per write; the functional unique index
  makes the database authoritative, so no further optimization is warranted for MVP
- `lower()` in PostgreSQL and `mb_strtolower()` in PHP may differ for exotic Unicode casing;
  the database index is authoritative and would reject such a collision with `409`/`422`

## Security Review
- authentication and ownership enforced on every endpoint
- `user_id` protected from mass assignment on both `Tag` and `Todo`
- search input parameter-bound with `%`, `_`, and `\` escaped
- destructive operations limited to owned resources; tag deletion cascades only to join rows
- no secrets or internal details exposed in error payloads

## Code Bloat Review
- no new manager/helper/factory abstractions introduced
- two small shared methods added, each removing real duplication or an actual defect class

## Decision
Slice Decision: APPROVED

Reason:
All required tests and quality gates pass.
No blocking security, data-integrity, architecture, scope,
or regression issue remains.

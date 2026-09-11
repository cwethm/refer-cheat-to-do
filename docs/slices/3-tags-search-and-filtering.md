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

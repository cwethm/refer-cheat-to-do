# Slice 9 — Libraries

## Slice Contract

### Goal
Provide Libraries as a shared *context* grouping over Projects and Notebooks, without granting any
authority over the members that are grouped.

### In scope
- User-owned `Library` records with CRUD endpoints.
- Membership of Projects and Notebooks in a Library, added and removed explicitly.
- Owner-scoped member listing.
- Explicit verification of the Library non-authority invariant.

### Out of scope
- Sharing a Library with another user, capability/permission grants (Slice 10), cross-context
  requests (Slice 11), activity history (Slice 12).

### Library non-authority invariant
> Library membership provides shared context/membership. Library membership alone does **not**
> grant mutation, destructive, ownership, lifecycle, or permission authority over member Projects,
> Notebooks, or their contents.

Membership is therefore only ever a row in a join table. Every Project and Notebook operation
continues to be authorised solely by ownership, exactly as established in Slices 4 and 5.

### Schema changes
Migration `20260916180000_CreateLibraries`:
- `libraries` — `user_id` foreign key `ON DELETE RESTRICT`, `name`, `description`, timestamps,
  index on `user_id`.
- `libraries_projects` — composite primary key `(library_id, project_id)`, both foreign keys
  `ON DELETE CASCADE`, secondary index on `project_id`.
- `libraries_notebooks` — composite primary key `(library_id, notebook_id)`, both foreign keys
  `ON DELETE CASCADE`, secondary index on `notebook_id`.

The join rows cascade because a join row is meaningless without either side; the *parents* are
never cascaded, which is what keeps the non-authority invariant true at the database level.

### API changes
| Method | Path | Behaviour |
| --- | --- | --- |
| `GET` | `/api/libraries` | Owned libraries, paginated |
| `POST` | `/api/libraries` | Creates a library owned by the session user |
| `GET` | `/api/libraries/{id}` | Library with its owned members |
| `PATCH` | `/api/libraries/{id}` | Updates `name` / `description` only |
| `DELETE` | `/api/libraries/{id}` | Deletes an empty library (`409` while members remain) |
| `GET` | `/api/libraries/{id}/members` | Project and Notebook members |
| `POST` | `/api/libraries/{id}/members/{memberType}/{memberId}` | Adds a member (`201`) |
| `DELETE` | `/api/libraries/{id}/members/{memberType}/{memberId}` | Removes membership only |

`memberType` is `project` or `notebook`; anything else is `400`.

### Authorization rules
- Anonymous requests are `401`.
- Another user's Library is `404`, including for membership manipulation (id tampering).
- A member must be owned by the Library owner; a cross-user Project or Notebook is `404`.
- `user_id` is not mass-assignable on `Library`; ownership is set from the authenticated identity.

---

## Slice Completion Report

### Delivered behaviour
Library CRUD, membership add/remove/list, deterministic duplicate handling (`409`), member-blocked
deletion (`409`), and owner-scoped member serialization.

### Blocker diagnosed and fixed
The suite failed with `Cannot describe schema for table 'libraries_projects' — The table does not
exist`, while the focused service test passed in isolation. Diagnosis, in order:

1. The `test` datasource resolves to host `localhost`, port `5432`, database `refer_cheat_to_do_test`
   — the same connection used by `bin/cake migrations migrate --connection test` and by PHPUnit.
   (`config/app.php` builds the test datasource from `TEST_DB_*` with `DB_*` fallbacks; nothing in
   `phpunit.xml.dist` or `tests/bootstrap.php` overrides it.)
2. `bin/cake migrations status --connection test` lists `20260916180000 CreateLibraries` as `up`,
   and `information_schema` on that exact connection reports all three tables present.
3. Clearing the schema cache did not change the result, so stale metadata was not the cause.
4. The real cause: `Application::bootstrap()` registers a `TableLocator` with
   `allowFallbackClass(false)`. Once any integration test boots the application, the fixture's
   `_schemaFromReflection()` call raises `MissingTableClassException` for aliases without a concrete
   Table class — which CakePHP then reports with the misleading "table does not exist" message.
   `TodosTags` and `RelatedTodos` already had Table classes; the two new junctions did not.

Fix: added `LibrariesProjectsTable` / `LibrariesNotebooksTable` and their entities, following the
existing `TodosTagsTable` convention. No test, fixture strategy or production behaviour was weakened.

### Shared components
- **Added:** `App\Service\LibraryMembershipService` — the single authority for membership mechanics
  (same-owner assertion, race-safe insert relying on the composite primary key, `23505` → `409`,
  removal, member listing). Projects and Notebooks keep separate domain models; only the identical
  mechanics are shared via the `MEMBER_TYPES` map.
- **Added:** junction Table/Entity classes required by the application's no-fallback locator.
- **Reused:** the API envelope, owner-scoped lookup, `ValidationException` mapping, pagination
  reading, and the ownership conventions from Slices 4–5.

### Tests added
- `LibraryMembershipServiceTest` — 19 tests: add/remove/duplicate/absent for both member kinds,
  cross-owner rejection, unknown member, unknown type, multi-library membership, `hasMembers`,
  direct database duplicate and unknown-foreign-key constraint checks, and the cascade check
  proving deleting a Library removes join rows but not members.
- `LibrariesControllerTest` — 27 tests covering CRUD, pagination validation, anonymous `401`
  (including for membership writes), mass-assignment of `user_id` on create and update, cross-user
  Library `404` on view/edit/delete/members, cross-user Project and Notebook membership denial,
  cross-user Library id tampering, duplicate member `409`, invalid member type `400`, removal
  leaving the Project and Notebook intact, member-blocked delete `409`, direct Library deletion
  cascading only join rows, and explicit non-authority regressions showing membership grants no
  edit or delete authority and never transfers ownership.

### Test results
```
PHPUnit: OK (383 tests, 760 assertions)
PHPCS:   no errors
PHPStan: no errors
```

### Migration verification
`bin/cake migrations rollback --target 20260916170000` removed `libraries`, `libraries_projects`
and `libraries_notebooks`; `bin/cake migrations migrate` recreated them. Constraints verified
directly in PostgreSQL: `libraries.user_id` foreign key is `RESTRICT`; all four join foreign keys
are `CASCADE`; composite primary keys and secondary indexes present.

### Security review
Ownership is always taken from the session, never from the payload. Cross-user access is `404`
rather than `403`, matching earlier slices, so Library ids are not enumerable. Membership never
widens the authorisation surface of a Project or Notebook, and this is asserted by test rather than
assumed.

### Anti-bloat / reuse review
No new subsystem. One service, two junction Table/Entity pairs required by the framework
configuration, one controller, one migration. No permission model was anticipated here; that is
Slice 10.

### Known limitations
- Libraries are single-owner; there is no sharing or invitation flow in the MVP.
- Member listing is not paginated, which is acceptable at MVP volumes.

### Deferred work
- Capability-based sharing of a Library (Slice 10).
- Cross-context requests between Library members (Slice 11).

---

## Slice Decision: APPROVED

Reason:
All required tests and quality gates pass. Library membership and non-authority invariants are
verified. No blocking security, data-integrity, architecture, scope, or regression issue remains.

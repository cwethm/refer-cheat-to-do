# Slice 16 — Notebooks, NotebookSections and Libraries in the client

## Slice Contract

### Status
DELIVERED

### Goal
Complete the organizational surfaces in the `/app` client by adding Notebooks, NotebookSections and
Library browsing, reusing the section-assignment interaction established in Slice 15.

### In scope
- A Notebooks view listing the user's Notebooks with server-side pagination.
- Creating, renaming and deleting a Notebook and its NotebookSections.
- Assigning and clearing a ToDo's `notebook_section_id`, including moving a ToDo between a
  ProjectSection and a NotebookSection in a single explicit action.
- A read-oriented Libraries view for browsing Library contents.

### Out of scope
- New domain rules, schema changes, or new API endpoints.
- Library mutation flows beyond what the existing endpoints already support.
- Relationships, objectives, lifecycle screens and activity history (Slice 17).

### Dependencies
Slice 5 (Notebooks), Slice 9 (Libraries), and Slice 15 (section-assignment interaction). The
assignment control introduced in Slice 15 must be reused rather than duplicated.

### Design constraints
- The single-organizational-section invariant from ADR 0001 applies identically here; moving between
  a Project and a Notebook clears one assignment and sets the other in one request.
- Shared client behaviour extracted in this slice must be justified by at least two real call sites.

### Test plan
- Client unit tests for new URL builders and shared formatting helpers.
- Browser tests for: creating a Notebook and section; assigning a ToDo; moving a ToDo from a
  ProjectSection to a NotebookSection and verifying the old assignment is cleared; Library browsing;
  empty states; pagination boundaries; and API error surfacing.
- Accessibility audit of each new view plus addition to the responsive overflow loop.
- Standing client security checklist unchanged and passing.

### Risks
- Duplicating the Slice 15 assignment UI instead of reusing it. The review must check for this.
- Library browsing quietly becoming Library management. Keep it read-oriented unless re-contracted.

## Delivered behaviour
The client has two further views, **Notebooks** and **Libraries**. Notebooks lists the signed-in
person's Notebooks with server-side pagination at 20 per page, creates a Notebook from a name and
optional description, renames one in place, and deletes one the server accepts as empty. Opening a
Notebook shows its ordered sections with the ToDos filed in each, and sections can be added, renamed
and deleted through the existing endpoints. This is the same interaction as Projects, because it is
the same code.

The Slice 15 section picker was not duplicated. It was widened: each ToDo card now carries one
picker, labelled "Section for {title}", whose options span both Project sections and Notebook
sections. Choosing an option and pressing *Move* sends a single `PATCH /api/todos/{id}` carrying
both `project_section_id` and `notebook_section_id`, one set and the other `null`, so moving a ToDo
from a Project section to a Notebook section — or back — is one explicit action that satisfies the
single-section invariant rather than tripping its `409`. Choosing "No section" clears both.

Libraries is read-oriented. It lists the person's Libraries with pagination, and opening one shows
the Projects and Notebooks it groups. There is no create, rename, delete or membership editing: the
contract keeps Library mutation out of this slice.

No endpoint, schema, domain rule or authorization rule changed.

## Architecture
- The Projects view code was generalized rather than copied. A `RECORD_KINDS` map describes the two
  organizational kinds — their list path, detail key, page size, DOM ids and labels — and the single
  set of renderers and loaders (`renderRecord`, `renderSection`, `loadRecords`, `loadRecordDetail`)
  is driven by it. Record DOM nodes carry `data-record-kind` and `data-record-id`, so one delegated
  click handler serves both kinds. The extraction has two real call sites, as the contract requires.
- `buildRecordListUrl` holds the shared paging and clamping logic; `buildProjectsUrl`,
  `buildNotebooksUrl` and `buildLibrariesUrl` delegate to it.
- `sectionOptionValue` / `parseSectionOptionValue` encode a picker option as `"{kind}:{id}"`, and
  `buildSectionAssignmentBody` turns the chosen option into the two-field PATCH body. Keeping the
  body construction in one pure function is what makes the cross-kind move testable without a
  browser.
- `VIEW_LOADERS` replaces the hardcoded per-view branching in `refreshWorkspace` and `selectView`.
- Section options are gathered by listing Projects and Notebooks once each and reading each record's
  sections, so the picker covers the newest `SECTION_SOURCE_LIMIT` (100) of each kind.
- The module was not split; the client security suite still reads the whole `webroot/js/` directory.

## Accessibility and responsive review
- Both new views, their forms, per-record rename fields and the widened picker have programmatic
  names. The Project and Notebook section forms carry distinct labels so that the two forms are
  separately addressable.
- `tests/e2e/accessibility.spec.mjs` audits Notebooks and Libraries with axe at WCAG 2.1 A/AA and
  includes both in the 320/375/768/1280 overflow loop.
- The workspace navigation and the heading row now wrap at every width, so the fifth and sixth view
  buttons no longer push the page into horizontal overflow at 768 CSS pixels.

## Tests and verification
- `tests/js/app.test.mjs`: Notebooks and Libraries URL paging and clamping, the section option
  value encoding and parsing including malformed input, and the two-field assignment body for a
  Project section, a Notebook section and "No section".
- `tests/e2e/notebooks.spec.mjs`: the empty state; creating a Notebook and section, assigning a
  ToDo and seeing it from both sides, renaming and deleting; moving a ToDo from a Project section to
  a Notebook section in one action and confirming through the API that `project_section_id` is now
  `null`; Notebooks pagination boundaries; and a refused deletion of a non-empty Notebook.
- `tests/e2e/libraries.spec.mjs`: the empty state; browsing a Library down to its Projects and
  Notebooks; an empty Library; Libraries pagination; and an expired session returning to sign-in.
- `tests/e2e/projects.spec.mjs` was re-contracted: its browser-level `409` test became the mirror
  move test, because the client no longer produces that conflict. The `409` remains covered by
  `tests/TestCase/Controller/Api/TodosControllerTest.php`, which is where a server rule belongs.
- `PagesControllerTest` additionally asserts the Notebook, Notebook section and Library markup.
- Full gate: 634 PHPUnit tests / 1731 assertions, 25 client unit and security tests, 33 browser
  tests, PHPCS and PHPStan clean.

## Known limitations
- The picker covers the first 100 Projects and the first 100 Notebooks. No endpoint lists every
  section at once, and adding one is out of scope.
- Libraries cannot be created, renamed, deleted, or have members added or removed from the client.
  The endpoints exist; the screens do not.
- Sections still cannot be reordered, and there is no bulk or drag-and-drop assignment.
- Deleting a Notebook or section relies on the server's emptiness rules; the client surfaces the
  resulting error rather than offering to empty them first.

## Slice Completion Report

Slice 16 completes the organizational surfaces in the `/app` client. Notebooks and NotebookSections
are managed with the same code that manages Projects, Libraries can be browsed, and a ToDo moves
between a Project section and a Notebook section in one request. No endpoint, schema, domain rule or
authorization rule changed. The slice is approved after the focused and full regression suites pass.

Slice Decision: **APPROVED**

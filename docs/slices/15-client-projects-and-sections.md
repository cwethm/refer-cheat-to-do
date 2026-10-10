# Slice 15 — Projects and ProjectSections in the client

## Slice Contract

### Status
DELIVERED

### Goal
Let a person create, browse and organize Projects and ProjectSections in the `/app` client, and
assign a ToDo to a ProjectSection, so the review queue's "Not in a Project or Notebook section"
reason becomes actionable instead of merely explanatory.

### In scope
- A Projects view listing the signed-in user's Projects with server-side pagination.
- Creating, renaming and deleting a Project and its ProjectSections through the existing endpoints.
- Viewing a Project's sections and the ToDos assigned to each.
- Assigning and clearing a ToDo's `project_section_id` from the ToDo card and from the review queue.
- Surfacing the API's `409 Conflict` when an assignment would violate the single-section invariant.

### Out of scope
- New domain rules, schema changes, or new API endpoints.
- Any change to API authorization or validation.
- Notebooks, NotebookSections and Libraries (Slice 16).
- Drag-and-drop reordering or bulk assignment.

### Dependencies
Slice 4 (Projects and ProjectSections API) and Slice 14 (client shell). No server work is expected;
if a gap is found, record it and stop rather than widening the slice.

### Design constraints
- `docs/adr/0001-single-organizational-section-per-todo.md` is authoritative: a ToDo has at most one
  organizational section. The client must present assignment as a *move*, not as an additive choice,
  and must rely on the server for enforcement.
- The client may hide a control the user cannot use, but hiding is never the enforcement mechanism.

### Test plan
Per `docs/front-end-testing-policy.md`:
- Client unit tests for any new URL builders and for section-label formatting.
- Browser tests for: creating a Project and a section; assigning a ToDo to a section; clearing the
  assignment; the single-section conflict message; the empty state when a user has no Projects;
  pagination boundaries on the Projects list; and a `401` during assignment returning to sign-in.
- Accessibility audit of the new view and its addition to the responsive overflow loop.
- The standing client security checklist must keep passing unchanged.

### Risks
- Scope leakage into Notebooks because both share the section concept. The contract forbids it.
- Growing `webroot/js/app.mjs` past the point where a single module is readable. If it must be split,
  the split is structural only; the security suite reads the whole directory and must still pass.

## Delivered behaviour
The client has a fourth view, **Projects**. It lists the signed-in person's Projects with
server-side pagination at 20 per page, creates a Project from a name and optional description,
renames a Project in place, and deletes one the server accepts as empty. Opening a Project shows its
ordered sections with the ToDos filed in each, and sections can be added, renamed and deleted
through the existing endpoints.

Every ToDo card — in the ToDos list and in the review queue — carries a single "Project section"
picker preselected to the ToDo's current section. Choosing another section and pressing *Move* sends
one `PATCH /api/todos/{id}` with `project_section_id`; choosing "No section" sends `null`. The
picker is a move, not an additive choice, which is what
`docs/adr/0001-single-organizational-section-per-todo.md` requires. A ToDo already filed in a
Notebook section is labelled as such, and the server's `409 Conflict` message is shown verbatim
rather than being pre-empted in the client. An assigned ToDo shows where it lives, so the review
queue's "Not in a Project or Notebook section" reason is now actionable.

No endpoint, schema, domain rule or authorization rule changed. The server remains authoritative for
ownership, validation and the single-section invariant.

## Architecture
- `webroot/js/app.mjs` gains `buildProjectsUrl`, `formatSectionLabel`, the Projects view loaders and
  renderers, and the section-assignment action. The module was not split; the client security suite
  still reads the whole `webroot/js/` directory.
- Section options for the picker are built by listing Projects once and reading each Project's
  sections, so the picker covers the newest `SECTION_SOURCE_LIMIT` (100) Projects.
- List loaders carry a monotonic request token, so a slow response can no longer repaint a list that
  a newer request has already replaced and discard a half-made selection.

## Accessibility and responsive review
- The new view, its forms, its per-record rename fields and the section picker all have programmatic
  names; `tests/e2e/accessibility.spec.mjs` audits the Projects view with axe at WCAG 2.1 A/AA and
  includes it in the 320/375/768/1280 overflow loop.
- The workspace navigation now wraps on narrow screens so the fourth view button does not push the
  page into horizontal overflow at 320 CSS pixels.

## Tests and verification
- `tests/js/app.test.mjs`: Projects URL paging and clamping, limit clamping to what the API accepts,
  and Project-qualified section labels including their fallbacks.
- `tests/e2e/projects.spec.mjs`: the Projects empty state; creating a Project and a section;
  assigning a ToDo and seeing it from both sides; renaming a section and a Project; clearing an
  assignment; deleting an empty section and an empty Project; the `409` single-section conflict and
  that the ToDo is left where it was; Projects pagination boundaries; and a `401` during assignment
  returning to sign-in. The spec removes everything it creates.
- `PagesControllerTest` additionally asserts the Project and section forms and the Projects list.
- Full gate: 630 PHPUnit tests / 1705 assertions, 21 client unit and security tests, 21 browser
  tests (smoke, boundaries, Projects, accessibility and responsive), PHP lint and PHPCS clean.

## Known limitations
- The section picker covers the first 100 Projects; a person with more must open the Project view to
  see the rest. No endpoint exists to list every section at once, and adding one is out of scope.
- Notebooks, NotebookSections and Libraries still have no screens. A ToDo filed in a Notebook section
  can be seen but not moved from the client. Slice 16 closes this.
- Sections cannot be reordered from the client, and there is no bulk or drag-and-drop assignment.
- Deleting a Project or section relies on the server's emptiness rules; the client surfaces the
  resulting `409` rather than offering to empty them first.

## Slice Completion Report

Slice 15 brings Projects and ProjectSections into the `/app` client and makes the review queue's
missing-section reason actionable, without adding endpoints, schema, domain rules or authorization
rules. The slice is approved after the focused and full regression suites pass.

Slice Decision: **APPROVED**

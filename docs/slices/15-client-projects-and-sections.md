# Slice 15 — Projects and ProjectSections in the client

## Slice Contract

### Status
PROPOSED

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

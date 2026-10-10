# Slice 16 — Notebooks, NotebookSections and Libraries in the client

## Slice Contract

### Status
PROPOSED

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

# ADR 0001 — A ToDo Belongs to at Most One Organizational Section

## Status
Accepted (Slice 5 — Notebooks and NotebookSections)

## Context
Slice 4 introduced `ProjectSection` assignment for ToDos and Slice 5 introduces
`NotebookSection` assignment. `docs/mvp-development-slices.md` explicitly requires a documented
decision on whether a ToDo may simultaneously belong to both a ProjectSection and a
NotebookSection, and requires an ADR when the repository documents do not already resolve it.

No existing design document, roadmap entry, or ADR resolves this question, so it is decided here.

Projects are goal-oriented and finite; Notebooks are continuing knowledge/reference contexts.
Both answer the same question for a ToDo: "where does this piece of work live?".

## Decision
A ToDo has **at most one organizational section**: either a `project_section_id` or a
`notebook_section_id`, never both. A ToDo with neither remains a valid Inbox item.

Enforcement is layered:

1. the API rejects a request that would leave both assignments set, with `409 Conflict`;
2. a database `CHECK` constraint (`todos_single_section_chk`) makes the invariant
   independently authoritative.

Moving a ToDo between contexts is explicit: a single `PATCH /api/todos/{id}` may clear one
assignment and set the other in the same request.

## Consequences
- Lists, dashboards, and later review/resurfacing logic have one unambiguous organizational home
  per ToDo, so no precedence rule between Projects and Notebooks is needed.
- Cross-cutting classification remains available through Tags, which are intentionally many-to-many.
- If a future phase genuinely needs multi-context membership, it will require a new ADR, a
  migration dropping the check constraint, and an explicit precedence rule for context-scoped views.

## Alternatives Considered
- **Allow both simultaneously.** Rejected: it creates ambiguity about which context owns the ToDo
  and would force an arbitrary precedence rule in every context-scoped view.
- **Silently clear the other assignment.** Rejected: silent data changes during an update are
  surprising and can lose organizational intent without user confirmation.
- **One generic "container/section" table for both domains.** Rejected: the slice protocol
  explicitly warns against collapsing Projects and Notebooks into one generic container while
  their domain meanings remain distinct.

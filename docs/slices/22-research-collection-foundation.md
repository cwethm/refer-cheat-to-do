# Slice 22 — ResearchCollection foundation

## Slice Contract

### Status
PROPOSED — first slice of the post-MVP domain program

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
Before implementation the slice must state: who may read, write and administer a ResearchCollection;
what the default-deny outcome is for each endpoint; and what happens to collection items when the
owning user or a related resource is deleted.

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

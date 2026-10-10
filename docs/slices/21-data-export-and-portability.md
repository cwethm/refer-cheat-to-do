# Slice 21 — Data export and account data portability

## Slice Contract

### Status
PROPOSED

### Goal
Let a user take their data with them. Nothing in the roadmap currently provides export, and daily
real-world use should not begin without it.

### In scope
- A complete, machine-readable export of the signed-in user's own data: ToDos, tags, Projects and
  sections, Notebooks and sections, Libraries, relationships, objectives and results, review
  schedules, and the user's own activity history.
- A documented, versioned export format with a stable schema identifier.
- A client action that requests the export and a documented operator command for the same job.
- A documented restore procedure, even if restore is initially operator-driven.

### Out of scope
- Importing data from other products.
- Exporting another user's data, or data visible only through a capability grant.
- Scheduled or automatic backups of the database itself, which is a deployment concern.

### Dependencies
All domain slices (1–13). Slice 18 if capability-shared resources are to be considered, which this
contract deliberately excludes.

### Design constraints
- The export is scoped to rows the user owns. Authorization is evaluated by the existing services;
  export must not become a second, weaker read path.
- Password hashes, tokens, and other credential material are never included.
- Generation must be bounded: streaming or chunked output, never an unbounded in-memory array.

### Test plan
- PHPUnit: the export contains every owned record type; it contains no other user's rows even when a
  capability grant exists; it contains no credential fields; an unauthenticated request is denied;
  the format matches its documented schema version.
- A memory/bounds test asserting the export does not load the entire dataset at once.
- Browser test for requesting an export and receiving a downloadable result.
- A round-trip test for the documented restore procedure.

### Risks
- Export is the single highest-value target in the system; an authorization mistake here discloses
  everything a user has. The denied paths matter more than the happy path.

# Slice 30 — Issues: topic hubs for social issues

## Slice Contract

### Status
PROPOSED — fifth slice of Phase F (`docs/intended-use.md`)

### Goal
Give each social issue the owner is engaged with a named place that gathers everything known about
it — cards, records, ToDos, Projects, Notebooks and research — so the material is organised around
the issue and not scattered by type. Once needs (Slice 31) join, the hub is also where the owner
finds the detailed issues and factors to weigh when forming a recommendation.

### In scope
- A user-owned hub for an issue: `name` (≤255 characters) and a short plain-text `description`
  (≤5,000 characters) used for "where things stand".
- Adding and removing members of the hub explicitly, for the item types that exist at this point:
  reference cards, records, ToDos, Projects, Notebooks and ResearchCollections.
- A hub detail view grouping members by type, with each group paginated, the most recent records
  first, and counts per type.
- An **Issues** client view: list, create, open, edit and delete (membership only is removed on
  deletion).
- Account export coverage and activity entries for hub and membership changes.

### Out of scope
- Issue status, severity, positions or stances, deadlines, or any workflow state.
- Sharing a hub, or collaborating on one. Community needs (Slice 31) and people (Slice 33) join a hub
  when those slices land; decisions are records and are already eligible members.
- A hub is a standing home for a subject. A purpose-bound or temporary selection of items is a
  bundle (Slice 34).
- Automatic membership, such as pulling in everything carrying a tag.
- Authority over members: a hub grants nothing over what it groups.

### Dependencies
Slices 26 and 27 (cards and records), 9 (Libraries and the non-authority invariant), 21 (export),
12 (activity). Slice 28 links are unaffected: membership groups items under an issue, a link relates
two specific items.

### Required decision (ADR)
Whether an issue is a **Library with a wider membership vocabulary** or a **new Issue entity**.

The leaning is to reuse Libraries. A Library is already a user-owned context grouping with an
explicit, tested non-authority invariant (Slice 9) and `libraries_*` membership join tables with
composite primary keys, `CASCADE` on the join row and no cascade to the parents. Extending the member
vocabulary to cards, records and ToDos is a deliberate migration and avoids a parallel grouping
concept. The ADR must settle the cost of that choice: ToDos in a Library would be the first member
type that is not a Project or Notebook, the `capability_grants` `library` resource type and existing
Library capability checks must be shown unaffected, and the client must label hubs as Issues without
creating two names for one thing.

A separate Issue entity is justified only if a field specific to issues is needed that a description
cannot carry. If chosen, the ADR must say why Libraries could not serve.

### Required threat and authorization review
- Hubs are owner-only; members must be owned by the hub's owner. Another user's hub, a nonexistent
  hub or member, and a cross-user member all return `404`.
- Membership is a join row and nothing more. It must grant no mutation, destructive, lifecycle,
  ownership or permission authority over a member (the Slice 9 invariant), and a hub listing must not
  disclose members the requester could not otherwise read.
- Deleting a hub removes only its membership rows. Deleting a member removes only its membership row.
- Member lists are bounded and paginated per type; no unbounded nested load.
- The export includes hubs and memberships for the user's own rows.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership and the full denied-path matrix; the non-authority invariant for each member
  type, including a ToDo; membership uniqueness; cascade behaviour on deleting a hub and on deleting
  each member type; pagination per type; activity entries; export.
- A regression test that existing Library endpoints and Library capability checks behave exactly as
  before for Projects and Notebooks.
- Browser tests for creating an issue, adding and removing members of several types, and viewing the
  grouped hub; accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- Overloading Libraries could weaken the concept the capability model was built around. The ADR and
  the regression test above exist to keep that visible.
- A hub that can contain everything becomes a second, unstructured inbox. Membership stays explicit.

# Slice 28 — Cross-links and backlinks

## Slice Contract

### Status
PROPOSED — third slice of Phase F (`docs/intended-use.md`)

### Goal
Let the owner connect any knowledge item to any other and see what points back at it, so details from
one discussion are found where they are needed rather than only where they were first recorded.

### In scope
- A user-owned link between two items, each of a type from a closed set: `todo`, `project`,
  `notebook`, `research_collection`, `reference_card`, `record`. Later slices that add an item type
  (`need`, Slice 31; `person`, Slice 33) extend the vocabulary only through a deliberate migration.
  Issue membership (Slice 30) and bundle membership (Slice 34) group items and are separate
  mechanisms; a link relates two specific items.
- Links are the mechanism for "connected to" everywhere in Phase F: a pattern to the concepts it
  uses, a record to the cards it produced, a decision to what informed it (Slice 32), a person to
  what they relate to (Slice 33). A link may join two items of the same type.
- Optional short `label` on a link (≤100 characters) describing the relationship.
- Create, list and remove links, and a backlinks listing for any item: outgoing and incoming links
  together, bounded and paginated.
- A **Linked items** panel on each existing detail view (card, record, ToDo, Project, Notebook,
  ResearchCollection): add a link by searching for the target, remove a link, follow a link.
- Account export coverage and activity entries for link creation and removal.

### Out of scope
- Replacing or migrating existing links: ToDo related/parent links (Slice 8), Library membership
  (Slice 9), ResearchCollection item links (Slice 22) and cross-context requests (Slice 11) are
  unchanged.
- Links to or from another user's items, or items reachable only through a capability grant.
- Automatic link suggestion, text-mention parsing, graph visualisation or traversal beyond one hop.
- Any change to authorization on the linked items.

### Dependencies
Slices 26 and 27 (the new item types), 22 (the precedent for typed links), 21 (export), 12
(activity).

### Required decision (ADR)
How a link refers to its two ends. Slice 22 used a typed `(type, id)` pair, a database `CHECK` on the
type vocabulary, no resource foreign key and `BEFORE DELETE` triggers that clear the link when the
target is deleted; ADR 0002 and Slice 11 chose constrained polymorphism over a table per pair. The
alternatives are a link table per type pair (rejected: it grows quadratically with item types) and
leaving dangling links to be filtered at read time.

The leaning is a single `links` table in the Slice 22 style, with `BEFORE DELETE` triggers that delete
links in either direction when an item is deleted, so a dangling link cannot exist and a deleted
card never leaves a ghost reference in an export. The ADR must also fix link direction semantics
(a link is directed; the backlinks view shows both) and the uniqueness rule for a pair and label.

### Required threat and authorization review
- A link may be created only between two items owned by the requesting user. A target the user does
  not own, or that does not exist, returns `404`, revealing nothing about its existence. The owner of
  the link row is the creator and is immutable.
- A link grants nothing: reading, editing or deleting an item is authorised only as before, never
  because a link exists. A link must not be a way to read an item's title, summary or text.
- Self-links and duplicate links are rejected by the database as well as the service.
- Backlink and link listings are bounded by `page` and `limit` (maximum 100), never recursive.
- Link labels are plain text and are never logged.
- The export includes links, restricted to the user's own rows.

### Test plan
- Migration apply, rollback and reapply, including the deletion triggers for every linkable table.
- PHPUnit: the full denied-path matrix; cross-user, nonexistent and wrong-type targets; self and
  duplicate links; label boundaries; deleting each item type removes its links in both directions and
  nothing else; pagination boundaries; activity entries; export contains only the user's links.
- A regression test that a link grants no read, edit or delete authority and exposes no content of
  the linked item.
- Browser tests for adding, following and removing a link from at least a card, a record and a ToDo;
  accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- A generic link is the easiest way to build an accidental second read path. The contract forbids
  returning linked content; the link listing returns only type, id and label, and the client fetches
  titles through each item's own authorised endpoint.
- Every new linkable item type needs a trigger and a vocabulary entry. That cost is intended: it keeps
  the set closed and reviewed.

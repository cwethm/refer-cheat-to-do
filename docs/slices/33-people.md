# Slice 33 — People

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). Which details a person record holds, and whether groups
or organisations are included, are open questions for the owner.

### Goal
Give the owner a private record of a person who matters to their work: why they matter and what is
known about them, connected to every record, event, need, decision, card and ToDo they relate to or
are referenced by, so that all of it can be found from the person and the person from all of it.

### In scope
- `Person`: a user-owned record with a required `display_name` (≤255 characters; an alias, initials
  or a role is encouraged), an optional `relevance` (≤2,000 characters: why this person matters to
  the work, the line read at a glance) and optional plain-text `details` (≤65,536 UTF-8 bytes).
- Tagging with the user's existing tags.
- Connections through Slice 28 links: `person` is added to the link vocabulary, so a person links to
  records, needs, decisions, cards, ToDos, Projects, issues and other people. A person-to-person link
  with a free-text label expresses a connection ("works with", "introduced by").
- Issue hub membership through Slice 30: `person` is added to the member vocabulary.
- A **Connected to** panel on the person's detail view, built on the Slice 28 backlinks listing:
  everything the person links to and everything that links to them, grouped by type, bounded and
  paginated. Linked people also appear on the detail views of the items that link to them.
- A **People** client view: search by name, relevance and details; list; detail; a create form that
  needs only a name; edit; delete behind confirmation, stating what deletion does and does not remove.
- Account export coverage and activity entries for person mutations (extending the closed
  `activity_records_subject_type_chk` set by migration).

### Out of scope
- Contact details (email, telephone, address, social handles) unless the owner asks for them, which
  would be a separate decision with its own threat review.
- Linking a person to a user account, any capability grant, sharing, notifications, or any
  collaboration on a person record.
- Detecting, extracting or suggesting people from other text. A record's free-text `participants` is
  never parsed into person records, and deleting a person does not edit text elsewhere that
  mentions them.
- Importing contacts, enrichment from outside sources, and any outbound request.
- Groups and organisations, until the owner decides whether they are wanted.
- Fields for sensitive categories of information about a person (health, status, affiliation, location).
  They are deliberately not modelled; see the guidance in `docs/intended-use.md`.
- Agent access (Slice 24 excludes people unless explicitly granted).

### Dependencies
Slices 27 (records), 28 (links and backlinks), 30 (issues), 21 (export), 12 (activity), 17
(confirmation pattern).

### Required decision (ADR)
Whether `details` needs application-level encryption in addition to the operator's disk encryption.
The leaning is no for the first version: it would prevent search over the field, complicate backup
and restore, and introduce key management and key loss as new failure modes, for protection that
does not cover the largest realistic threats (a compromised session or application). The ADR must
state the reasoning, and the condition under which it would be revisited, such as the owner
choosing to record higher-risk detail than the guidance in `docs/intended-use.md` recommends.

### Required threat and authorization review
- A person record, and above all the set of connections between people and the work, is the most
  sensitive object in the system. It is owner-only; ownership is set from the session and is
  immutable. Another user's person and a nonexistent one both return `404`.
- Links grant nothing (Slice 28): a link to a person reveals nothing about that person to anyone
  but the owner, and no listing of another item returns a person's `relevance` or `details`.
- Text is plain, returned as text and inserted with `textContent`. Names, relevance, details and
  search text are never logged.
- Deleting a person removes their links (in both directions, by the Slice 28 triggers) and hub
  memberships and nothing else. Free text elsewhere that names them is untouched, and the client
  says so before the deletion is confirmed.
- People are a sensitive type for bundles and offline copies (Slices 34 and 35): excluded unless the
  owner explicitly allows it for that bundle. They are never in an agent's reach by default.
- The export includes people and their links, for the user's own rows only. A denied-path test must
  show that no other user's person or connection appears.

### Test plan
- Migration apply, rollback and reapply, including the link triggers for the new type.
- PHPUnit: ownership and the full denied-path matrix; length boundaries; person-to-person links and
  self-link rejection; deleting a person removes only their links and memberships; search over name,
  relevance and details with wildcard escaping; pagination boundaries; activity entries; export
  contains only the user's people and connections.
- A regression test that no listing of another item (record, need, decision, card) returns a linked
  person's `relevance` or `details`.
- A regression test that creating people changes no ToDo list, review queue or dashboard count.
- Browser tests for creating a person with a name only, linking them to a record and to another
  person, viewing Connected to, and deleting behind a confirmation that states its limits;
  accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- The social graph is sensitive in aggregate even where each node is not. The export, bundles and
  offline copies are the places it could leave the server, and each has its own exclusion rule.
- Guidance cannot stop the owner recording something that should not be recorded. The absence of
  fields for sensitive categories, the alias-first interface and real deletion reduce the harm; they
  do not remove it.
- A person record invites growth into a general contact-management feature. Contact details,
  groups and import need their own contracts.

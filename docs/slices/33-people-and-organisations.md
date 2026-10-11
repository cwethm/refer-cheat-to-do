# Slice 33 — People and organisations

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). Confirmed by the owner: a record for people, groups and
organisations; defined relationships between them; and, for each, what they can contribute and in
what capacity. Still to confirm: that "assets" means resources a party could put toward the work and
not their finances (see Out of scope), and the starter list of relationship types.

### Goal
Give the owner a private record of the people, groups and organisations that matter to their work:
why they matter and what is known about them, how they are connected to one another by *defined*
relationships, and what each could contribute and in what capacity (as part of a team, as a
volunteer, or as a paid consultant or contractor). Each is connected to the records, needs,
decisions, cards and ToDos it relates to or is referenced by, so all of it can be found from the party
and the party from all of it.

### In scope
- `Party`: a user-owned record with a required `kind` (`person`, `group`, `organisation`, enforced by a
  database `CHECK`), a required `display_name` (≤255 characters; an alias, initials or a role is
  encouraged for people), an optional `relevance` (≤2,000 characters: why this party matters to the
  work, the line read at a glance) and optional plain-text `details` (≤65,536 UTF-8 bytes). A *group*
  is an informal collective; an *organisation* is a formal body. The client calls the type **People &
  groups**; `party` is the type name in the API and in link and membership vocabularies.
- Tagging with the user's existing tags, and issue hub membership through Slice 30 (`party` is added
  to the member vocabulary).
- **Relationship types**: a user-managed list of named, defined relationships, so that every
  connection between two parties says what it is. A type has a `name` (≤100 characters, unique per
  user after the tag normalisation: trim, collapse whitespace, lowercase) and an optional
  `inverse_name` (≤100). A type without an inverse is symmetric ("works with"); a type with one is
  directed ("employs" / "works for"). A small illustrative starter set is offered and the owner can
  add, rename and remove types. A type that is in use cannot be removed (`409`).
- **Relationships**: a connection between two of the owner's parties through exactly one relationship
  type, with an optional `note` (≤500 characters). Both ends are always parties, so a relationship
  uses ordinary foreign keys with `ON DELETE CASCADE`, not the typed `(type, id)` pair of Slice 28.
  Self-relationships and duplicates (the same two parties and type, in either order for a symmetric
  type) are rejected. Person-to-person, person-to-organisation, person-to-group and
  organisation-to-organisation are all allowed. Slice 28 rejects a generic link whose two ends are
  both parties, so there is exactly one way to relate two parties and it is a defined one.
- **Contributions** (the owner's words: assets, strengths and abilities), recorded per party as rows
  with a `kind`, a `label` and the engagements they are offered for:
  - `kind`: `strength` (a quality the party brings, for example facilitation or steadiness in
    conflict), `ability` (a skill or competence they can apply, including a qualification, for example
    first aid or translation) or `asset` (a resource they could put toward the work, for example a
    vehicle, a venue, equipment or a network they can reach);
  - `label` (required, ≤255 characters) and optional `notes` (≤2,000 characters);
  - `engagements`: a non-empty set drawn from `team` (as part of a team the owner works in),
    `volunteer`, and `paid` (as a consultant or contractor), stored as three boolean columns with a
    `CHECK` that at least one is true;
  - a documented cap on contributions per party. They apply to every `kind`, so an organisation can
    list a venue as an asset.
- A **Connected to** panel on each party's detail view with two parts: its *relationships* (grouped by
  type, worded from the correct end for directed types), and its Slice 28 *links* (everything the
  party links to and everything that links to it, grouped by item type). Both are bounded and
  paginated. Linked parties also appear on the detail views of the items that link to them.
- A **People & groups** client view:
  - search by name, relevance, details and contribution labels;
  - filter by kind, tag, contribution kind and engagement, so the owner can ask "who has a vehicle and
    would volunteer" or "who could be contracted for translation". This is a filter the owner
    applies, not a match the system suggests;
  - list, detail, a create form that needs only a name and a kind, edit, managing contributions,
    managing relationship types, and delete behind confirmation that states what deletion does and
    does not remove.
- Account export coverage for all four tables, and activity entries for party, relationship and
  contribution mutations (extending the closed `activity_records_subject_type_chk` set by migration).

### Out of scope
- Contact details (email, telephone, address, social handles) unless the owner asks for them, which
  would be a separate decision with its own threat review.
- **Finances.** Pay rates, fees, invoices, contracts, wealth or money held. `asset` means a resource
  that could be applied to the work, not a party's financial position. "Paid" records only the
  capacity in which something would be offered.
- Availability scheduling, hours, calendars and any booking.
- Matching, scoring or suggesting a party for a need or task. The owner searches and filters.
- Linking a party to a user account, any capability grant, sharing, notifications or collaboration on a
  party. Sharing is limited to published cheat sheets (Slice 36) and does not involve this record.
- Detecting, extracting or suggesting parties from other text. A record's free-text `participants` is
  never parsed into party records, and deleting a party does not edit text elsewhere that mentions it.
- Importing contacts, enrichment from outside sources, and any outbound request.
- Dates on a relationship (started, ended) and relationship history; a relationship is removed or
  kept. Membership rosters beyond relationships ("who is in this organisation" is the relationships
  pointing at it).
- Fields for sensitive categories of information about a person (health, immigration or legal status,
  location, beliefs or affiliations). They are deliberately not modelled; see the guidance in
  `docs/intended-use.md`.
- Agent access (Slice 24 excludes parties unless explicitly granted).

### Dependencies
Slices 27 (records), 28 (links and backlinks), 30 (issues), 21 (export), 12 (activity), 17
(confirmation pattern).

### Required decision (ADR)
1. **One table or several.** The leaning is one `party` table with a `kind`, because relationships,
   contributions, links, tags and hub membership are identical for a person, a group and an
   organisation, and the commonest relationship (a person and an organisation) crosses kinds. The ADR
   fixes the type name that goes into the vocabularies of Slices 28, 30 and 34.
2. **Relationship vocabulary.** The leaning is an owner-managed list with a fixed shape (name and
   optional inverse), because the owner asked for *defined* relationships and the useful ones are
   specific to their community. The alternative, a closed list fixed by migration, would be safer to
   test and less useful. The ADR must also decide how a symmetric duplicate is rejected at the
   database level.
3. **Encrypting `details`.** Whether `details` and contribution notes need application-level
   encryption in addition to the operator's disk encryption. The leaning is no for the first version:
   it would prevent search over the field, complicate backup and restore, and introduce key
   management and key loss as new failure modes, for protection that does not cover the largest
   realistic threats (a compromised session or application). The ADR states the reasoning and the
   condition under which it would be revisited.
4. **Representation of `engagements`.** Three boolean columns with a `CHECK` (the leaning, because it
   avoids array types and is easy to filter on) against a join table.

### Required threat and authorization review
- A party record, and above all the set of relationships and contributions around it, is the most
  sensitive object in the system. It is owner-only; ownership is set from the session and is
  immutable. Another user's party, relationship type, relationship or contribution, and a nonexistent
  one, all return `404`. A relationship whose two parties do not both belong to the requester is
  rejected the same way.
- *Who works or volunteers with whom* can reveal an affiliation even when each record is harmless, and
  contributions are statements about third parties. The controls are guidance, owner-only storage,
  real deletion, and exclusion from every outward path in this phase.
- Links and relationships grant nothing (Slice 28 and Slice 9 non-authority): nothing about a party is
  visible to anyone but the owner, and no listing of another item returns a party's `relevance`,
  `details` or contributions.
- Text is plain, returned as text and inserted with `textContent`. Names, relevance, details,
  contribution labels and search text are never logged.
- Deleting a party removes its relationships and contributions (cascade), its links in both
  directions (the Slice 28 triggers) and its hub memberships, and nothing else. Free text elsewhere
  that names the party is untouched, and the client says so before the deletion is confirmed.
- Parties are a sensitive type for bundles and offline copies (Slices 34 and 35): excluded unless the
  owner explicitly allows it for that bundle. They are never in an agent's reach by default, and they
  are never part of a published cheat sheet (Slice 36).
- The export includes parties, relationship types, relationships and contributions, for the user's own
  rows only. A denied-path test must show that no other user's party or connection appears.
- Guidance for contributions: record what a party offers or has said they offer, in terms of what they
  can do. Do not record impairments, conditions, limitations or anything about health.

### Test plan
- Migration apply, rollback and reapply, including the link triggers for the new type and the
  cascades.
- PHPUnit:
  - ownership and the full denied-path matrix for parties, relationship types, relationships and
    contributions;
  - `kind` and length boundaries; `engagements` with none, each one and all three;
  - relationship type uniqueness after normalisation, deleting a type that is in use (`409`), and
    renaming a type;
  - relationships: self-relationship and cross-user party rejection, duplicate rejection in both
    orders for a symmetric type and in one order only for a directed type, and correct wording from
    each end;
  - Slice 28 rejects a link whose two ends are both parties;
  - deleting a party removes only its relationships, contributions, links and memberships;
  - filtering by kind, tag, contribution kind and engagement; search with wildcard escaping;
    pagination boundaries; query count independent of the number of relationships; the contribution
    cap at its boundary;
  - activity entries; export contains only the user's rows.
- A regression test that no listing of another item (record, need, decision, card) returns a linked
  party's `relevance`, `details` or contributions.
- A regression test that creating parties changes no ToDo list, review queue or dashboard count.
- Browser tests for creating a party with a name and kind only, defining a relationship type,
  relating a person to an organisation and to another person, adding contributions with engagements,
  filtering on them, viewing Connected to, and deleting behind a confirmation that states its limits;
  accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- The social graph is sensitive in aggregate even where each node is not. The export, bundles and
  offline copies are the places it could leave the server, and each has its own exclusion rule.
- This is the largest slice in Phase F. If it cannot be completed as one reviewable change, the
  natural split is to build contributions as the last increment and move them to their own slice;
  parties, relationship types and relationships do not depend on them.
- Guidance cannot stop the owner recording something that should not be recorded. The absence of
  fields for sensitive categories, the alias-first interface and real deletion reduce the harm; they
  do not remove it.
- A party record invites growth into a general contact-management or volunteer-management feature.
  Contact details, availability, matching and import each need their own contract.

# Slice 34 — Bundles

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). Whether a packaged bundle is only a download or also
something an AI assistant evaluates depends on the owner's answer to the "evaluation" question there.

### Goal
Let the owner select a named set of items of any kind, for a purpose and either as a standing set or
a temporary one, and reopen it as a set. A bundle is the unit used to make material available on the
phone (Slice 35) and to package a set of planning, meeting, discussion or topic material to take
elsewhere for evaluation or planning refinement.

### In scope
- `Bundle`: a user-owned record with a required `name` (≤255 characters), an optional `purpose`
  (≤2,000 characters), a `lifespan` of `standing` or `temporary`, an `expires_on` date allowed only
  for temporary bundles (a database `CHECK`), and `allow_sensitive`, a boolean defaulting to false.
- Explicit membership of items by type, from the types that exist when the slice is built: reference
  cards, cheat sheets, records (including decisions), ToDos, Projects, Notebooks,
  ResearchCollections, issues, needs and parties (people, groups and organisations). A member is a typed `(type, id)` pair in a join
  table with a closed vocabulary `CHECK`, as in Slices 22 and 28, and deleting an item removes its
  memberships by the same trigger mechanism.
- An explicit, one-time **add from** action that copies the current members of a cheat sheet or an
  issue hub into the bundle as individual members. It is a copy, not a live link, and is never
  automatic.
- **Sensitive types** are parties, needs and records (including decisions). Members of those types are
  listed in the bundle but are left out of its contents unless `allow_sensitive` is true. The
  client marks them and states why they are missing.
- A bounded **contents** endpoint: each member's content as serialised by that item's own endpoint
  and authorised by the same checks, with an `as_of` timestamp, honouring `allow_sensitive`, and
  capped by documented limits on member count and total response size. A member that no longer
  exists or can no longer be read is omitted. This is the one packaging surface in this slice, and
  it is what Slice 35 reads.
- A **Bundles** client view: list (standing, temporary, expired flagged), create with only a name,
  open, edit, delete behind confirmation, and an **Add to bundle** control on item detail views.
- Account export coverage and activity entries for bundle and membership changes (extending the
  closed `activity_records_subject_type_chk` set by migration).

### Out of scope
- Offline storage of any kind (Slice 35).
- Rendering a bundle to a document, or any downloadable file format (Slice 23), until the owner's
  answer on evaluation is known. Agent or AI access to a bundle (Slice 24).
- Sharing a bundle or collaborating on one; any capability grant.
- Automatic membership, such as everything carrying a tag; automatic deletion on expiry; and bundle
  versions or history. Expiry marks a bundle expired and, in Slice 35, removes device copies. It
  never deletes anything (`development-slice-protocol.md` §37).
- Authority over members. Like a Library (Slice 9), a bundle's membership grants no mutation,
  destructive, lifecycle, ownership or permission authority over a member.

### Dependencies
Slices 26 (cards and sheets), 27 (records), 30 (issues), 31 (needs), 32 (decisions) and 33 (people and organisations),
for the types that exist when it is built; Slice 9 (the non-authority invariant), 21 (export),
12 (activity), 17 (confirmation pattern).

### Required decision (ADR)
Whether a bundle is a topic hub (Slice 30) with extra fields, or a separate entity. The leaning is a
separate entity: its lifetime, expiry, sensitivity switch and device availability have no meaning for
a standing topic hub. The membership join and the `(type, id)` vocabulary check are the second
occurrence of the hub's mechanism, so under the Rule of Two the ADR should decide whether to extract
only that shared mechanism. The ADR must also fix the sensitivity classes (above) and the contents
limits.

### Required threat and authorization review
- Bundles are owner-only; members must be owned by the bundle's owner. Another user's bundle and a
  nonexistent one return `404`, as do a cross-user or nonexistent member on add.
- A bundle must not become a second read path. The contents endpoint calls the same per-type
  authorisation as each item's own endpoint, and membership of a bundle never authorises a read.
- A bundle is a disclosure unit: it is how content leaves the server for a device or a file. The
  default exclusion of sensitive types, the member and size caps, and the explicit `allow_sensitive`
  switch are the controls, and each needs a denied-path test.
- Names, purposes and contents are never logged. The export includes bundles and memberships for the
  user's own rows only.

### Test plan
- Migration apply, rollback and reapply, including the membership triggers for every member type.
- PHPUnit: ownership and the full denied-path matrix; lifespan and expiry constraints; membership
  uniqueness and cross-user rejection; the add-from action copies and does not link; deleting each
  member type removes only its memberships; the contents endpoint excludes sensitive members by
  default and includes them when `allow_sensitive` is true, omits unreadable members, and enforces
  both caps at their boundary; the non-authority invariant for every member type; pagination
  boundaries; activity entries; export contains only the user's bundles.
- A regression test that creating bundles changes no ToDo list, review queue or dashboard count.
- Browser tests for creating a bundle, adding items of several types from their detail views,
  add-from a cheat sheet, the sensitive-member marker, expiry display, and deleting behind
  confirmation; accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- The contents endpoint is the most likely place for a read-path shortcut, because it aggregates
  many types. Per-type authorisation and the denied-path matrix are the control.
- Bundles can quietly grow into a second, weaker hub. Purpose, lifespan and the sensitivity switch
  are what distinguish them; anything standing and topic-shaped belongs in an issue.

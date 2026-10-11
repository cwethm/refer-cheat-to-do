# Slice 27 — Discussion and event records

## Slice Contract

### Status
PROPOSED — second slice of Phase F (`docs/intended-use.md`)

### Goal
Let the owner record what happened and what was learned in a discussion or at an event, and find it
again by date, kind, tag or text.

### In scope
- `Record`: a user-owned record with a required `title` (≤255 characters), a `kind` from a closed set
  (`discussion`, `event`, enforced by a database `CHECK`) and a required `occurred_at` timestamp.
- Optional fields: `participants` (free text, ≤2,000 characters), `location` (events only,
  ≤255 characters), a one-line `summary` (≤500 characters) and plain-text `details`
  (≤65,536 UTF-8 bytes).
- Tagging a record with the user's existing tags.
- CRUD API following the established conventions.
- A chronological timeline, newest first with a deterministic tie-break, filterable by `kind`, tag,
  date range and search text (title, summary, participants, details).
- A **Records** client view: timeline, detail view, a low-friction create form that needs only a title
  and defaults `occurred_at` to now.
- Account export coverage and activity entries for record mutations (extending the closed
  `activity_records_subject_type_chk` set by migration).
- Tag association code shared with Slice 26 where the second occurrence justifies it
  (`development-slice-protocol.md` §12); otherwise left duplicated and documented.

### Out of scope
- Links to cards, other records, ToDos or issues (Slice 28) and to people and organisations
  (Slice 33). `participants` stays free text; parties are later linked in addition, and the text is
  never parsed into party records. Follow-up ToDos are created through the existing ToDo endpoints; nothing is
  extracted or created automatically.
- Contact details, linking participants to user accounts, a record for people (Slice 33), calendar integration,
  reminders, recurring events, attachments, audio or transcription.
- Sharing or capability grants, and any agent or AI summarisation.
- Archive or trash; deletion is permanent behind a confirmation.

### Dependencies
Slices 3 (tags and search conventions), 12 (activity), 17 (confirmation pattern), 21 (export), 26
(shared client and tag conventions).

### Required threat and authorization review
- Records describe other people. `participants` is free text, with no account linkage and no
  contact fields, and the client encourages roles, initials or aliases (`docs/intended-use.md`,
  sensitive information).
- Records are owner-only; ownership is set from the session and is immutable. Another user's record
  and a nonexistent one both return `404`.
- Text is plain text, stored and returned as text, inserted with `textContent`.
- Participants, titles, details and search text are never logged.
- The export includes records, so denied-path export tests are required.
- `occurred_at` validation must accept past and future dates (planned events) and reject malformed
  values with `400`.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership and the full denied-path matrix; `kind` constraint; field length boundaries;
  timeline ordering including identical `occurred_at` values; date-range and search filter
  boundaries; pagination boundaries; activity entries; export contains the new rows and no other
  user's rows.
- A regression test that creating records changes no ToDo list, review queue or dashboard count.
- Browser tests for create with title only, create with all fields, filter by kind and date range,
  edit, and delete behind confirmation; accessibility and overflow checks;
  `tests/js/client-security.test.mjs` passing.

### Risks
- Free-text participants are easy to over-fill with identifying detail. Guidance in the interface and
  the absence of contact fields reduce this; they do not remove it.
- A record type with only two kinds can become a catch-all. New kinds (Slice 32 adds `decision`) are a
  deliberate migration, not a free-form label.

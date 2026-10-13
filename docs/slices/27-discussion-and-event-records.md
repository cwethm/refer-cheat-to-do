# Slice 27 — Discussion and event entries

## Slice Contract

### Status
APPROVED (contract) — second slice of Phase F (`docs/intended-use.md`). The owner approved this
contract on 2026-10-13 and made the decisions recorded under **Decisions** below.

Much of the implementation was merged before the contract was approved (PR #31, under the name
`records`). Completing the slice therefore means bringing that code into line with this contract,
in the order set out under **Work plan**. The slice is IMPLEMENTED only when every item in the
**Test plan** passes, and APPROVED only after the owner has used the Entries view.

This file keeps its original path so existing links continue to work.

### Goal
Let the owner record what happened and what was learned in a discussion or at an event, and find it
again by date, kind, tag or text.

### In scope
- `Entry`: a user-owned entry with a required `title` (≤255 characters), a `kind` from a closed set
  (`discussion`, `event`, enforced by a database `CHECK`) and a required `occurred_at` timestamp.
  The kind set is closed; Slice 32 adds `decision` by migration.
- Optional fields: `participants` (free text, ≤2,000 characters), `location` (events only,
  ≤255 characters), a one-line `summary` (≤500 characters) and plain-text `details`
  (≤65,536 UTF-8 bytes).
- Tagging an entry with the user's existing tags, through tag handling shared with Slice 26.
- CRUD API following the established conventions (`data`/`meta`, `error.code`/`message`/`details`).
- A chronological timeline, filterable by `kind`, tag, date range and search text.
- An **Entries** client view: timeline, detail view, and a low-friction create form that needs only
  a title and defaults `occurred_at` to now.
- Account export and operator restore coverage, and activity entries for entry mutations.

### Out of scope
- Links to cards, other entries, ToDos or issues (Slice 28) and to people and organisations
  (Slice 33). `participants` stays free text; parties are later linked in addition, and the text is
  never parsed into party records. Follow-up ToDos are created through the existing ToDo endpoints;
  nothing is extracted or created automatically.
- Contact details, linking participants to user accounts, a record for people (Slice 33), calendar
  integration, reminders, recurring events, attachments, audio or transcription.
- Storing the time zone an entry was made in (see Risks).
- Sharing or capability grants, and any agent or AI summarisation.
- Archive or trash; deletion is permanent behind a confirmation.
- Changing how ToDos are tagged.

### Dependencies
Slices 3 (tags and search conventions), 12 (activity), 17 (confirmation pattern), 21 (export and
restore), 26 (shared client and tag conventions; APPROVED).

### Decisions
Made by the owner on 2026-10-13.

1. **Name.** The type is `Entry` / `entries`, not `Record` / `records`, to avoid confusion with the
   activity history (`activity_records`, `ActivityRecorder`) and to fit every kind, including
   Slice 32's `decision`. The rename covers:
   - tables `entries` and `entries_tags`, and their constraints and index
     (`entries_kind_chk`, `entries_location_chk`, `entries_details_size_chk`,
     `entries_owner_timeline_idx`);
   - classes `Entry`, `EntriesTable`, `EntriesTagsTable`, `EntriesController`;
   - the API at `/api/entries`, with the JSON key `entry`;
   - the activity subject type `entry` and the actions `entry.created`, `entry.updated`,
     `entry.deleted`, `entry.tag_attached` and `entry.tag_detached`, with
     `activity_records_subject_type_chk` updated to match;
   - export types `entries` and `entries_tags`;
   - the client view label **Entries** and `entry-*` element ids.

   Only test data exists in any deployment, so no data is converted, and exports made before the
   rename are not supported for restore. Restoring such an export must fail with a clear error and
   insert nothing.
2. **Shared tag handling.** Tag attach, detach and loading are extracted into shared code used by
   Slice 26 reference cards and by entries (Rule of Two, `development-slice-protocol.md` §12), before
   the rest of this slice is completed. The extraction keeps every endpoint, response shape, status
   code (`409` for an existing attachment, `404` for a missing one or another user's tag) and
   per-type activity action name unchanged. Each type keeps its own join table. If the client tag
   pickers of the Reference and Entries views are duplicated, they are shared in the same change.
   ToDo tagging is not changed.
3. **Location is events only.** A location on a discussion is rejected with `422` by validation and
   by the database `CHECK`; blank text is stored as `NULL` and accepted. Changing an event that has
   a location into a discussion without clearing the location in the same request is rejected with
   `422`. In the client the location field is hidden for discussions, and switching an event with a
   location to a discussion shows the approved warning and clears the location.
4. **Timeline order.** Newest `occurred_at` first, then newest `id` first. Every entry has exactly
   one position, so pages never overlap or skip entries while the data is unchanged. The client
   shows entries in the order the API returns them and never re-sorts. Page-number pagination can
   still shift when an entry is added between page loads, as for ToDos and reference cards.
5. **Times and time zones.**
   - `occurred_at` is accepted only as an ISO 8601 date and time with a time zone, past or future;
     anything else is rejected with `400`. It is stored in UTC to the second and returned in UTC.
   - The client shows times in the device's current local time zone, **with a time zone label**
     (for example `2026-10-13 18:30 BST`), and converts entered times from that zone to UTC.
   - Date ranges (`from`, `to`) include both ends. A date-only value sent to the API means the whole
     **UTC** day; the client sends the first and last moment of the **local** day instead.
   - A range whose `from` is later than its `to` is rejected with `400`.
6. **Export and restore.** The account export and the operator restore cover entries and their tag
   joins, following Slice 21: restore requires an empty target account and never merges. Restore
   assigns new ids in export order, so entries sharing an `occurred_at` keep their relative order.
   Times in the export file are UTC.
7. **Search** covers title, summary, participants, location and details, with the wildcard escaping
   and bounds used for ToDo search.
8. **Guidance on recording people.** The exact wording, approved by the owner, is:
   - Participants label (unchanged): `Participants (optional: roles, initials or aliases, no contact
     details)`.
   - Participants placeholder: `e.g. Chair (JM), tenant rep, me`.
   - A note beside Details, linked to the field with `aria-describedby`: *Write about people by role
     or alias. Leave out health, legal or immigration status, addresses and affiliations.*
   - The kind-switch warning: *Discussions don't have a location. Switching will clear it.*

   Slices 31 and 33 reuse the same participants guidance.

### Required threat and authorization review
- Entries describe other people. `participants` is free text, with no account linkage and no
  contact fields, and the client encourages roles, initials or aliases (`docs/intended-use.md`,
  sensitive information).
- Entries are owner-only; ownership is set from the session and is immutable, and `user_id` is not
  mass-assignable. Another user's entry and a nonexistent one both return `404`. A tag belonging to
  another user returns `404`, and a `tag` filter naming one matches nothing.
- Text is plain text, stored and returned as text, and inserted with `textContent`.
- Participants, titles, locations, details and search text are never logged (`SafeLogEvent` only).
- The export includes entries, so denied-path export tests are required.
- Entries are not ToDos: they must not appear in ToDo lists, the review queue, orphan detection or
  dashboard counts.
- The shared tag code carries the same-user tag check for both types, so it is tested once directly
  and again through each type's endpoints.

### Test plan
- Migrations apply, roll back and reapply on PostgreSQL, including the rename migration.
- PHPUnit:
  - ownership and the full denied-path matrix for every endpoint;
  - the `kind` constraint;
  - field length boundaries;
  - location rejected on a discussion, accepted on an event, blank accepted on a discussion, and an
    event-to-discussion change with a location still set rejected with `422`;
  - `occurred_at` accepted past and future, and malformed values rejected with `400`;
  - timeline ordering, including several entries with the same `occurred_at` spanning a page
    boundary, each appearing on exactly one page;
  - date-range boundaries for both date-only and full-timestamp bounds, and `400` for a reversed
    range;
  - search over every searched field, including location, and escaping of `%`, `_` and `\`;
  - pagination boundaries;
  - activity entries under the subject type `entry`;
  - export contains the new rows and no other user's rows;
  - restore into an empty account, including tag joins, activity pointers, long `details` and the
    relative order of entries sharing an `occurred_at`;
  - restoring an export made before the rename fails with a clear error and inserts nothing;
  - the shared tag code: another user's tag `404`, duplicate attach `409`, missing detach `404`, an
    activity entry written; Slice 26 tag tests pass unchanged.
- A regression test that creating entries changes no ToDo list, review queue or dashboard count.
- Client unit tests: the timeline URL builder, local-day bounds (including a daylight-saving change
  day) and the time zone label.
- Browser tests:
  - create with title only, and with all fields;
  - filter by kind, tag and date range, and search;
  - edit, and delete behind confirmation;
  - the location field hidden for discussions;
  - switching an event with a location to a discussion shows the warning and clears the location;
  - the participants placeholder and the Details note are present;
  - times are shown with a time zone label;
  - accessibility audit and no overflow at 320, 375, 768 and 1280 CSS pixels;
  - `tests/js/client-security.test.mjs` passing.

### Work plan
Each step is its own pull request, merged only with CI passing.

1. **Rename** `records` to `entries` across the database, code, API, activity history, export,
   client and tests, with no change in behaviour. Existing tests pass after the rename alone.
2. **Shared tag handling** for reference cards and entries (Decision 2). Existing Slice 26 and
   entry tag tests pass unchanged; new tests cover the shared code.
3. **Completion**: location search, location kind-switch behaviour, reversed-range `400`, the time
   zone label, the guidance wording, the remaining PHPUnit, restore and browser tests, then the
   Delivered API and Completion sections below, and the roadmap status.

### Risks
- Free-text participants and details are easy to over-fill with identifying detail. The guidance
  wording and the absence of contact fields reduce this; they do not remove it.
- The time zone an entry was made in is not stored. Times are shown in the device's current time
  zone, so an entry made in one place and read in another shows the reader's local time; the time
  zone label makes this visible. Storing the original zone is a possible later change.
- An entry type with only two kinds can become a catch-all. New kinds (Slice 32 adds `decision`)
  are a deliberate migration, not a free-form label.
- Renaming after merge touches many files at once. Doing it before Slices 28, 32 and 34 build on
  the type keeps it as small as it will ever be.

### Delivered API
To be completed in step 3 of the Work plan.

### Completion
To be completed in step 3 of the Work plan.

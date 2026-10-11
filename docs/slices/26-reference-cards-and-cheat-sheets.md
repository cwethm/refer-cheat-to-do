# Slice 26 — Reference cards and cheat sheets

## Slice Contract

### Status
PROPOSED — first slice of Phase F (`docs/intended-use.md`). The dialogue-pattern structure below is
the recommended default and is awaiting the owner's confirmation; the rest of the contract does not
depend on it.

### Goal
Give the owner a first-class, searchable place for short reference material that is consulted rather
than completed: concepts and topic-specific dialogue patterns, grouped into cheat sheets, found
quickly enough to use during a conversation.

### In scope
- `ReferenceCard`: user-owned record with a required `title` (≤255 characters), a `card_type` from a
  closed set (`concept`, `dialogue_pattern`, enforced by a database `CHECK`), a one-line `summary`
  (≤500 characters, the line read at a glance) and plain-text `body` (≤65,536 UTF-8 bytes, as in
  Slice 22). Only the title is required. `concept` and `dialogue_pattern` are defined in
  `docs/intended-use.md`.
- Dialogue-pattern fields (recommended): optional `situation`, `aim`, `approach`, `avoid` and
  `rationale`, each plain text of at most 2,000 characters, valid only when `card_type` is
  `dialogue_pattern` (a database `CHECK` requires them to be `NULL` otherwise, so the type keeps its
  meaning). The client shows populated fields as labelled blocks beneath the summary and hides
  empty ones. If the owner chooses summary plus free text instead, these five fields and their
  `CHECK` are dropped and nothing else in the contract changes.
- Tagging a card with the user's existing tags.
- Cheat sheets: a named, ordered selection of cards, with a card allowed in more than one sheet.
- CRUD API following the established conventions (`data`/`meta`, `error.code`/`message`/`details`).
- Search over title, summary, dialogue-pattern fields and body, filtered by `card_type`, tag and
  cheat sheet, with the
  wildcard escaping and bounded pagination used for ToDo search.
- A **Reference** client view: search-first lookup, a card reading view and an ordered cheat-sheet
  view that is legible at 320 CSS pixels.
- Account export coverage for cards and cheat sheets, and activity entries for their mutations
  (extending the closed `activity_records_subject_type_chk` set by migration).

### Out of scope
- Sharing, capability grants or any public access. Cards are private (`docs/intended-use.md`).
- Links between cards and other items, including a pattern to the concepts it relies on (Slice 28),
  recall practice (Slice 29), bundles and offline copies (Slices 34 and 35), printing (Slice 23).
- Rich-text, Markdown or HTML rendering, attachments, import from other products, offline caching.
- Archive or trash for cards; deletion is permanent behind a confirmation.
- Full-text search infrastructure. Case-insensitive substring search is adequate at personal scale.

### Dependencies
Slice 3 (tags and search conventions), 12 (activity), 16 and 17 (client scaffolding and confirmation
pattern), 21 (export).

### Required decision (ADR)
How a cheat sheet relates to its cards. Options:

1. **Notebook sections** — reuse Notebooks and `SectionOrderingBehavior`, with a card in at most one
   section.
2. **A `cheat_sheets` table with ordered membership** — a card may appear in several sheets.

The leaning is option 2: the same card is routinely needed in more than one situation, which option 1
forbids by construction, and a card is not a ToDo, so ADR 0001 does not apply. Membership follows the
Library precedent (composite primary key, `CASCADE` on the join row, parents never cascaded) with a
`position` per membership. The ADR must also decide whether ordering reuses `SectionOrderingBehavior`
or needs its own, applying the Rule of Two (`development-slice-protocol.md` §12).

### Required threat and authorization review
- Cards and sheets are owner-only. Ownership is set from the session and is immutable; `user_id`
  is not mass-assignable. Another user's card or sheet, and a nonexistent one, both return `404`.
  A sheet may contain only cards owned by the same user.
- Card text is plain text, returned as text and inserted by the client with `textContent`. Nothing
  is fetched or rendered as markup.
- Deleting a card removes its memberships and tag joins but never a sheet or tag. Deleting a sheet
  removes only its memberships. Deleting an account that owns cards follows the `RESTRICT` policy
  of Slice 22.
- Cards are not ToDos. They must not appear in ToDo lists, the review queue, orphan detection or
  dashboard counts.
- Search input is escaped and bounded. Search text, titles and bodies are never logged.
- The export includes cards, sheets and memberships for the user's own rows only.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership and the full denied-path matrix for every endpoint; `card_type` constraint;
  dialogue-pattern fields rejected on a concept card and accepted at their length boundary; title,
  summary and body length boundaries; membership ordering and cross-user membership
  rejection; search escaping of `%`, `_` and `\`; pagination boundaries; delete cascade behaviour;
  activity entries; export contains the new rows and no other user's rows.
- A regression test that creating cards changes no ToDo list, review queue or dashboard count.
- Client unit tests for the search URL builder; browser tests for create, search, open a card, open
  and reorder a cheat sheet, and delete behind confirmation; accessibility audit and overflow checks
  at 320, 375, 768 and 1280 CSS pixels; `tests/js/client-security.test.mjs` passing.

### Risks
- Reference material drifting into the ToDo model because it is the nearest existing record. The
  separate record type and the regression test above guard against it.
- Substring search over bodies is slow at large scale. The upgrade path (PostgreSQL full-text search)
  is a later, separate decision.
- A flat list of cards becomes hard to browse; sheets and tags are the answer, not a nested hierarchy.

# ADR 0004 — Cheat sheets are their own table with ordered card membership

## Status
Accepted (Slice 26)

## Context
Slice 26 introduces reference cards (concepts and dialogue patterns) and cheat sheets: a named,
ordered selection of cards for a situation. The slice contract requires that a card may appear in
more than one cheat sheet, because the same concept or pattern is routinely needed in several
situations (`docs/intended-use.md`, "Look up").

Two shapes were considered:

1. **Notebook sections.** Reuse Notebooks, NotebookSections and `SectionOrderingBehavior`, placing
   each card in a section. A row can belong to only one section, so a card could be in at most one
   sheet. ADR 0001 (one organizational section per ToDo) does not apply to cards, which are not
   ToDos, but the single-parent shape would still forbid the required many-to-many use.
2. **A `cheat_sheets` table with ordered membership.** A sheet is its own owner-scoped record, and
   `cheat_sheets_reference_cards` joins it to cards with a `position` per membership.

## Decision
Use option 2.

- `cheat_sheets` is owner-scoped (`user_id`, `RESTRICT` on account deletion, as Slice 22).
- `cheat_sheets_reference_cards` follows the Library membership precedent: a composite primary key
  `(cheat_sheet_id, reference_card_id)`, `CASCADE` on both foreign keys so deleting a sheet or a card
  removes only the join rows, and parents are never cascaded from a join row. It adds a `position`
  (1-based, contiguous within a sheet).
- A sheet may contain only cards owned by the same user. This is enforced server-side when a card is
  added; another user's card and a nonexistent card both return `404`.
- Ordering reuses `SectionOrderingBehavior`. Membership is the third ordered sibling set after
  ProjectSections and NotebookSections, so the Rule of Two/Three (`development-slice-protocol.md`
  §12) favours the existing component over a second implementation. The behaviour orders siblings
  by `position` and then by the table's primary key, and identifies the moved row by its primary
  key, so a composite-key join row is ordered by the same code as a section row. Its responsibility
  (contiguous 1-based positions within one parent) is unchanged.

## Consequences
- The same card can be placed in any number of sheets, each with its own position.
- Deleting a card removes it from every sheet; the affected sheets are renumbered in the same
  transaction so positions stay contiguous.
- Cards are not organizational sections. They never appear in ToDo lists, the review queue, orphan
  detection or dashboard counts.
- Sharing a sheet with named people (Slice 36) can publish a copy of a sheet's ordered cards without
  changing this storage shape.

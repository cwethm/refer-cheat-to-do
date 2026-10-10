# Slice 31 — Community solidarity needs

## Slice Contract

### Status
PROPOSED — sixth slice of Phase F (`docs/intended-use.md`)

### Goal
Let the owner keep a private record of needs they are aware of in their community and what is being
done about each, so that solidarity actions are followed through and a need is not quietly forgotten.

### In scope
- `Need`: a user-owned record with a required `title` (≤255 characters), plain-text `description`
  (≤65,536 UTF-8 bytes), an optional `needed_by` date and a `status` from a closed set (`open`,
  `in_progress`, `met`, `closed`, enforced by a database `CHECK`).
- A resolution note recorded when a need is `met` or `closed`, and a `status_changed_at` timestamp
  maintained by the service.
- Tagging with the user's existing tags. Linking a need to the record in which it was raised and to
  the follow-up ToDos working on it, through Slice 28 links (this slice adds `need` to the link
  vocabulary). Placing a need under an issue through Slice 30 hub membership (this slice adds `need`
  to the member vocabulary).
- A **Needs attention** list: open or in-progress needs, with deterministic, explainable reason codes
  (`needed_by_soon`, `needed_by_passed`, `no_movement` after a documented number of days, `no_follow_up`
  when no ToDo is linked), ordered most urgent first.
- A **Needs** client view: list filtered by status, tag and issue; detail; a low-friction create form
  needing only a title; the Needs attention list.
- Account export coverage and activity entries for need mutations.

### Out of scope
- Contact details, addresses, linking to user accounts or to other people, or a "people" record. The
  interface encourages roles, initials or aliases (`docs/intended-use.md`, sensitive information).
- Sharing, assigning a need to another user, requests between people, or any capability grant. Needs
  are private. Shared solidarity work would need its own ADR extending ADR 0002.
- Notifications, email, reminders or any outbound request.
- Any agent or AI triage of needs.
- Archive or trash; deletion is permanent behind a confirmation.

### Dependencies
Slices 27 (records), 28 (links), 30 (issues), 7 (the explainable-reasons precedent), 21 (export),
12 (activity), 17 (confirmation pattern).

### Required decision
Whether the `no_movement` rule reuses `ReviewSchedulingService` or is a small rule set of its own,
under the Rule of Two and Three (`development-slice-protocol.md` §12), as in Slice 29. Needs are not
ToDos and must not enter the ToDo review queue.

### Required threat and authorization review
- Needs are the most sensitive record in this direction. They are owner-only; ownership is set from
  the session and is immutable. Another user's need and a nonexistent one both return `404`.
- No contact or identifying fields exist. Text is plain, stored and returned as text, inserted with
  `textContent`, and never logged along with titles or search text.
- Needs attention reasons are computed from the owner's own rows, by rules visible in the response, with
  no hidden scoring.
- Dashboard counts are unchanged; if a needs count is later wanted on the Today view, it is a count
  only and needs its own contract.
- The export includes needs. Denied-path export tests outweigh happy-path tests.
- Status transitions are validated by the service and the database; a `met` or `closed` need cannot
  return to `open` without an explicit reopen action that is recorded in activity.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership and the full denied-path matrix; status constraint and transitions including
  reopen; length boundaries; every Needs attention rule at its boundary and the stable ordering of
  reasons; query count independent of the number of needs; link behaviour on deleting a need;
  activity entries; export contains only the user's needs.
- A regression test that creating needs changes no ToDo list, review queue or dashboard count.
- Browser tests for creating a need, moving it through statuses, linking a follow-up ToDo, viewing
  Needs attention and its empty state, and deleting behind confirmation; accessibility and overflow
  checks; `tests/js/client-security.test.mjs` passing.

### Risks
- The most likely failure is quiet: a need that is recorded and never surfaced is no better than
  forgotten. Needs attention is therefore in scope and not deferred.
- Needs invite recording identifying detail about people in difficulty. The absence of contact
  fields, interface guidance and private-by-default storage limit the harm; operator controls
  (disk encryption, backups) are documented, not enforced, by the application.

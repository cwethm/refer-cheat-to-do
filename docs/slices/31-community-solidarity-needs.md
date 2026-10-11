# Slice 31 — Community solidarity needs: perspectives and factors

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). The perspective-and-factors framing follows the owner's
stated aim for needs; the exact fields are awaiting the owner's confirmation.

### Goal
Let the owner keep a private record of needs they are aware of in their community, together with the
detail and factors that bear on each, so that recommendations and contributions are formed with a
broader perspective. Secondarily, make sure a need that is being acted on is not quietly forgotten.

### In scope
- `Need`: a user-owned record with a required `title` (≤255 characters) and these optional plain-text
  fields:
  - `description` (≤65,536 UTF-8 bytes): what is needed and for whom, in roles or aliases.
  - `factors` (≤65,536 UTF-8 bytes): the circumstances, constraints, trade-offs and other
    considerations around the need, who else is affected, and what has been tried.
  - `needed_by` (date) and `last_confirmed_on` (date): when it is needed, and when the owner last
    checked that what is recorded is still true.
  - a `status` from a closed set (`open`, `in_progress`, `met`, `closed`, enforced by a database
    `CHECK`), a resolution note when `met` or `closed`, and a service-maintained `status_changed_at`.
- Tagging with the user's existing tags. Linking a need to the record in which it was raised and to
  the follow-up ToDos working on it, through Slice 28 links (this slice adds `need` to the link
  vocabulary). Placing a need under an issue through Slice 30 hub membership (this slice adds `need`
  to the member vocabulary). A decision that was informed by a need links to it from the decision
  (Slice 32).
- A **Perspectives** view, the main use: the needs on an issue or tag, each showing its description,
  factors and how recently it was confirmed, newest-confirmed first, filterable by status, issue and
  tag. `met` and `closed` needs stay visible here, because what happened is also perspective.
- A **Needs attention** list for needs with `open` or `in_progress` status, with deterministic,
  explainable reason codes, ordered by this tuple:
  `(highest-priority reason rank ASC, needed_by ASC NULLS LAST, last_confirmed_on ASC NULLS FIRST,
  updated_at ASC, id ASC)`. A need's rank is the highest-priority (lowest-numbered) emitted reason,
  with fixed precedence `needed_by_passed`, `needed_by_soon`, `not_confirmed_recently`, `no_movement`,
  `no_follow_up`; all emitted codes are returned in that same precedence order:
  - `needed_by_soon` and `needed_by_passed`, against `needed_by`;
  - `not_confirmed_recently`, when `last_confirmed_on` is older than a documented number of days (or
    missing), because a need used as perspective must be current;
  - `no_movement`, when an `in_progress` need has not changed status or been edited for a documented
    number of days;
  - `no_follow_up`, only for `in_progress` needs with no linked ToDo. An `open` need is not required
    to have one: the owner may record it purely as perspective.
- A **Needs** client view: Perspectives, Needs attention (with its empty state), list, detail, a
  low-friction create form needing only a title, edit, and delete behind confirmation.
- Account export coverage and activity entries for need mutations (extending the closed
  `activity_records_subject_type_chk` set by migration).

### Out of scope
- Contact details, addresses, linking to user accounts, or identifying individuals beyond free text.
  People and organisations are a separate record (Slice 33), which a need can then link to. The interface encourages
  roles, initials or aliases (`docs/intended-use.md`, sensitive information).
- Sharing, assigning a need to another user, requests between people, or any capability grant. Needs
  are private. Shared solidarity work would need its own ADR extending ADR 0002.
- Notifications, email, reminders or any outbound request.
- Any agent or AI triage or summarisation of needs.
- Archive or trash; deletion is permanent behind a confirmation.

### Dependencies
Slices 27 (records), 28 (links), 30 (issues), 7 (the explainable-reasons precedent), 21 (export),
12 (activity), 17 (confirmation pattern).

### Required decision
Whether the `no_movement` and `not_confirmed_recently` rules reuse `ReviewSchedulingService` or are
a small rule set of their own, under the Rule of Two and Three (`development-slice-protocol.md` §12).
Needs are the second consumer of an explainable-reasons rule after ToDo review; cards (Slice 29) will
be the third and are the point at which a shared extraction, if any, is justified. Needs are not
ToDos and must not enter the ToDo review queue.

### Required threat and authorization review
- Needs are sensitive. They are owner-only; ownership is set from the session and is immutable.
  Another user's need and a nonexistent one both return `404`.
- No contact or identifying fields exist. Text is plain, stored and returned as text, inserted with
  `textContent`, and never logged along with titles, factors or search text.
- Perspectives and Needs attention are computed from the owner's own rows, by rules visible in the
  response, with no hidden scoring or ranking.
- Dashboard counts are unchanged; if a needs count is later wanted on the Today view, it is a count
  only and needs its own contract.
- The export includes needs. Denied-path export tests outweigh happy-path tests.
- Status transitions are validated by the service and the database; a `met` or `closed` need cannot
  return to `open` without an explicit reopen action that is recorded in activity.
- Needs are a sensitive type for bundles and offline copies (Slices 34 and 35): excluded unless the
  owner explicitly allows it for that bundle.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: ownership and the full denied-path matrix; status constraint and transitions including
  reopen; length boundaries; every Needs attention rule at its boundary, including
  `not_confirmed_recently` with a missing date, `no_follow_up` applying only to `in_progress`, and
  the stable ordering of reasons; the Perspectives view listing only the owner's needs and including
  `met` and `closed`; query count independent of the number of needs; link behaviour on deleting a
  need; activity entries; export contains only the user's needs.
- A regression test that creating needs changes no ToDo list, review queue or dashboard count.
- Browser tests for creating a need, recording its factors, moving it through statuses, linking a
  follow-up ToDo, viewing Perspectives for an issue, viewing Needs attention and its empty state, and
  deleting behind confirmation; accessibility and overflow checks; `tests/js/client-security.test.mjs`
  passing.

### Risks
- A need that is recorded and never surfaced is no better than forgotten, and a need that is out of
  date misleads the thinking it is meant to inform. Needs attention and `last_confirmed_on` exist for
  that reason and are in scope, not deferred.
- Needs invite recording identifying detail about people in difficulty. The absence of contact
  fields, interface guidance and private-by-default storage limit the harm; operator controls
  (disk encryption, backups) are documented, not enforced, by the application.
- Two large text fields invite long, unstructured notes. Retrieval depends on the Perspectives view
  showing the beginning of each field, not the whole of it.

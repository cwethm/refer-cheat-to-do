# Slice 29 — Recall practice for reference cards

## Slice Contract

### Status
PROPOSED — scheduled after Slice 35 in Phase F and before Slices 19 and 36 (`docs/roadmap.md`). Fast
lookup (Slice 26) and offline access (Slice 35) do most of the work of not straining to recall;
practice reinforces the cards the owner wants to know by heart. Its core depends on Slice 26; the
optional issue-scoped session depends on Slice 30, so moving it before Slice 30 requires deferring
that option.

### Goal
Resurface reference cards on a deterministic schedule so that concepts and dialogue patterns are
retained, not only stored, and the owner is not straining to recall them when they are needed.

### In scope
- Practice is opt-in per card (`practice_enabled`, default false), so a growing set of cards does
  not flood the queue.
- Practice bookkeeping on cards: `last_practised_at`, `next_practice_at` and `practice_interval_days`,
  with a default interval and a documented minimum and maximum enforced by a `CHECK`.
- A practice queue of due cards, ordered by how overdue each is, paginated, with the reason a card is
  due.
- An explicit outcome per card, `recalled` or `forgot`, which reschedules it by a simple, documented,
  deterministic rule: `recalled` lengthens the interval up to the maximum, `forgot` returns it to the
  default. Plus a snooze that reschedules without changing the interval.
- A **Practise** client view: the card's title and summary are shown first, the body is revealed on
  request, then the owner picks `recalled` or `forgot`. A session can optionally be limited to one
  cheat sheet or one issue (Slice 30), for example the sheet for tomorrow's meeting.

### Out of scope
- Any adaptive or learned scheduling, ratings beyond the two outcomes, streaks, statistics or
  gamification.
- Notifications, email or any out-of-band reminders.
- Practice for records, needs or ToDos. ToDo review (Slice 7) is unchanged and cards never enter it.
- AI-generated questions or answers.

### Dependencies
Slice 26 (cards), Slice 7 (the scheduling and explainability precedent), Slice 12 (activity), and
Slice 30 (issues) for the optional scoping.

### Required decision
Whether the date arithmetic reuses `ReviewSchedulingService`. It is currently the single authority
for ToDo review dates. Because Slice 31 now precedes this slice, community needs are the second
consumer of that kind of rule and cards the third, so the Rule of Two and Three applies (`development-slice-protocol.md` §12): extract only the shared
computation, not a generic "reviewable" abstraction, and leave ToDo behaviour byte-for-byte unchanged.

### Required threat and authorization review
- Practice endpoints are owner-only; another user's card and a nonexistent card both return `404`,
  and the practice queue never includes other users' cards.
- Outcomes and snoozes mutate only the scheduling fields of the owner's card, never its content.
- The queue must be bounded by `page` and `limit`, and may not issue a query per card.
- Practice state is part of the export.

### Test plan
- Migration apply, rollback and reapply.
- PHPUnit: the interval rule at the minimum, default and maximum boundaries; due and not-yet-due
  edges; ordering ties; opt-in and opt-out; denied-path matrix; query count independent of queue
  size; activity entries; export includes practice fields.
- Regression tests that ToDo review scheduling, the review queue and the dashboard counts are
  unchanged.
- Browser tests for practising a card through reveal, `recalled`, `forgot` and snooze, and for the
  empty queue; accessibility and overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- Spaced repetition invites feature growth. The two-outcome, fixed-rule scheduler is the boundary;
  anything adaptive needs a new contract.
- Showing the body only on request can hide content from assistive technology if implemented with
  visual hiding alone. The accessibility audit must cover the revealed and hidden states.

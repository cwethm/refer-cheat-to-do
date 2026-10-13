# Roadmap

## Foundation sequence

1. Repository and environment foundation
2. Authentication
3. ToDo capture and Inbox
4. Tags and search
5. Projects and ProjectSections
6. Notebooks and NotebookSections
7. Lifecycle / Archive / Trash
8. Review engine
9. ToDo relationships
10. Libraries
11. Capability model
12. Cross-context requests
13. Dashboard and activity history

## Current status

MVP 1 is **READY**. Every roadmap step (1-13) is delivered and its slice is APPROVED in
`docs/slices/`. The MVP release verification suite passes; see `docs/mvp-1-completion-report.md`.

The first post-MVP slice, **14 — Client-facing daily workflow**, is **APPROVED**. It adds a responsive
same-origin client at `/app` for the completed API without adding domain features or changing API
authorization; see `docs/slices/14-client-facing-daily-workflow.md`.

Slices 14–22 are delivered. Slice 19 (account lifecycle) was built ahead of its Phase F position
because it is a prerequisite of sharing cheat sheets (Slice 36). The owner's description of how the application will actually be used —
reference cards and cheat sheets, records of discussions and events, planning and strategic thinking,
and organising material around social issues and community solidarity needs — is recorded in
`docs/intended-use.md`. It shows that the system so far covers tasks, projects and gathered source
material but has no record type for reference material, discussions, events, decisions or community
needs. **Phase F below, now ahead of the remaining domain expansion, closes that gap.** Slice 26
(reference cards and cheat sheets) is **APPROVED**. Slice 27's contract is approved and its
completion is in progress: rename to `entries`, shared tag handling, then the remaining work.

## Post-MVP sequence

Work after Slice 14 is organized into phases. Each numbered slice has a contract in
`docs/slices/`; a contract marked PROPOSED has not been implemented.

Slice numbers are stable identifiers, not an execution order. Phase order governs, so the Phase F
slices (26–36) are built before the Phase G slices (23–25) even though their numbers are higher.

### Phase A — Front-end testing hardening (DELIVERED)

The manual Chromium verification recorded in Slice 14 is now an automated, CI-run suite:

- `tests/e2e/app.spec.mjs` covers the daily workflow, session lifecycle, pagination boundaries,
  empty states, API error surfacing and expired-session recovery.
- `tests/e2e/accessibility.spec.mjs` runs an axe WCAG 2.1 A/AA audit of every view, checks keyboard
  reachability, and asserts no horizontal overflow at 320, 375, 768 and 1280 CSS pixels.
- `tests/js/client-security.test.mjs` is the standing client security regression suite every future
  client slice must keep passing.
- `docs/front-end-testing-policy.md` records the strategy, the supported browser baseline, the
  offline and slow-network expectations, and the security checklist.
- CI runs the browser suites in a dedicated `browser` job alongside the existing `quality` job.

### Phase B — Client feature parity with the API

15. Projects and ProjectSections in the client, including section assignment — **APPROVED**; see
    `docs/slices/15-client-projects-and-sections.md`
16. Notebooks, NotebookSections and Libraries in the client — **APPROVED**; see
    `docs/slices/16-client-notebooks-and-libraries.md`
17. Relationships, objectives, lifecycle and activity history in the client — **APPROVED**; see
    `docs/slices/17-client-relationships-lifecycle-and-activity.md`

Each follows the Slice 14 rule: no new domain rules, no new endpoints, no new authorization.

### Phase C — Collaboration surfaces

18. Capability grant/revoke and cross-context request inbox, plus an ADR deciding whether activity
    history stays actor-only once resources are genuinely shared — **DELIVERED**; see
    `docs/slices/18-collaboration-surfaces.md` and `docs/adr/0003-actor-only-activity-visibility.md`

### Phase D — Account and operations maturity

19. Account lifecycle and session security (invitation, password change and reset, rate limiting) —
    **IMPLEMENTED**; see `docs/slices/19-account-lifecycle-and-session-security.md`. Accounts can be
    created by invitation; `bin/cake set_user_password` remains the operator recovery path. It is
    the prerequisite of Slice 36.
20. Operational maturity (dashboard query performance, structured logging, observability) —
    **IMPLEMENTED**; see `docs/slices/20-operational-maturity.md` and `docs/application-design.md`
21. Data export and account data portability — **IMPLEMENTED**; see
    `docs/slices/21-data-export-and-portability.md`

### Phase E — Research foundation

22. ResearchCollection foundation — **IMPLEMENTED**; see
    `docs/slices/22-research-collection-foundation.md`

### Phase F — Knowledge, records and community organising

Directed by `docs/intended-use.md`. Unlike Phases B–D, each slice here is vertical: the API and its
client view ship together, because a record type the owner cannot reach from the client does not yet
support the intended use. Every slice must also meet the standing requirements in
`docs/intended-use.md` (owner-only, exported, activity-recorded, plain text, dashboard and review
unaffected, browser-tested), and the sensitive-information constraints that go with them.

Listed in the order they are intended to be built. Slice numbers are not the order; see the note
after the list.

26. Reference cards and cheat sheets — **APPROVED**; see
    `docs/slices/26-reference-cards-and-cheat-sheets.md`
27. Discussion and event entries — contract **APPROVED**, completion in progress; see
    `docs/slices/27-discussion-and-event-records.md`
28. Cross-links and backlinks — **PROPOSED**; see `docs/slices/28-cross-links-and-backlinks.md`
30. Issues: topic hubs for social issues — **PROPOSED**; see `docs/slices/30-issues-topic-hubs.md`
31. Community solidarity needs, as perspectives and factors — **PROPOSED**; see
    `docs/slices/31-community-solidarity-needs.md`
32. Planning and strategic thinking, with a decision log and revisit dates — **PROPOSED**; see
    `docs/slices/32-planning-and-strategic-thinking.md`
33. People and organisations, with defined relationships and contributions — **PROPOSED**; see
    `docs/slices/33-people-and-organisations.md`
34. Bundles of resources for local access and packaging — **PROPOSED**; see
    `docs/slices/34-bundles.md`
35. Offline access to bundles — **PROPOSED**; needs an ADR and the owner's approval first, because it
    amends the standing client storage and service-worker rules; see
    `docs/slices/35-offline-access.md`
29. Recall practice for reference cards — **PROPOSED**; see
    `docs/slices/29-recall-practice-for-reference-cards.md`
19. Account lifecycle and session security — the Phase D slice above, the prerequisite of Slice 36 —
    **IMPLEMENTED**; see `docs/slices/19-account-lifecycle-and-session-security.md`
36. Sharing cheat sheets with named people — **PROPOSED**; needs Slice 19 and an ADR first; see
    `docs/slices/36-sharing-cheat-sheets.md`

Ordering notes:

- Slice 27 follows 26 because it reuses its tag and client conventions, and 28 needs both. Slices 30,
  31 and 32 need 28's links. Slice 33 (people) comes after the entries, needs and decisions it
  connects to exist, and 34 after every type it can contain.
- Slice 27 is completed in three steps, each its own pull request: rename `records` to `entries`,
  share tag handling with Slice 26, then the remaining work. See the Work plan in its contract.
- The owner confirmed the order 33, 34, 35, then 29. Slice 29 depends only on 26. It follows 30 and 31
  because the owner's stated purpose for community needs is to broaden the perspective behind
  recommendations, which those slices serve directly. The choice is reversible: moving 29 earlier
  changes no dependency. The implications of each order are set out in `docs/intended-use.md`.
- Slice 36 depends on Slices 19 and 26 and on no other Phase F slice, so it follows 19 and can be moved
  ahead of 29, or earlier still, without changing a dependency. It is placed after 29 only to leave the
  confirmed order undisturbed; whether to bring it forward is an open question in
  `docs/intended-use.md`.
- Slice 35 does not start until its ADR is accepted and the owner has agreed to amend
  `docs/front-end-testing-policy.md` and `tests/js/client-security.test.mjs` as that ADR specifies.
  Slice 34 is useful on its own as a way to gather and reopen sets of material. Offline access is
  read-only; recording while offline is not planned.

### Phase G — Document, agent and automation expansion

Previously the rest of Phase E, now deferred behind Phase F. Contracts first, in dependency order,
each with its own threat and authorization review:

23. Document generation — first use is printing cheat sheets (Slice 26); see
    `docs/slices/23-document-generation.md`
24. AI agent integration — records and needs are excluded from agent access unless granted per
    resource; see `docs/slices/24-ai-agent-integration.md`
25. Huginn orchestration — see `docs/slices/25-huginn-orchestration.md`

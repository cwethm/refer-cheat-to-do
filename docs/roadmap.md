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

Slices 14–18 and 20–22 are delivered; Slice 19 (account lifecycle) is still PROPOSED and is not needed
for single-owner use. The owner's description of how the application will actually be used —
reference cards and cheat sheets, records of discussions and events, planning and strategic thinking,
and organising material around social issues and community solidarity needs — is recorded in
`docs/intended-use.md`. It shows that the system so far covers tasks, projects and gathered source
material but has no record type for reference material, discussions, events, decisions or community
needs. **Phase F below, now ahead of the remaining domain expansion, closes that gap.**

## Post-MVP sequence

Work after Slice 14 is organized into phases. Each numbered slice has a contract in
`docs/slices/`; a contract marked PROPOSED has not been implemented.

Slice numbers are stable identifiers, not an execution order. Phase order governs, so the Phase F
slices (26–32) are built before the Phase G slices (23–25) even though their numbers are higher.

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
17. Relationships, objectives, lifecycle and activity history in the client — **DELIVERED**; see
    `docs/slices/17-client-relationships-lifecycle-and-activity.md`

Each follows the Slice 14 rule: no new domain rules, no new endpoints, no new authorization.

### Phase C — Collaboration surfaces

18. Capability grant/revoke and cross-context request inbox, plus an ADR deciding whether activity
    history stays actor-only once resources are genuinely shared — **DELIVERED**; see
    `docs/slices/18-collaboration-surfaces.md` and `docs/adr/0003-actor-only-activity-visibility.md`

### Phase D — Account and operations maturity

19. Account lifecycle and session security (invitation, password change and reset, rate limiting) —
    **PROPOSED**, not started. Accounts are still created with `bin/cake set_user_password`. It becomes
    a prerequisite if cheat sheets are ever shared with other people (see `docs/intended-use.md`).
20. Operational maturity (dashboard query performance, structured logging, observability) —
    **IMPLEMENTED**; see `docs/application-design.md` (the contract's status line still reads
    PROPOSED)
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

26. Reference cards and cheat sheets — **PROPOSED**; see
    `docs/slices/26-reference-cards-and-cheat-sheets.md`
27. Discussion and event records — **PROPOSED**; see
    `docs/slices/27-discussion-and-event-records.md`
28. Cross-links and backlinks — **PROPOSED**; see `docs/slices/28-cross-links-and-backlinks.md`
29. Recall practice for reference cards — **PROPOSED**; see
    `docs/slices/29-recall-practice-for-reference-cards.md`
30. Issues: topic hubs for social issues — **PROPOSED**; see `docs/slices/30-issues-topic-hubs.md`
31. Community solidarity needs — **PROPOSED**; see
    `docs/slices/31-community-solidarity-needs.md`
32. Planning and strategic thinking — **PROPOSED**; see
    `docs/slices/32-planning-and-strategic-thinking.md`

Slice 27 follows 26 because it reuses its tag and client conventions, and 28 needs both. Slice 29
needs only 26, and Slices 30 and 31 could be ordered ahead of it if community needs are the more
urgent use (see the open questions in `docs/intended-use.md`).

### Phase G — Document, agent and automation expansion

Previously the rest of Phase E, now deferred behind Phase F. Contracts first, in dependency order,
each with its own threat and authorization review:

23. Document generation — first use is printing cheat sheets (Slice 26); see
    `docs/slices/23-document-generation.md`
24. AI agent integration — records and needs are excluded from agent access unless granted per
    resource; see `docs/slices/24-ai-agent-integration.md`
25. Huginn orchestration — see `docs/slices/25-huginn-orchestration.md`

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

## Post-MVP sequence

Work after Slice 14 is organized into phases. Each numbered slice has a contract in
`docs/slices/`; a contract marked PROPOSED has not been implemented.

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
16. Notebooks, NotebookSections and Libraries in the client
17. Relationships, objectives, lifecycle and activity history in the client

Each follows the Slice 14 rule: no new domain rules, no new endpoints, no new authorization.

### Phase C — Collaboration surfaces

18. Capability grant/revoke and cross-context request inbox, plus an ADR deciding whether activity
    history stays actor-only once resources are genuinely shared

### Phase D — Account and operations maturity

19. Account lifecycle and session security (invitation, password change and reset, rate limiting)
20. Operational maturity (dashboard query performance, structured logging, observability)
21. Data export and account data portability

### Phase E — Post-MVP domain expansion

Contracts first, in dependency order, each with its own threat and authorization review:

22. ResearchCollection foundation
23. Document generation
24. AI agent integration
25. Huginn orchestration

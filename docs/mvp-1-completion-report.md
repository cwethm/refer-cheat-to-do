# MVP 1 Completion Report

Status: **READY**

## Slice status

| Slice | Document | Decision |
| --- | --- | --- |
| 1 — Authentication and user workspace | `docs/slices/1-authentication-and-user-workspace.md` | APPROVED |
| 2 — ToDo capture and Inbox | `docs/slices/2-todo-capture-and-inbox.md` | APPROVED |
| 3 — Tags, search and filtering | `docs/slices/3-tags-search-and-filtering.md` | APPROVED |
| 4 — Projects and ProjectSections | `docs/slices/4-projects-and-project-sections.md` | APPROVED |
| 5 — Notebooks and NotebookSections | `docs/slices/5-notebooks-and-notebook-sections.md` | APPROVED |
| 6 — Lifecycle, Archive, Trash, Restore | `docs/slices/6-lifecycle-archive-trash-restore.md` | APPROVED |
| 7 — Review scheduling and resurfacing | `docs/slices/7-review-scheduling-and-resurfacing.md` | APPROVED |
| 8 — Relationships and terminal objectives | `docs/slices/8-relationships-and-objectives.md` | APPROVED |
| 9 — Libraries | `docs/slices/9-libraries.md` | APPROVED |
| 10 — Capability and permission model | `docs/slices/10-capability-and-permission-model.md` | APPROVED |
| 11 — Cross-context requests and callbacks | `docs/slices/11-cross-context-requests-and-callbacks.md` | APPROVED |
| 12 — Activity history and audit foundation | `docs/slices/12-activity-history-and-audit-foundation.md` | APPROVED |
| 13 — Dashboard and MVP integration polish | `docs/slices/13-dashboard-and-mvp-integration-polish.md` | APPROVED |

## Release verification results

```text
PHP lint      — clean
PHPCS         — no errors
PHPStan       — [OK] No errors
PHPUnit       — 609 tests, 1620 assertions, OK
Migrations    — full chain rolled back to 0 and reapplied; all 12 migrations `up`
```

The complete gate is reproducible with `composer check`.

## 3.1 Functional matrix

Every listed capability is exercised by the suite:

| Capability | Primary coverage |
| --- | --- |
| Authentication | `AuthControllerTest`, anonymous denial across `MvpJourneyTest` |
| ToDo create/edit/view | `TodosControllerTest`, Journey A/B |
| Tags | `TagsControllerTest`, `TagsTableTest`, `TodosTagsTableTest` |
| Search and filtering | `TodosControllerTest`, Journey A |
| Projects / ProjectSections | `ProjectsControllerTest`, `ProjectSectionsTableTest`, Journey B |
| Notebooks / NotebookSections | `NotebooksControllerTest`, `NotebookSectionsTableTest`, Journey B |
| Lifecycle, Archive, Trash, restore, permanent deletion | `TodoLifecycleServiceTest`, Journey D, `MvpReleaseVerificationTest` |
| Review scheduling, orphan detection, review queue | `ReviewSchedulingServiceTest`, `OrphanDetectionServiceTest`, Journey C |
| Related ToDos, parent/child, terminal objectives | `TodoRelationshipServiceTest`, `MvpReleaseVerificationTest` |
| Libraries | `LibraryMembershipServiceTest`, `LibrariesControllerTest`, Journey E |
| Capabilities | `CapabilityServiceTest`, `CapabilitiesControllerTest` |
| Cross-context requests and callbacks | `CrossContextRequestServiceTest`, `CrossContextRequestsControllerTest`, Journey F |
| Activity history | `ActivityRecorderTest`, `ActivityControllerTest` |
| Dashboard | `DashboardControllerTest` |

## 3.2 Authorization matrix

- **Anonymous** — denied (`401`) on every protected collection and mutation; covered by the
  parameterized `testAnonymousAccessIsDenied` sweep and per-slice anonymous tests.
- **Owner** — allowed on their own resources throughout.
- **Different user** — every read, edit, archive, trash, restore, delete, link, assign and membership
  attempt against another user's resource returns `404`; covered by the parameterized
  `testCrossUserResourceAccessIsDenied` sweep plus per-slice cross-user tests.
- **Authorized cross-context actor** — may send, resolve and call back exactly as granted
  (Journey F).
- **Unauthorized cross-context actor** — refused; and revoking the grant immediately withdraws the
  ability (Journey F, `testCrossContextMutationWithoutCapabilityIsRejected`).
- **Permission management** — self-escalation on another user's resource is refused
  (`testCapabilityEscalationIsRejected`); `CapabilityServiceTest` includes a full 12×12
  non-implication matrix proving no capability implies another.

## 3.3 Destructive-action matrix

Verified by `MvpReleaseVerificationTest` and Journeys D/E:
Trash precedes permanent deletion (`409` otherwise); permanent deletion has its own explicit
endpoint and is unreachable through the update endpoint; restoring retains tags and section
assignment; deleting a Library deletes neither Projects nor Notebooks; deleting a Tag deletes no
ToDos; removing a relationship deletes neither ToDo; permanently deleting a ToDo deletes neither its
Tags nor its relatives; repeating a destructive action returns `404` rather than partially applying.

## 3.4 Data-integrity matrix

Foreign keys, unique constraints and join-row cascades are asserted directly in PostgreSQL during
each slice's migration verification, and behaviourally through the API: duplicate Tag attachment and
duplicate Library membership return `409`; cross-user ownership references are refused; parent cycles
return `409`; capability grants cannot be self-issued (enforced in both the service and a database
CHECK); acceptance of a cross-context request is transactional.

## 3.5 Review-engine matrix

`OrphanDetectionServiceTest` and `ReviewSchedulingServiceTest` cover no-tags, no-section, overdue,
not-yet-due, stale, exact due boundary, multiple reasons and reason removal after organization;
archived and trashed exclusion is covered there and again in the dashboard counts; snooze and its
effect on the due list is covered by Journey C.

## 3.6 Shared-module regression matrix

| Shared module | Direct tests | Consumer tests |
| --- | --- | --- |
| `CapabilityService` | `CapabilityServiceTest` | Projects, Notebooks, Libraries, cross-context requests, Journey F |
| `ReviewSchedulingService` | `ReviewSchedulingServiceTest` | review queue, dashboard counts, Journey C |
| `ActivityRecorder` | `ActivityRecorderTest` | lifecycle, tags, requests, permission changes (`ActivityControllerTest`) |
| Ordered section logic | `SectionOrderingBehaviorTest` | `ProjectSectionsTableTest`, `NotebookSectionsTableTest` |
| `LibraryMembershipService` | `LibraryMembershipServiceTest` | `LibrariesControllerTest`, Journey E |
| `TodoLifecycleService` | `TodoLifecycleServiceTest` | Todos API, dashboard, Journey D |

## Bloat review

No class was added with only a trivial caller; no interface was introduced with a single
implementation; no factory, registry or plugin layer exists; no framework feature was wrapped without
domain value; no column or table was added for a future feature; no dependency was added during
Slices 9-13. Each service introduced (`LibraryMembershipService`, `CapabilityService`,
`CrossContextRequestService`, `ActivityRecorder`, `DashboardService`) is the single authority for its
rule and has multiple consumers.

## Reuse review

**Existing code reused** — `Api\AppController` (`respond`, `requireUserId`, `capabilities`,
`activity`), `ApiExceptionRenderer`, the `withTags` finder, `SectionOrderingBehavior`, and every
prior slice's service.
**New shared code** — `ActivityRecorder` and `DashboardService`, both cross-cutting by definition.
**Intentionally local** — per-controller serializers and query-parameter readers, kept local because
their shapes differ per resource.
**Defense-in-depth duplication** — ownership is checked in the controller *and* enforced by database
foreign keys; uniqueness is checked in the application *and* by database unique constraints;
capability self-grant is blocked in the service *and* by a database CHECK. These duplications are
intentional.

## Known limitations

- Activity is visible only to the actor who produced it.
- Dashboard orphan reasoning iterates the user's non-hidden ToDos in PHP.
- Accessibility verification is deferred until a UI surface exists; the repository is API-only.

## Decision

MVP 1: **READY**

Every required slice is APPROVED and the complete release verification passes. No blocking security,
data-integrity, architecture, scope or regression issue remains. Post-MVP work (ResearchCollection,
AI agents, Huginn orchestration, document generation) is explicitly **not** started.

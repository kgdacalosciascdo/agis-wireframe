# ARMIS Workflow and Implementation Checkpoint

## Current implementation checkpoint

ARMIS-1A/1B, ARMIS-2A/2B, ARMIS-3A/3B, ARMIS-4A, ARMIS-4B, and ARMIS-5A are verified. ARMIS-3A
provides the backend planning ledger for availability, annual capacity,
planned workload, and utilization; ARMIS-3B provides its protected React
workspace. ARMIS-4A provides separate assignment and actual-person-day
ledgers with conflict and capacity rules. The existing
`InterimIapResourcePlanningGateway` remains the only
provider used by AEMS; ARMIS is not authoritative and no IAP records were
migrated or changed.

## ARMIS-0 scope

ARMIS-0 established the as-built boundary. ARMIS-1A adds the backend foundation
for the resource registry and its governed planning ledger. It does not change
IAP or AEMS workflow behavior, switch the provider, or start AIS integration.

## Verified as-built matrix

| Area | Current state | Evidence |
| --- | --- | --- |
| Navigation | ARMIS exposes Resource Registry, Competencies & Certifications, Planning & Utilization, and Assignments & Actuals pages | `src/config/navigation.js` |
| Workspace/API | Protected resource, competency, planning, and assignment/actuals React workspaces consume dedicated ARMIS APIs | `src/App.jsx`, `backend/routes/api.php` |
| Permissions | Granular `armis.*` permissions govern registry, competency, planning, actuals, reports, and exports; compatibility permissions remain | `RolePermissionSeeder` |
| Resource data | Temporary capacity, unavailability, skills, and IAP requirements are stored by IAP | IAP resource migrations/models/controller |
| AEMS consumption | AEMS reads a replaceable `ResourcePlanningGateway` | `ResourcePlanningGateway`, `InterimIapResourcePlanningGateway` |
| Provider mode | `IAP_INTERIM_FALLBACK`, available but non-authoritative; ARMIS-4A writes remain outside the AEMS provider | integration status service and boundary tests |
| Reports/API | ARMIS-5A provides immutable scope-pinned report runs, protected CSV/PDF exports, private downloads, administration status, and hardening flags | `ArmisReportService`, `ArmisReportController`, `armis_report_runs`, `armis_report_exports` |
| Tests | ARMIS foundation, competency, planning, assignment, report, resource, and desktop/mobile workspace tests protect the module and interim provider boundary | `backend/tests/Feature/Armis*Test.php`, `tests/e2e/armis-*.spec.js` |

### Fully implemented before ARMIS-1A

The IAP interim resource boundary is implemented and consumed by AEMS for
capacity, skills, unavailability, planned requirements, and warning-only team
validation. Integration status makes the provider and authority explicit.

### ARMIS-1A implemented

ARMIS now has a separate, scope-aware foundation for resource profiles,
competencies, availability periods, capacity submissions, requirements,
workload allocations, actual person-day versions, and immutable workflow event
timelines. Profiles link to Core users and offices. Competency evidence links
to an exact immutable Core `document_versions` record.

The profile registry API is protected by granular `armis.resource.*`
permissions and supports optimistic locking, draft-to-active lifecycle
transitions, soft archive/restore, office scope filtering, Activity Log,
Audit Trail, and ARMIS workflow events. ARMIS records are intentionally not
read by AEMS yet; the existing `ResourcePlanningGateway` remains bound to the
IAP interim provider.

### Partially implemented

Temporary IAP resource records support AEMS planning but are not a complete
resource registry. They have no ARMIS-owned lifecycle, independent approval,
versioned actuals, utilization reporting, or ARMIS scope model.

### Missing

The ARMIS reports React workspace and ARMIS-owned provider remain future phases.
ARMIS-3A/3B, ARMIS-4A/4B, and ARMIS-5A provide planning, assignment, actuals,
conflict, notifications, administration status, protected report snapshots,
private exports, and audit records but do not change the provider or IAP/AEMS
authority boundary.

## Existing interim data and ownership

The following IAP tables must remain intact for historical plans and backward
compatibility:

- `iap_auditor_capacities`
- `iap_auditor_unavailability`
- `iap_auditor_skills`
- `iap_engagement_skill_requirements`
- planned IAP team allocations

AEMS engagement and assignment actual person-days remain in AEMS tables.
ARMIS-4A adds a separate versioned assignment and actual-person-day ledger; it
does not migrate, rename, remove, or overwrite those records.

## Current provider contract

`ResourcePlanningGateway` is the stable read boundary used by AEMS:

```text
capacityFor
engagementActualPersonDays
assignmentActualPersonDays
skills
requirements
unavailability
status
```

`InterimIapResourcePlanningGateway` is the current binding. A future ARMIS
adapter must implement this read contract without changing AEMS consumers.
ARMIS writes and approvals should use separate authorized services; the read
gateway must not become an untracked write path.

## ARMIS-1A design checkpoint

The next phase is the backend foundation only. It should establish normalized,
scope-aware, soft-deletable and auditable tables for:

1. resource profiles linked to Core users and offices;
2. competency catalogue links and verification evidence;
3. availability periods and capacity submissions;
4. workload allocations and resource requirements;
5. actual person-day submissions and immutable approved versions;
6. workflow events, activity/audit records, and notifications.

Profile lifecycle is `Draft -> Active -> Suspended -> Inactive -> Archived`.
Capacity and actuals schemas provide independent submit/review/approve states
and version lineage; their operational review services are reserved for the
later ARMIS phases. Approved versions are designed to become immutable and
corrections must create a new version.

### ARMIS-1A API routes

- `GET /api/armis/metadata`
- `GET /api/armis/resources`
- `POST /api/armis/resources`
- `GET /api/armis/resources/{profile}`
- `GET /api/armis/resources/{profile}/events`
- `PUT /api/armis/resources/{profile}`
- `POST /api/armis/resources/{profile}/transition`
- `POST /api/armis/resources/{profile}/restore`

## Permissions and separation of duties

ARMIS-1A introduces granular `armis.*` permissions for resource,
competency, availability, capacity, workload, actuals, reports, and exports.
The existing `arms.view` and `arms.manage` compatibility permissions must be
preserved. Resource maintainers must not approve their own submissions;
independent reviewers approve verified data. Auditees have no ARMIS write
access, and technical administrators do not receive professional approval by
default.

## Integration sequence

1. Build ARMIS records and APIs without changing IAP/AEMS behavior.
2. Reference approved IAP demand with source and snapshot lineage.
3. Add an ARMIS adapter implementing the existing read gateway.
4. Expose interim, shadow, and authoritative provider modes.
5. Reconcile ARMIS and IAP results before any authority switch.
6. Transfer actual-person-day ownership only through an explicit, tested gate.

CMS remains independent. AIS integration is out of scope until ARMIS is stable
and authoritative.

## ARMIS roadmap

- ARMIS-1A: backend foundation, schema, permissions, authorization, audit, and APIs.
- ARMIS-1B: resource registry and detail workspace.
- ARMIS-2A/2B: competency and certification backend/workspace.
- ARMIS-3A: availability, capacity, workload, utilization backend, revision
  lineage, independent review, locking, notifications, and audit records.
- ARMIS-3B: availability, capacity, workload, calendar, and utilization React
  workspace at `/audit-resource-management/planning`, with responsive
  utilization cards, availability calendar, and permission-aware workflow
  actions.
- ARMIS-4A: assignment and actual-person-day backend, conflicts, capacity rules,
  approvals, revisions, and audit/notification controls.
- ARMIS-4B: assignment and actuals React workspace.
- ARMIS-5A: immutable scope-aware reports, protected CSV/PDF exports, notification
  and administration status, checksum controls, and operational hardening.
- ARMIS-5B: ARMIS reports and administration React workspace.
- ARMIS-6: IAP/AEMS adapter, shadow reconciliation, authority switch, and rollback verification.

## ARMIS-1A acceptance

ARMIS-1A is complete when its schema, models, scoped profile API, lifecycle,
optimistic locking, compatibility permissions, audit/event records, tests, and
documentation are verified. Later planning and actuals phases remain
separate; no provider authority switch or AIS integration is part of ARMIS-1A.

## ARMIS-1B frontend checkpoint

ARMIS-1B adds the protected React Resource Registry at
`/audit-resource-management/resources` and the resource detail route at
`/audit-resource-management/resources/{profileId}`. It consumes only the
ARMIS-1A contracts, supports scope-safe search/status filtering, draft profile
creation, optimistic-lock profile editing, lifecycle actions, archive/restore,
and an immutable workflow timeline. The workspace displays the interim provider
warning and does not present ARMIS as authoritative for AEMS.

## ARMIS-2A competency and certification backend checkpoint

ARMIS-2A operationalizes the existing `armis_competencies` foundation table as
a controlled certification ledger. Competencies are selected from the Core
`IAP_AUDITOR_SPECIALIZATION` catalogue (or a future `ARMIS_COMPETENCY`
catalogue) and certification evidence must reference an exact active Core
`document_versions` record. The selected document version remains immutable;
ARMIS stores only its foreign-key identity and evidence metadata.

The competency lifecycle is:

```text
Draft / Returned
    -> Pending Verification
    -> Verified
    -> Expired or Revoked (controlled reviewer actions)
```

Verified records are not edited in place. Corrections use a new revision in
the same competency family, link `supersedes_id` to the prior version, mark
the prior row non-current, and return the new version to Draft. The database
enforces one current revision for a resource and Core competency catalogue
item, while the service enforces the same rule inside a row-locking
transaction.

ARMIS-2A API routes:

- `GET /api/armis/competencies/metadata`
- `GET /api/armis/competencies`
- `POST /api/armis/competencies`
- `GET /api/armis/competencies/{competency}`
- `GET /api/armis/competencies/{competency}/events`
- `PUT /api/armis/competencies/{competency}` (Draft or Returned only)
- `POST /api/armis/competencies/{competency}/submit`
- `POST /api/armis/competencies/{competency}/review`
- `POST /api/armis/competencies/{competency}/revisions`

`armis.competency.manage` covers preparation, draft maintenance, submission,
and correction requests. `armis.competency.verify` is separate and is required
for Verify, Return, and Revoke decisions. A submitter or resource owner cannot
perform the independent review. Each mutation records an Activity Log, Audit
Trail entry, and immutable ARMIS workflow event; submission and review changes
also generate deduplicated in-app notifications through Core.

ARMIS-2A does not add a React workspace. Competency and certification screens
are the ARMIS-2B scope. Availability, capacity, workload, actuals, provider
switching, and AIS integration remain deferred.

## ARMIS-2B competency workspace checkpoint

ARMIS-2B adds the protected React workspace at
`/audit-resource-management/competencies` and the detail route at
`/audit-resource-management/competencies/{competencyId}`. The registry is
office-scoped through the ARMIS-2A API and provides search, status filtering,
current-claim metrics, resource and Core catalogue selection, credential
metadata, exact Core Document Version evidence references, and immutable
review history.

The detail workspace exposes only backend-authorized actions: Draft/Returned
editing, submission for verification, independent Verify/Return/Revoke
decisions, and correction revision creation from a Verified record. The UI
does not fabricate evidence downloads or bypass Core document authorization;
it displays the exact Core Document Version identity and checksum metadata
returned by the backend. Desktop and mobile browser coverage protects registry
rendering, detail history, draft creation, submission, and independent
verification.

## ARMIS-3B planning workspace checkpoint

ARMIS-3B adds the protected React workspace at
`/audit-resource-management/planning`. One responsive workspace presents four
operational views:

- Overview/Utilization for fiscal-year capacity, approved workload, remaining
  days, utilization percentage, and over-capacity indicators;
- Availability Calendar for current scope-aware periods and their workflow
  statuses;
- Capacity for annual capacity submissions and immutable version actions;
- Workload for planned allocations, source lineage, and capacity-aware review.

The workspace consumes only the ARMIS-3A APIs. It offers create/edit for Draft
and Returned records, submission, independent Approve/Return review, locking,
and correction revisions only when the signed-in user has the corresponding
granular permission. Approved and Locked records are presented as immutable.
Fiscal-year and status filters, search, responsive tables, and hoverable
summary cards are included for desktop and mobile use. The provider boundary is
explicitly shown as `IAP_INTERIM_FALLBACK`; ARMIS-3B does not expose actual
person-days, switch AEMS to ARMIS, or create public document URLs.

Focused browser coverage protects both desktop and mobile rendering and the
capacity draft/submission flow in `tests/e2e/armis-planning.spec.js`.

## ARMIS-4A assignment and actuals backend checkpoint

ARMIS-4A operationalizes the previously reserved actuals foundation and adds
the separate `armis_engagement_assignments` and
`armis_assignment_competencies` ledgers. Assignment revisions are linked to an
AEMS engagement and an ARMIS resource profile. Required competencies are
stored as an immutable snapshot on each assignment revision and can also be
copied from an AEMS-scoped ARMIS resource requirement.

Assignments use `DRAFT -> SUBMITTED -> RETURNED/APPROVED -> LOCKED`. Draft and
Returned assignments accept optimistic-lock updates. Approved and Locked
assignments cannot be edited in place; corrections create a new Draft revision
with `supersedes_id` and the previous competency snapshot preserved.

The existing `armis_actual_person_days` table now has assignment lineage,
version/current-revision guards, variance reasons, and creator/updater audit
metadata. Actuals use the same independent review and locking workflow.
Recording requires an approved or locked assignment. Actual periods must be
inside the assignment dates. A variance reason is required when cumulative
actual person-days exceed the approved assignment plan.

Submission and approval enforce these hard rules:

1. the resource profile and Core user are active;
2. the resource office is covered by the engagement offices;
3. a resource cannot have overlapping current ARMIS assignments;
4. approved or locked availability conflicts block assignment approval;
5. an approved ARMIS capacity version is required and cannot be exceeded;
6. engagement planned person-days cannot be exceeded by approved assignments;
7. every required competency must have a current verified ARMIS claim at the
   required proficiency; and
8. submitters and resource owners cannot independently review or approve their
   own records.

The APIs are protected by `armis.assignment.*` and existing
`armis.actuals.*` permissions. Mutations create ARMIS workflow events, Core
Activity Log entries, Audit Trail entries, and after-commit review/outcome
notifications. The ARMIS-4A ledger remains outside the AEMS
`ResourcePlanningGateway`; AEMS continues to use `IAP_INTERIM_FALLBACK` and no
IAP/AEMS records are migrated or overwritten.

## ARMIS-4B assignment and actuals React checkpoint

The protected `/audit-resource-management/assignments` workspace consumes the
ARMIS-4A assignment and actuals APIs. It is a dedicated page beside Planning &
Utilization so availability, annual capacity, workload, assignments, and
actual person-days do not become one overloaded registry.

The workspace provides responsive Overview, Assignments, and Actual
Person-Days sections. It supports permission-aware Draft creation, editing of
Draft/Returned records, submission, independent Approve/Return review, locking,
correction revisions, current conflict inspection, search, status filtering,
and empty/loading/retry states. Assignment forms use the authorized AEMS
engagement list when available and retain a safe ID fallback for ARMIS-only
roles. Required competency snapshots are selectable from Core catalogue and
requirement data; the backend remains authoritative for eligibility.

Actuals can only be recorded against an approved or locked assignment. The UI
shows planned versus actual days and explains the required variance reason when
actuals exceed the assignment plan. Historical approved and locked versions
remain read-only; corrections invoke the backend revision endpoints.

ARMIS-4B does not modify AEMS team rows, switch the AEMS provider, expose
public URLs, or make professional conflict/capacity decisions in the browser.
Focused browser coverage belongs in `tests/e2e/armis-assignments.spec.js` and
the existing ARMIS-4A backend regression remains the authoritative workflow
guard.

## ARMIS-5A reports and operational hardening checkpoint

ARMIS-5A adds a backend-only report and administration boundary. The protected
report catalog contains Resource Utilization, Assignment Register, Capacity and
Workload, and Competency Coverage reports. Each generation stores the visible
resource/assignment identifiers, filters, source-query version, result snapshot,
row count, and SHA-256 checksum in an immutable `armis_report_runs` record.

The protected routes are:

- `GET /api/armis/reports`
- `GET /api/armis/reports/runs`
- `GET /api/armis/reports/runs/{run}`
- `POST /api/armis/reports/{report}/generate`
- `POST /api/armis/reports/runs/{run}/exports`
- `GET /api/armis/report-exports/{export}/download`
- `GET /api/armis/administration`

Report viewing requires `armis.report.view`; CSV/PDF generation and download
require `armis.report.export`. CSV values beginning with spreadsheet formula
characters are prefixed defensively. PDF and CSV files are stored on the
private local disk, expose no public storage URL, preserve file size and
checksum metadata, and are re-used idempotently when the original artifact is
available. Report runs and exports cannot be updated or deleted.

The administration contract reports the current office/engagement scope,
available ARMIS permissions, workflow status families, ARMIS notification
counts, provider mode, and hardening guarantees. Existing ARMIS planning,
assignment, actuals, competency, and review notifications remain in-app Core
notifications; no automatic professional decision or provider authority switch
is introduced.

## ARMIS-5B reports and administration React checkpoint

ARMIS-5B adds the protected React workspace at
`/audit-resource-management/reports`. The Reports tab consumes the ARMIS-5A
catalog, generates backend snapshots with supported search/status/office/fiscal
year filters, displays immutable run history and checksums, and creates or
downloads protected CSV/PDF artifacts only when `armis.report.export` is
granted. Downloads use authenticated endpoints; the browser never receives a
public storage URL.

The Administration tab consumes `GET /api/armis/administration` and presents
the interim provider warning, workflow status families, permission contract,
notification counts, authorized scope, and hardening flags. It is a read-only
operational view; it cannot switch providers, approve assignments, change
workflow decisions, or alter report snapshots. The page is responsive and
keeps report tables horizontally scrollable on narrow screens.

Focused browser coverage is in `tests/e2e/armis-reports.spec.js`. ARMIS-5B
does not switch the AEMS `ResourcePlanningGateway`; AIS integration, provider
authority, shadow reconciliation, rollback, and future ARMIS-owned actuals
remain ARMIS-6 scope.

# ARMIS Workflow and Implementation Checkpoint

## ARMIS-0 scope

ARMIS-0 established the as-built boundary. ARMIS-1A adds the backend foundation
for the resource registry and its governed planning ledger. It does not change
IAP or AEMS workflow behavior, switch the provider, or start AIS integration.

## Verified as-built matrix

| Area | Current state | Evidence |
| --- | --- | --- |
| Navigation | A module card points to the generic `/audit-resource-management` route | `src/config/navigation.js` |
| Workspace/API | No dedicated ARMIS React workspace, operational page, or API routes | `src/App.jsx`, `backend/routes/api.php` |
| Permissions | Only compatibility permissions `arms.view` and `arms.manage` exist; no `armis.*` catalogue | `RolePermissionSeeder` |
| Resource data | Temporary capacity, unavailability, skills, and IAP requirements are stored by IAP | IAP resource migrations/models/controller |
| AEMS consumption | AEMS reads a replaceable `ResourcePlanningGateway` | `ResourcePlanningGateway`, `InterimIapResourcePlanningGateway` |
| Provider mode | `IAP_INTERIM_FALLBACK`, available but non-authoritative; actuals remain AEMS-owned | integration status service and boundary tests |
| Tests | Boundary and dashboard tests protect the interim provider contract; no ARMIS module tests exist | `AemsIntegrationBoundaryTest`, `AemsDashboardTest` |

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

ARMIS competency, availability, capacity, workload, and actuals approval
services and React pages remain future phases. Reports, notifications,
ARMIS-owned provider, and the IAP/AEMS authority switch are also not
implemented.

## Existing interim data and ownership

The following IAP tables must remain intact for historical plans and backward
compatibility:

- `iap_auditor_capacities`
- `iap_auditor_unavailability`
- `iap_auditor_skills`
- `iap_engagement_skill_requirements`
- planned IAP team allocations

AEMS engagement and assignment actual person-days remain in AEMS tables. No
ARMIS-0 migration renames, removes, or copies these records.

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
- ARMIS-3A/3B: availability, capacity, workload, calendar, and utilization.
- ARMIS-4A/4B: allocations, actuals, approvals, and corrections.
- ARMIS-5: reports, notifications, administration, and operational hardening.
- ARMIS-6: IAP/AEMS adapter, shadow reconciliation, authority switch, and rollback verification.

## ARMIS-1A acceptance

ARMIS-1A is complete when its schema, models, scoped profile API, lifecycle,
optimistic locking, compatibility permissions, audit/event records, tests, and
documentation are verified. Competency, capacity, availability, workload, and
actuals review actions remain the next ARMIS phases; no provider authority
switch or AIS integration is part of ARMIS-1A.

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

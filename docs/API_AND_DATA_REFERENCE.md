# API and Data Reference

## 1. API conventions

Base path: `/api`.

Authentication uses Laravel Sanctum first-party SPA cookies. Mutating browser
requests obtain `/sanctum/csrf-cookie` before sending the request.

Standard JSON envelope:

```json
{
  "success": true,
  "message": "Optional human-readable result.",
  "data": {}
}
```

Validation error envelope:

```json
{
  "success": false,
  "message": "The submitted data is invalid.",
  "errors": {
    "field": ["Actionable validation message."]
  }
}
```

Identifiers in URLs are database IDs. Business codes such as `CIAS`,
`DOC-2026-00001`, or `IAP-2027` are returned in resource data and reports.

## 2. Public/session endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/health` | Database/application health |
| GET | `/runtime-configuration` | Safe public branding/display configuration |
| GET | `/demo-accounts` | Local demo accounts when enabled |
| POST | `/login` | Employee-ID/password authentication |
| GET | `/me` | Restore authenticated session |
| POST | `/logout` | End authenticated session |

## 3. Core endpoints

All entries below require `auth:sanctum`; each action also has its route
permission where applicable.

### 3.1 Profile

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/profile` | `profile.view` |
| PUT | `/profile` | `profile.update` |
| PUT | `/profile/password` | `profile.change_password` |

### 3.2 Offices, Audit Areas, and Audit Focuses

| Record | List | Create | Update | Archive | Restore |
| --- | --- | --- | --- | --- | --- |
| Office | `GET /offices` | `POST /offices` | `PUT /offices/{id}` | `DELETE /offices/{id}` | `POST /offices/{id}/restore` |
| Audit Area | `GET /audit-areas` | `POST /audit-areas` | `PUT /audit-areas/{id}` | `DELETE /audit-areas/{id}` | `POST /audit-areas/{id}/restore` |
| Audit Focus | `GET /audit-focuses` | `POST /audit-focuses` | `PUT /audit-focuses/{id}` | `DELETE /audit-focuses/{id}` | `POST /audit-focuses/{id}/restore` |

### 3.3 Users and account controls

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/users` | Permission-scoped user registry |
| GET | `/users/{id}` | User detail, effective roles, history |
| POST | `/users` | Create account |
| PUT | `/users/{id}` | Update identity/employment/roles |
| DELETE | `/users/{id}` | Archive |
| POST | `/users/{id}/activate` | Activate |
| POST | `/users/{id}/disable` | Disable |
| POST | `/users/{id}/lock` | Manual lock |
| POST | `/users/{id}/unlock` | Remove manual/temporary lock |
| POST | `/users/{id}/restore` | Restore archived account |
| PUT | `/users/{id}/password` | Administrative password reset |

### 3.4 Roles, permissions, and master lists

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/roles` | Roles, user count, scopes, permissions |
| POST | `/roles` | Create role |
| POST | `/roles/{id}/clone` | Clone role and permissions/scopes |
| PUT | `/roles/{id}` | Update role |
| DELETE | `/roles/{id}` | Archive unassigned role |
| POST | `/roles/{id}/restore` | Restore |
| GET | `/permissions` | Permission catalogue |
| GET | `/master-lists` | Configurable/reference lists |
| POST | `/master-lists` | Create a list |
| PUT | `/master-lists/{id}` | Update items/order/status |

### 3.5 Documents

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/documents` | Confidentiality-filtered repository |
| POST | `/documents` | Upload metadata and immutable version 1 |
| PUT | `/documents/{id}` | Update metadata, classification, links |
| POST | `/documents/{id}/versions` | Create immutable version |
| DELETE | `/documents/{id}` | Archive document |
| POST | `/documents/{id}/restore` | Restore document |
| GET | `/documents/{id}/download` | Download current authorized version |
| GET | `/documents/{id}/versions/{version}/download` | Download authorized historical version |

Create/update document fields use camelCase, including:

```text
documentTypeId
confidentialityLevelId
title
referenceNumber
issuingAuthority
publicationDate
version (create only)
description
isActive
file (create only)
links[]
```

### 3.6 Workflow Management

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/workflows` | Definitions and visible instances |
| GET | `/workflows/{id}` | Definition graph/detail |
| POST | `/workflows` | Create draft definition |
| PUT | `/workflows/{id}` | Update draft graph |
| POST | `/workflows/{id}/publish` | Publish and retire previous family version |
| POST | `/workflows/{id}/revisions` | Create draft revision |
| DELETE | `/workflows/{id}` | Archive eligible definition |
| POST | `/workflows/{id}/restore` | Restore |
| POST | `/workflow-instances` | Start explicit or module-default workflow |
| GET | `/workflow-instances/{id}` | Instance and event history |
| POST | `/workflow-instances/{id}/transitions/{action}` | Perform transition |
| POST | `/workflow-instances/{id}/cancel` | Cancel with history |

### 3.7 Notifications

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/notifications` | Inbox with filters |
| GET | `/notifications/recent` | Header badge/recent list |
| POST | `/notifications` | Authorized targeted delivery |
| POST | `/notifications/read-all` | Mark all read |
| PUT | `/notifications/preferences` | Update delivery preferences |
| POST | `/notifications/{id}/read` | Mark read |
| POST | `/notifications/{id}/unread` | Mark unread |
| DELETE | `/notifications/{id}` | Archive |
| POST | `/notifications/{id}/restore` | Restore |

### 3.8 Configuration and logs

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/system-configurations` | Safe administrative setting values and constraints |
| PUT | `/system-configurations` | Validate, save, and apply settings |
| POST | `/system-configurations/logo` | Upload managed runtime logo |
| POST | `/system-configurations/test-email` | Send configuration test |
| GET | `/activity-logs` | Filtered operational history |
| GET | `/activity-logs/export` | Authorized activity export |
| GET | `/audit-logs` | Filtered data-change history |
| GET | `/audit-logs/export` | Authorized audit export |
| POST | `/record-views` | Permission-check and deduplicate detail-view activity |

## 4. IAP endpoints

### 4.1 Dashboard and reports

| Method | Endpoint |
| --- | --- |
| GET | `/iap/dashboard` |
| GET | `/iap/reports` |
| GET | `/iap/reports/{report}` |
| GET | `/iap/reports/{report}/export` |

### 4.2 Strategic plans

```text
GET    /iap/strategic-plans
POST   /iap/strategic-plans
GET    /iap/strategic-plans/{id}
PUT    /iap/strategic-plans/{id}
DELETE /iap/strategic-plans/{id}
POST   /iap/strategic-plans/{id}/restore
GET    /iap/strategic-plans/{id}/completeness
POST   /iap/strategic-plans/{id}/transitions/{action}
POST   /iap/strategic-plans/{id}/revisions
```

### 4.3 Audit Universe

```text
GET    /iap/audit-universe
POST   /iap/audit-universe
PUT    /iap/audit-universe/{id}
DELETE /iap/audit-universe/{id}
POST   /iap/audit-universe/{id}/restore
```

### 4.4 Risk periods and subject assessments

```text
GET    /iap/risk-periods
POST   /iap/risk-periods
GET    /iap/risk-periods/{period}
PUT    /iap/risk-periods/{period}
DELETE /iap/risk-periods/{period}
POST   /iap/risk-periods/{period}/restore
POST   /iap/risk-periods/{period}/transitions/{action}

POST   /iap/risk-periods/{period}/assessments
PUT    /iap/risk-periods/{period}/assessments/{assessment}
DELETE /iap/risk-periods/{period}/assessments/{assessment}
POST   /iap/risk-periods/{period}/assessments/{assessment}/restore
POST   /iap/risk-periods/{period}/assessments/{assessment}/evidence
GET    /iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}
DELETE /iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}
```

### 4.5 Prioritization

```text
GET    /iap/prioritizations
POST   /iap/prioritizations
GET    /iap/prioritizations/{id}
PUT    /iap/prioritizations/{id}
PUT    /iap/prioritizations/{id}/items/{item}
POST   /iap/prioritizations/{id}/transitions/{action}
DELETE /iap/prioritizations/{id}
POST   /iap/prioritizations/{id}/restore
```

### 4.6 Annual plans, risks, engagements, and support

```text
GET    /iap/plans
POST   /iap/plans
GET    /iap/plans/{plan}
PUT    /iap/plans/{plan}
DELETE /iap/plans/{plan}
POST   /iap/plans/{plan}/restore
GET    /iap/plans/{plan}/completeness
POST   /iap/plans/{plan}/transitions/{action}
POST   /iap/plans/{plan}/revisions

GET    /iap/plans/{plan}/risk-assessments
POST   /iap/plans/{plan}/risk-assessments
PUT    /iap/plans/{plan}/risk-assessments/{assessment}
DELETE /iap/plans/{plan}/risk-assessments/{assessment}
POST   /iap/plans/{plan}/risk-assessments/{assessment}/restore

POST   /iap/plans/{plan}/engagements
PUT    /iap/plans/{plan}/engagements/{engagement}
DELETE /iap/plans/{plan}/engagements/{engagement}
POST   /iap/plans/{plan}/engagements/{engagement}/restore
PUT    /iap/plans/{plan}/engagements/{engagement}/team
PUT    /iap/plans/{plan}/prioritization

GET    /iap/plans/{plan}/supporting-records
POST   /iap/plans/{plan}/attachments
GET    /iap/plans/{plan}/attachments/{attachment}/download
DELETE /iap/plans/{plan}/attachments/{attachment}
POST   /iap/plans/{plan}/attachments/{attachment}/restore
POST   /iap/plans/{plan}/comments
```

### 4.7 Scheduling and capacity

```text
GET  /iap/schedules
POST /iap/schedules/{engagement}/conflicts
PUT  /iap/schedules/{engagement}
POST /iap/schedules/{engagement}/cancel

GET    /iap/resources
PUT    /iap/resources/auditors/{user}/capacity
POST   /iap/resources/auditors/{user}/unavailability
PUT    /iap/resources/unavailability/{id}
DELETE /iap/resources/unavailability/{id}
POST   /iap/resources/unavailability/{id}/restore
PUT    /iap/resources/auditors/{user}/skills
PUT    /iap/resources/engagements/{engagement}/requirements
```

## 5. Common list query parameters

Where supported:

| Parameter | Type | Meaning |
| --- | --- | --- |
| `search` | string | Code/name/description/domain search |
| `status` | string | Domain status |
| `officeId` | integer | Office filter |
| `auditAreaId` | integer | Audit Area filter |
| `fiscalYear` | integer | Planning year |
| `includeArchived` or `include_archived` | boolean | Include recoverable records if authorized |
| `sortBy` | string | Whitelisted sortable column |
| `sortDirection` | `asc`/`desc` | Sort direction |
| `page` | integer | Page number |
| `perPage` | integer | Page size within enforced bounds |

## 6. Core data model

| Entity | Main relationships |
| --- | --- |
| `User` | belongs to Office; has primary legacy Role and many assigned Roles; has notifications/logs/IAP assignments |
| `Role` | many Users and Permissions; stores office and engagement scopes |
| `Permission` | belongs to many Roles; stable module/action code |
| `Office` | has Users; many Audit Areas; independent, no parent |
| `AuditArea` | optional parent/children; responsible Office; many covered Offices; many Audit Focuses |
| `AuditFocus` | belongs to exactly one Audit Area |
| `MasterList` | has ordered, soft-deletable MasterListItems |
| `SystemConfiguration` | key/value/type/group; updated by User |
| `Document` | type/confidentiality items; versions; current version; module links; uploader/updater |
| `DocumentVersion` | belongs to Document; immutable stored file metadata/checksum |
| `DocumentLink` | belongs to Document; typed module record reference |
| `WorkflowDefinition` | versioned family with Steps, Transitions, and Instances |
| `WorkflowInstance` | pinned definition/current step; immutable Events |
| `SystemNotification` | recipient/actor, subject/deep link, read/archive state |
| `NotificationPreference` | one per User |
| `ActivityLog` | operational event and metadata |
| `AuditLog` | polymorphic old/new value history |

## 7. IAP data model

| Entity | Main relationships |
| --- | --- |
| `StrategicInternalAuditPlan` | objectives, priorities/themes, workflow events, revision lineage |
| `SiapObjective` | belongs to SIAP; maps to Audit Areas |
| `SiapPriority` | belongs to SIAP |
| `IapAuditUniverseItem` | responsible Office, primary Audit Area, stakeholder Offices, history |
| `IapRiskPeriod` | criteria, assessments, period events |
| `IapUniverseRiskAssessment` | period + universe item; scores, evidence, risk levels |
| `IapRiskAssessment` | legacy annual-plan-scoped risk, scores, plan attachments, and engagement references |
| `IapPrioritizationRun` | source risk period; ranked Items and Events |
| `IapPrioritizationItem` | snapshot of subject, scores, rank, decision, and source IDs |
| `InternalAuditPlan` | fiscal-year revision; engagements, risks, attachments, comments, workflow events |
| `IapPlanEngagement` | plan, source item/assessment, coverage, team, skills, schedule |
| `IapEngagementTeamMember` | engagement + User + planned person-days/lead flag |
| `IapAuditorCapacity` | User/year capacity |
| `IapAuditorUnavailability` | User/date range/type |
| `IapAuditorSkill` | User/master skill |
| `IapEngagementSkillRequirement` | engagement/master skill requirement |
| `IapScheduleEvent` | immutable schedule old/new values and reason |
| `IapAttachment` | plan-owned supporting file |
| `IapComment` | plan-owned review/management comment |
| `IapWorkflowEvent` | immutable annual-plan transition |

Both `iap_risk_assessments` and `iap_universe_risk_assessments` remain active.
The former supports legacy annual-plan-local risk records and revisions; the
latter supports the current period/universe workflow, prioritization, newer
plan lineage, and AEMS source snapshots. They are not interchangeable, and
neither system is being migrated or removed as part of current maintenance.

## 8. AEMS data model

The AEMS database/model foundation, Engagement Registry, aggregate lifecycle,
Entry Conference, Audit Team, AEO, AEP,
Audit Program, Working Paper, Audit Evidence, Issue, Finding, Management
Response, Auditor Rejoinder, Recommendation, Exit Conference, and Audit Report
endpoints are implemented. The Engagement Tracker endpoint derives portfolio
metrics and per-engagement progress from those records.
`AemsEngagementTransitionService` now moves the parent through controlled
actions and cross-workflow gates to `CLOSURE_REVIEW`, and executes the guarded
atomic `CLOSED` transition for the implemented formal Completion Assessment and
Engagement Closure aggregate.
The full CMS case-management module remains pending. CMS-1 now provides a
hardened immutable intake foundation: every valid AEMS transfer creates one
source envelope, one separate operational case initialized in `TRANSFERRED`,
and one append-only intake event.

| Entity | Main relationships |
| --- | --- |
| `AuditEngagement` | optional approved IAP engagement source; offices, audit areas/focuses, team, AEO, AEP, Entry Conference, programs, working papers, evidence, issues, findings, conferences, reports, Completion Assessments, Closures, final index, retention, lessons, reopening requests, events |
| `EntryConference` | one official PGIAM record per engagement; schedule, briefing, notes, completion/waiver, and optimistic lock |
| `EntryConferenceParticipant` | internal, auditee, or external participant and attendance |
| `EntryConferenceMatter` | matter, materiality, disposition, responsibility, and due date |
| `EntryConferenceAgreement` | commitment, responsibility, due date, and status |
| `EntryConferenceAcknowledgement` | immutable actor/office/version acknowledgement or reservation |
| `EntryConferenceAttachment` | immutable link to one exact private Core DocumentVersion |
| `EngagementTeam` | engagement + User assignment, role, person-days, active dates |
| `EngagementTeamHistory` | append-only team assignment old/new values |
| `AuditEngagementOrder` | one active family per engagement; immutable versions |
| `AuditEngagementOrderVersion` | exact authority/scope/team snapshot and optional DocumentVersion |
| `AuditEngagementPlan` | one active family per engagement; programs and immutable versions |
| `AuditEngagementPlanVersion` | objectives, scope, methodology, criteria, dates, resources, risks |
| `AuditProgram` | engagement/AEP revision with ordered procedures |
| `AuditProgramProcedure` | assignee, target date, evidence expectation, completion/waiver |
| `WorkingPaper` | engagement/procedure family with reviewer and immutable versions |
| `WorkingPaperVersion` | objective, work performed, population/sample, result, conclusion, cross-references, and exact cited evidence versions |
| `AuditEvidence` | immutable version family pinned to Core DocumentVersion, checksum, type, source, custodian, and confidentiality; many working-paper versions/issues/findings |
| `AuditIssue` | engagement exception linked to working-paper versions and evidence; optional one Finding |
| `AuditFinding` | engagement/issue revision with criteria, condition, cause, effect, evidence, recommendations, responses |
| `AuditRecommendation` | finding + responsible office/target date + idempotent CMS transfer lineage |
| `ManagementResponse` | versioned auditee response to a communicated Finding |
| `AuditorRejoinder` | auditor disposition and response dialogue conclusion |
| `AemsDialogueAttachment` | immutable link from one response or rejoinder to an exact private Core DocumentVersion |
| `ExitConference` | engagement schedule, minutes, agreements, participants, acknowledgement |
| `ExitConferenceParticipant` | internal/external attendee and attendance state |
| `ExitConferenceAttachment` | immutable private Core DocumentVersion pinned to one conference |
| `ExitConferenceAcknowledgement` | immutable actor-, office-, date-, comment-, status-, and version-specific auditee acknowledgement |
| `AuditReport` | one active report family per engagement with confidentiality and immutable versions |
| `AuditReportVersion` | generated content/file snapshot with selected Findings and recipients |
| `ReportRecipient` | internal/external delivery and acknowledgement for an exact report version |
| `AuditReportReviewComment` | immutable reviewer action and comment pinned to one exact report version |
| `CmsRecommendation` | immutable AEMS source envelope created once from an eligible recommendation in the exact issued report version; preserves report checksum, confidentiality, risk, office, original target, actor/date, and complete JSON snapshot |
| `CmsRecommendationCase` | one-to-one operational root initialized in `TRANSFERRED`, with effective target date, lead office, creator/opened date, and optimistic lock |
| `CmsRecommendationAssignment` | non-deletable Compliance Monitor history; only one effective current monitor is supported per case in CMS-2A |
| `CmsRecommendationEvent` | append-only case history for intake and assignment changes |
| `EngagementEvent` | append-only action, actor, state, lock version, and old/new snapshots |
| `CompletionAssessment` | current/revision family evaluating 25 required completion criteria |
| `CompletionAssessmentItem` | criterion result, source, blocker, and elevated acceptance |
| `CompletionAssessmentVersion` | immutable assessment snapshot pinned to an exact private Core DocumentVersion |
| `EngagementClosure` | current/revision family, approval/closed snapshots, status, and exact Closure DocumentVersion |
| `EngagementClosureChecklistItem` | evaluated authoritative gate and source record/path |
| `EngagementClosureEvent` | append-only controlled Closure transition event |
| `EngagementDocumentIndexItem` | exact indexed source record/DocumentVersion, checksum/file health, inclusion or authorized exclusion |
| `EngagementRetentionRecord` | replaceable-provider custody, retention, permanent/disposition, and legal-hold snapshot |
| `EngagementLessonLearned` | confidential post-engagement improvement record separate from issued results |
| `EngagementReopenRequest` | written-authority exceptional reopening workflow and original closed snapshot |

Important database constraints:

- one non-cancelled, non-archived AEMS engagement per IAP plan engagement;
- planned engagements require an IAP source;
- PostgreSQL special engagements require separate authority metadata;
- engagement coverage uses explicit office, audit-area, and audit-focus pivots;
- a team user has at most one current assignment per engagement;
- issue-to-finding conversion is one-to-one;
- version and revision numbers are unique within their families;
- current program, evidence, finding, and response revisions are unique;
- Working Paper indexes are unique within an engagement;
- exact Working Paper version/evidence links cannot be duplicated;
- CMS transfer keys and source AEMS recommendation IDs cannot be duplicated;
- AEMS `cms_recommendation_id` is a restrictive foreign key after an
  orphan-pointer migration preflight;
- each CMS intake has exactly one operational case;
- each initial event uses a unique idempotency key, so retry cannot duplicate
  `INTAKE_CREATED`;
- immutable intake status is constrained to `TRANSFERRED`; operational CMS
  states belong to the separate case;
- PostgreSQL partial unique indexes enforce one current Compliance Monitor per
  case and prevent a duplicate current user/case/role assignment;
- assignment rows are ended, never deleted or overwritten.

### 8.0 CMS-2A backend and CMS-2B React workspace

CMS routes use the **operational `cms_recommendation_cases.id`** as the
`{recommendation}` identifier. Cases are created only by the AEMS transfer
boundary; there is no generic CMS create, update, or delete endpoint.

| Method | Endpoint | Permission | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/cms/dashboard` | `cms.dashboard.view` | Live aggregates for the actor's visible case population |
| `GET` | `/api/cms/recommendations` | `cms.recommendation.view` | Scoped search/filter/sort/paginated registry |
| `GET` | `/api/cms/recommendations/{recommendation}` | `cms.recommendation.view` | Safe case, intake, source-lineage, assignment, and event detail |
| `GET` | `/api/cms/recommendations/{recommendation}/assignments` | `cms.recommendation.view` | Assignment history and, for authorized CIAS Management, eligible monitor options |
| `POST` | `/api/cms/recommendations/{recommendation}/assignments` | `cms.recommendation.assign` | Assign or replace the Compliance Monitor |
| `POST` | `/api/cms/recommendations/{recommendation}/assignments/{assignment}/end` | `cms.recommendation.assign` | End the current assignment without deleting history |

Registry query parameters are `search`, `status`, `officeId`, `risk`,
`confidentiality`, `monitorId`, `assigned`, `hasTargetDate`, `overdue`,
`transferredFrom`, `transferredTo`, `targetFrom`, `targetTo`, `sortBy`,
`sortDirection`, `perPage`, and `page`. Sort fields are limited to
`recommendationCode`, `transferredAt`, `targetDate`, `responsibleOffice`,
`risk`, `status`, and `assignedMonitor`. Pagination totals and filter options
are computed after visibility and confidentiality scope.

Assignment payload:

```json
{
  "userId": 42,
  "reason": "Required when replacing the current monitor.",
  "effectiveFrom": "2026-08-01T08:00:00+08:00",
  "effectiveUntil": null,
  "lockVersion": 1
}
```

Ending requires `reason` and the latest case `lockVersion`. A stale lock,
invalid monitor, duplicate assignment, or current-state conflict returns the
standard `422` validation envelope. A scoped nonexistent/inaccessible case
returns `404`. Assignment creation returns `201`; reads and ending return
`200`.

List items contain the generated display code (`CMS-REC-` plus the zero-padded
case ID), current state and monitor, effective target, derived overdue flag,
source summary, risk, confidentiality, and responsible office. Detail adds the
immutable transfer envelope, exact AEMS engagement/finding/recommendation/report
lineage, checksum, original office/target snapshots, assignment history, and
visible event timeline. It never returns document storage paths or unrestricted
AEMS Working Papers/evidence.

Dashboard cards include total, transferred/open, assigned, unassigned,
overdue, no-target, current-month transfer, high-risk, and high-risk-overdue
counts, plus visible office/risk/confidentiality/monitor groups, recent
transfers, and oldest unresolved targets. `OVERDUE` is derived, not persisted.
Due-soon is reported unavailable until an approved runtime threshold exists.

CMS-2A adds:

- `cms.dashboard.view`;
- `cms.recommendation.view`;
- `cms.recommendation.assign`;
- `cms.recommendation.monitor`;
- `cms.administration.monitor`.

The six legacy `cms.*` compatibility permissions remain unchanged.
`cms.view` is only a temporary basic-inquiry alias inside the authoritative
scope and never bypasses office, assignment, role, confidentiality, account, or
record-state restrictions. Seeded CIAS Management receives portfolio view,
assignment, and monitoring. AGIS Users may monitor only assigned cases.
Auditee Representatives see their responsible-office cases subject to
confidentiality. Read Only Users remain office/assignment scoped. Platform and
AGIS administration do not receive professional assignment authority;
`cms.administration.monitor` alone cannot open operational records.

Compliance Monitor changes use case row locking and `lockVersion`, retain ended
rows, append one of `COMPLIANCE_MONITOR_ASSIGNED`,
`COMPLIANCE_MONITOR_REPLACED`, or
`COMPLIANCE_MONITOR_ASSIGNMENT_ENDED`, write Activity Log and Audit Trail
records, and notify affected monitors after commit. Assignment does not change
case status and grants no validation or closure authority.

The CMS-2B React workspace consumes these APIs without recreating backend
scope, overdue, eligibility, or workflow rules:

| Frontend route | Permission | Behavior |
| --- | --- | --- |
| `/compliance-management` | `cms.dashboard.view` | Redirects to the CMS dashboard |
| `/compliance-management/dashboard` | `cms.dashboard.view` | Live scoped dashboard with attention links and supported summaries |
| `/compliance-management/recommendations` | `cms.recommendation.view` | URL-backed, server-searched/filtered/sorted/paginated registry |
| `/compliance-management/recommendations/{caseId}` | `cms.recommendation.view` | Safe overview, source lineage, assignment history, and event timeline |

Users with `cms.recommendation.assign` see assign, replace, and end controls.
Eligible monitor options come only from the assignment endpoint. Mutations
include `lockVersion`, retain backend validation messages, disable repeat
submission, refresh after success, and reload after a stale-lock response.
The sidebar contains live CMS destinations but no static operational counts.
Due-soon remains explicitly unavailable pending an approved runtime threshold.

Corrective action plans, progress/evidence submission, validation, extensions,
escalation, closure, accepted risk, reopening, and CMS reports are not
implemented.

### 8.1 AEMS authorization contract

There are 88 `aems.<resource>.<action>` permissions, including
`aems.engagement.export` for the access-scoped Engagement Progress Report.
The lifecycle and Entry Conference additions are
`aems.engagement.transition`, `aems.entry-conference.view`,
`aems.entry-conference.manage`, `aems.entry-conference.acknowledge`, and
`aems.entry-conference.waive`.
Formal closure adds 21 granular Completion Assessment, Closure, document-index,
retention, and exceptional-reopening operations. The verified runtime
catalogue contains 198 permissions in total, including 11 `cms.*` permissions.
Administrators receive monitoring access but do not receive audit approval or
issuance permissions. CIAS Management has global audit authority. AGIS Users
require an active `engagement_teams` assignment, and the assignment role limits
the actions they may perform.

Collection endpoints must apply the corresponding model scope:

```php
AuditEngagement::query()->visibleTo($request->user());
AuditIssue::query()->visibleTo($request->user());
AuditFinding::query()->visibleTo($request->user());
AuditReport::query()->visibleTo($request->user());
WorkingPaper::query()->visibleTo($request->user());
AuditEvidence::query()->visibleTo($request->user());
```

Record and transition endpoints must also call `$this->authorize(...)`.
Currently registered policies cover `AuditEngagement`, `EntryConference`, `AuditEvidence`,
`AuditIssue`, `AuditFinding`, `WorkingPaper`, `ExitConference`, and
`AuditReport`.

Auditee Representatives receive only communicated findings for their office,
covered exit-conference records, and issued reports addressed to their user or
office. Read-Only Users receive only issued reports naming their user as a
recipient. Review, approval, issue, authorization, and closure operations also
enforce preparer/originator separation.

### 8.2 Engagement Registry endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements` | Search, filter, sort, paginate, and include archived engagements |
| `GET` | `/api/aems/engagements/import-options` | List approved IAP engagements that have never been imported |
| `POST` | `/api/aems/engagements/import` | Create an engagement from an approved IAP item |
| `POST` | `/api/aems/engagements` | Create an independently authorized special or unplanned engagement |
| `GET` | `/api/aems/engagements/{engagement}` | Return coverage, team, event, lineage, and snapshot details |
| `PUT` | `/api/aems/engagements/{engagement}` | Update an editable engagement with optimistic locking |
| `DELETE` | `/api/aems/engagements/{engagement}` | Soft-archive an engagement |
| `POST` | `/api/aems/engagements/{engagement}/restore` | Restore an archived engagement when no active duplicate exists |

The collection accepts `search`, `sourceType`, `status`, `officeId`,
`auditAreaId`, `includeArchived`, `sortBy`, `sortDirection`, `page`, and
`perPage`. Every query is constrained by the engagement-level visibility scope.

An IAP import stores both direct lineage identifiers and an immutable
`source_snapshot`. The snapshot preserves the plan and version, approval,
prioritization decision and rank, original risk scores and residual-risk level,
audit-universe subject, offices, audit areas/focuses, objectives, scope, dates,
and planned person-days. Later IAP changes therefore do not rewrite the
engagement's planning baseline.

### 8.3 Audit Team endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/team` | Current team, candidates, history, resource summary, and warnings |
| `POST` | `/api/aems/engagements/{engagement}/team` | Assign a Supervisor, Team Leader, Auditor, or Reviewer |
| `PUT` | `/api/aems/engagements/{engagement}/team/{member}` | Update role, effort, dates, or notes |
| `POST` | `/api/aems/engagements/{engagement}/team/{member}/reassign` | End the old assignment and create a replacement with linked history |
| `DELETE` | `/api/aems/engagements/{engagement}/team/{member}` | End and soft-delete an assignment with a required reason |

### 8.4 Audit Engagement Order endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/aeo` | AEO workspace, versions, workflow events, and readiness |
| `POST` | `/api/aems/engagements/{engagement}/aeo` | Create the initial immutable draft version |
| `PUT` | `/api/aems/engagements/{engagement}/aeo/{order}` | Create a new immutable content version |
| `POST` | `/api/aems/engagements/{engagement}/aeo/{order}/transition` | Submit, review, return, resubmit, approve, or issue |
| `POST` | `/api/aems/engagements/{engagement}/aeo/{order}/revise` | Start a formal revision from an approved or issued order |
| `GET` | `/api/aems/engagements/{engagement}/aeo/{order}/pdf` | Download the exact approved AEO version |

All AEO mutations require the current `lockVersion`. Review and approval enforce
preparer separation, and approval additionally requires an independent review
event for the current version.

### 8.5 Audit Engagement Plan endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/aep` | AEP content, versions, risk lineage, team, and workflow history |
| `POST` | `/api/aems/engagements/{engagement}/aep` | Create the initial immutable AEP version after AEO issuance |
| `PUT` | `/api/aems/engagements/{engagement}/aep/{plan}` | Append an immutable draft/returned content version |
| `POST` | `/api/aems/engagements/{engagement}/aep/{plan}/transition` | Submit, review, return, resubmit, or approve |
| `POST` | `/api/aems/engagements/{engagement}/aep/{plan}/revise` | Start a formal revision of an approved AEP |

The server copies risk, prioritization, and Audit Universe lineage from the
engagement source snapshot. Clients cannot replace this lineage.

### 8.6 Audit Program endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/programs` | Program revision families, procedures, team choices, and events |
| `POST` | `/api/aems/engagements/{engagement}/programs` | Create a program from the approved AEP |
| `PUT` | `/api/aems/engagements/{engagement}/programs/{program}` | Edit current draft/returned program metadata |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/transition` | Submit, review, return, resubmit, approve, start, or complete |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/revise` | Create a documented draft revision from an approved/active baseline |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures` | Add a draft procedure |
| `PUT` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}` | Edit a draft procedure |
| `DELETE` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}` | Soft-delete a draft procedure |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/progress` | Record active-baseline progress, WP reference, completion, or waiver |
| `POST` | `/api/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/review` | Record the independent reviewer result |

Every mutation carries the parent program `lockVersion`; procedure mutations
also carry the procedure `lockVersion`.

### 8.7 Working Paper endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/working-papers` | Scoped Working Paper/Evidence workspace, active procedures, versions, events, and options |
| `POST` | `/api/aems/engagements/{engagement}/working-papers` | Create a procedure-linked draft and immutable content version 1 |
| `PUT` | `/api/aems/engagements/{engagement}/working-papers/{paper}` | Append an immutable draft/returned content version |
| `POST` | `/api/aems/engagements/{engagement}/working-papers/{paper}/transition` | Submit, return, resubmit, approve/lock, or void |
| `POST` | `/api/aems/engagements/{engagement}/working-papers/{paper}/revise` | Copy an approved version into a documented correction revision |

Submission requires population/sample descriptions and verified or locked
evidence, or a documented explanation that no attachment is required. Approval
enforces preparer separation, locks the exact evidence rows cited by the current
content version, and prevents content overwrite. Every mutation carries the
Working Paper `lockVersion`.

### 8.8 Audit Evidence endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/evidence` | Upload private evidence file and create immutable evidence/document version 1 |
| `POST` | `/api/aems/engagements/{engagement}/evidence/{evidence}/revisions` | Upload an immutable replacement version with required change reason |
| `POST` | `/api/aems/engagements/{engagement}/evidence/{evidence}/transition` | Verify metadata/checksum or void eligible evidence with a reason |
| `GET` | `/api/aems/engagements/{engagement}/evidence/{evidence}/download` | Download the exact authorized evidence version |

Evidence uploads use multipart camelCase fields. Files are stored privately as
hidden AEMS-owned Core documents. Verification checks the stored checksum;
Working Paper approval changes cited `VERIFIED` evidence to `LOCKED`. Locked
evidence cannot be voided, while replacement creates a new `DRAFT` current
version and preserves the locked historical row.

### 8.9 Issue endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/issues` | Create a supported draft issue |
| `PUT` | `/api/aems/engagements/{engagement}/issues/{issue}` | Update a draft issue and its exact support links |
| `POST` | `/api/aems/engagements/{engagement}/issues/{issue}/transition` | Submit, independently validate, dismiss with reason, or idempotently convert |

The implemented issue states are `DRAFT`, `SUBMITTED`, `VALIDATED`,
`DISMISSED`, and `CONVERTED_TO_FINDING`. Submission requires a Working Paper
version or evidence link. Validation requires approved Working Papers and
verified/locked evidence. Terminal issues cannot be edited or deleted.

### 8.10 Finding workspace and lifecycle endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/findings-workspaces` | List engagements visible to an audit-team user or responsible auditee office |
| `GET` | `/api/aems/engagements/{engagement}/findings-workspace` | Scoped Issue/Finding workspace and controlled options |
| `POST` | `/api/aems/engagements/{engagement}/findings` | Create a draft criteria-condition-cause-effect Finding |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}` | Update draft content and exact support links |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/transition` | Submit, validate, communicate, request response, record non-response, or finalize |

Finding validation requires independent authority, approved Working Paper
versions, verified evidence, and a recommendation or documented reason for
none. Validation locks cited evidence. Communication records an immutable
snapshot containing the finding elements, recommendations, recipients,
confidentiality, due date, and exact supporting IDs. Finalized findings are
immutable.

### 8.11 Recommendation endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations` | Add a draft recommendation |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}` | Edit a draft recommendation |
| `DELETE` | `/api/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}` | Remove a draft recommendation |

Recommendations remain editable while their Finding is not finalized. Finding
finalization stores a recommendation snapshot, changes every draft
recommendation to `FINALIZED`, and prevents content mutation. CMS lineage fields
remain reserved for an idempotent later transfer.

### 8.12 Management Response endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses` | Responsible-office Auditee Representative creates a draft response |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}` | Update the current draft response |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/attachments` | Upload a private supporting document to the current draft response |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/transition` | Submit/resubmit, start review, or request clarification |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/revisions` | Create an immutable successor after clarification |

Only the communicated responsible office can author a response. Submitted
versions remain historical; clarification creates a new current version rather
than overwriting the submitted response.

### 8.13 Auditor Rejoinder endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders` | Create a draft auditor disposition and rejoinder |
| `PUT` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}` | Update a draft rejoinder |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/attachments` | Upload a private supporting document to the draft rejoinder |
| `POST` | `/api/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/finalize` | Independently finalize the rejoinder and dialogue |
| `GET` | `/api/aems/engagements/{engagement}/findings/{finding}/dialogue-attachments/{attachment}/download` | Download an authorized response or rejoinder attachment |

Disposition values are `ACCEPT`, `PARTIALLY_ACCEPT`, and `REJECT`. Finalized
responses and rejoinders are immutable and satisfy the Finding finalization
dialogue gate.

Every exchange returns its actor, creation/update dates, content, status, and
version. Attachment metadata includes the exact document-version ID, original
file name, file size, MIME type, SHA-256 checksum, uploader, and upload date.
The file remains private and is served only through the engagement- and
finding-scoped authorization boundary.

### 8.14 Aggregate lifecycle and Entry Conference endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/engagements/{engagement}/lifecycle` | Return states, permitted actions, cross-workflow requirements, blockers, related links, and immutable transition history |
| `POST` | `/api/aems/engagements/{engagement}/transitions/{action}` | Execute an authorized action with optimistic locking; never accepts a target status |
| `GET` | `/api/aems/entry-conference-workspaces` | List engagements available to the actor's assignment or auditee-office scope |
| `GET` | `/api/aems/engagements/{engagement}/entry-conference` | Load the office-scoped Entry Conference, reference lists, exact attachments, acknowledgements, and history |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference` | Create the one official draft Entry Conference |
| `PUT` | `/api/aems/engagements/{engagement}/entry-conference/{conference}` | Update an editable conference, participants, matters, and agreements |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/transitions/{action}` | Schedule, reschedule, mark held, circulate notes, complete, waive, or cancel |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/acknowledgements` | Record an immutable acknowledgement with or without reservation |
| `POST` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/attachments` | Pin an uploaded briefing, agenda, notes, waiver support, or other file to an exact private Core DocumentVersion |
| `GET` | `/api/aems/engagements/{engagement}/entry-conference/{conference}/attachments/{attachment}/download` | Download an authorized exact attachment version |

Entry Conference statuses are `DRAFT`, `SCHEDULED`, `RESCHEDULED`, `HELD`,
`NOTES_FOR_ACKNOWLEDGEMENT`, `ACKNOWLEDGED`, `COMPLETED`, `WAIVED`, and
`CANCELLED`. Completion re-checks issued AEO, approved AEP/program, held date,
agenda/briefing, both attendance classes, notes, and material-matter
dispositions. Waiver requires CIAS authority, reason, separation, and any
required supporting document. Completed and waived records are immutable.

### 8.15 Exit Conference endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/exit-conference-workspaces` | List conference-enabled engagements in the caller's assignment or office scope |
| `GET` | `/api/aems/engagements/{engagement}/exit-conferences` | Load schedules, participants, linked Findings, outcomes, files, and acknowledgements |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences` | Schedule a conference with Findings and participants |
| `PUT` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}` | Update or reschedule an editable conference |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/complete` | Record attendance, finding outcomes, revised dates, minutes, and lock the completion snapshot |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/transition` | Formally waive or cancel with a reason |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/attachments` | Upload an immutable private minutes or supporting document |
| `GET` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/attachments/{attachment}/download` | Download an authorized conference document |
| `POST` | `/api/aems/engagements/{engagement}/exit-conferences/{conference}/acknowledgements` | Invited Auditee Representative acknowledges completed minutes |

Only current formally communicated or finalized Findings from the engagement
can be selected. Completion requires attendance for every participant, an
outcome for every linked Finding, and minutes. Completed, cancelled, and waived
records are immutable; acknowledgement remains a separate append-only action.

### 8.16 Audit Report endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/report-workspaces` | List internal report workspaces or issued reports visible to the recipient |
| `GET` | `/api/aems/engagements/{engagement}/reports` | Load the report family, immutable versions, comments, recipients, and CMS transfers |
| `POST` | `/api/aems/engagements/{engagement}/reports` | Generate the initial Draft Report PDF from validated or later Findings |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/versions` | Generate an immutable draft or final revision |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/final` | Generate the Final Report draft using finalized Findings only |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/transition` | Submit, return, approve, or issue |
| `GET` | `/api/aems/engagements/{engagement}/reports/{report}/versions/{version}/download` | Download an authorized private PDF version |
| `POST` | `/api/aems/engagements/{engagement}/reports/{report}/cms-transfer` | Idempotently synchronize included recommendations into CMS intake |

Each generated version preserves its arranged sections, executive summary,
selected Finding snapshots, exact Core document-version ID, PDF file name,
size, and SHA-256 checksum. Reviewer comments, recipients, approval, and
issuance metadata remain version- or family-bound as appropriate. Recipients
can access only the current issued version; confidentiality gates other
authorized downloads.

### 8.17 Engagement Tracker endpoint

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/aems/dashboard` | Return access-scoped portfolio cards, engagement progress, alerts, and pre-closure readiness |
| `GET` | `/api/aems/dashboard/export` | Download the filtered, access-scoped Engagement Progress Report as CSV |

The endpoint accepts `search`, `status`, `officeId`, `page`, `perPage`,
`sortBy`, and `sortDirection`. Portfolio cards always represent the actor's
complete visible portfolio; filters and pagination affect the engagement
tracker list. The response includes:

- active, planning, and fieldwork engagement counts;
- overdue procedures, Working Papers awaiting review, Findings awaiting
  response, upcoming Exit Conferences, and reports pending approval;
- engagements whose currently implemented pre-closure gates are satisfied;
- 14 derived stages per engagement: AEO, AEP, Audit Program, Entry Conference, fieldwork
  procedures, Working Papers, Evidence, Findings, Management Responses, Exit
  Conference, Draft Report, Final Report, CMS transfer, and Engagement
  Closure;
- overdue and schedule alerts, overall percentage, office/status filter
  options, and pagination metadata;
- the closure gate checklist and remaining blocker labels.
- active integration metadata for Core, IAP, CMS, and the current
  ARMIS-compatible resource provider.

The endpoint uses `AuditEngagement::visibleTo()` before aggregation and record
loading. It persists no dashboard-owned status. A stage changes only when its
authoritative workflow record changes. “Ready for closure” means only that the
pre-closure gates are met. The response separately reports the formal
Completion Assessment and Closure status. Neither a tracker stage nor a 100%
value can execute `CLOSED`.

The CSV export accepts `search`, `status`, `officeId`, `sortBy`, and
`sortDirection`, requires `aems.engagement.export`, and reuses the same
visibility scope. Each export records an Activity Log and Audit Log containing
the filters, row count, file name, actor, and request context.

### 8.18 Completion Assessment and Closure endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/completion-assessments` | Load revision/version history or create the current 25-criterion assessment |
| `PUT` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}` | Update editable content and criterion results with optimistic locking |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/transitions/{action}` | Submit, return, resubmit, or approve through a controlled action |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/items/{item}/accept-blocker` | Elevated formal acceptance of a documented unresolved blocker |
| `POST` | `/api/aems/engagements/{engagement}/completion-assessments/{assessment}/revisions` | Create a controlled current correction from an approved assessment |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/closure` | Load the formal workspace or create the current Closure record |
| `PUT` | `/api/aems/engagements/{engagement}/closures/{closure}` | Update an editable Closure summary |
| `GET` | `/api/aems/engagements/{engagement}/closures/{closure}/checklist` | Return evaluated authoritative gates and sources |
| `POST` | `/api/aems/engagements/{engagement}/closures/{closure}/refresh-checklist` | Re-evaluate and persist current source-derived checklist results |
| `POST` | `/api/aems/engagements/{engagement}/closures/{closure}/transitions/{action}` | Submit, return, resubmit, approve, or atomically close |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/document-index` | Load index/readiness or add an authorized supporting record |
| `POST` | `/api/aems/engagements/{engagement}/document-index/refresh` | Discover eligible exact document versions without copying files |
| `POST` | `/api/aems/engagements/{engagement}/document-index/{item}/exclude` | Exclude with mandatory reason and authority |
| `GET` | `/api/aems/engagements/{engagement}/document-index/export` | Export the access-scoped final index as CSV |
| `PUT` | `/api/aems/engagements/{engagement}/retention` | Save interim classification, custody, disposition, and legal-hold metadata |
| `POST` | `/api/aems/engagements/{engagement}/retention/{retention}/approve` | Independently approve and lock retention metadata |
| `POST` | `/api/aems/engagements/{engagement}/lessons-learned` | Record a confidential improvement item separate from issued results |
| `GET`, `POST` | `/api/aems/engagements/{engagement}/reopen-requests` | Load history or draft a written-authority exceptional request |
| `POST` | `/api/aems/engagements/{engagement}/reopen-requests/{reopen}/transitions/{action}` | Submit, approve, reject, or implement reopening |

The server accepts action codes, never arbitrary status targets. An approved
Completion Assessment or a 100% tracker value does not close the engagement.
`CLOSE_ENGAGEMENT` locks and re-evaluates the current engagement and Closure,
then atomically writes both `CLOSED` states, final snapshots, immutable events,
Activity Log, and Audit Trail. Notifications are queued after commit.

### 8.19 AEMS module integration contracts

Cross-module operations resolve through container-bound contracts:

| Contract | Current provider | Boundary |
| --- | --- | --- |
| `IapEngagementGateway` | `DatabaseIapEngagementGateway` | Lists and locks only approved/active IAP engagement sources, then preserves the imported source link |
| `CmsRecommendationGateway` | `DatabaseCmsRecommendationGateway` | Thin adapter to `CmsIntakeService`; creates one immutable intake/case/event per eligible issued recommendation and returns the same source-matching record on retry |
| `ResourcePlanningGateway` | `InterimIapResourcePlanningGateway` | Supplies capacity, availability, workload inputs, competencies, and person-days through an ARMIS-replaceable API |
| `EngagementRetentionProvider` | `InterimAemsRetentionProvider` | Preserves approved AEMS retention/custody snapshots behind a Core Records Management-replaceable boundary; never destroys records |

The dashboard response includes `integrations.core`, `integrations.iap`,
`integrations.cms`, and `integrations.armis`. The ARMIS entry is intentionally
reported as `IAP_INTERIM_FALLBACK` and non-authoritative. A future ARMIS adapter
can replace the `ResourcePlanningGateway` binding without changing Audit Team
or Engagement Tracker consumers.

Core remains authoritative for Users, Offices, Roles, Permissions, Scopes,
Audit Areas, Audit Focuses, Master Lists, private Documents and
`DocumentVersion`s, reusable workflow infrastructure, Notifications, Activity
Logs, Audit Trails, runtime settings, and document numbering. AEMS
domain-specific transitions remain code-guarded because configurable workflow
records must not create or bypass audit states. Team assignments and issued
reports, AEO/AEP review, returned Working Papers, communicated Findings, Exit
Conferences, and report review actions create deduplicated Core Notifications
after the surrounding transaction commits. The scheduled reminder command also
covers overdue procedures, Management Response deadlines, and upcoming Exit
Conferences.

## 9. Reference/master-list codes

Important configurable list families include:

```text
OFFICE_TYPE
SECTOR
POSITION
EMPLOYMENT_TYPE
AUDIT_AREA_TYPE
DOCUMENT_TYPE
DOCUMENT_CONFIDENTIALITY
AEMS_EVIDENCE_CATEGORY
AEMS_EVIDENCE_SOURCE_TYPE
RISK_LEVEL
IAP_AUDIT_UNIVERSE_SUBJECT_TYPE
IAP_PLANNING_PERIOD_TYPE
IAP_PLANNING_PRIORITY
IAP_RISK_CRITERION
IAP_ENGAGEMENT_TYPE
IAP_AUDIT_APPROACH
IAP_COMMENT_TYPE
IAP_ATTACHMENT_TYPE
IAP_AUDITOR_SKILL
IAP_UNAVAILABILITY_TYPE
```

Use the seeded `MasterListSeeder` as the source of exact current values.

## 10. Runtime configuration keys

| Key | Type |
| --- | --- |
| `system_name`, `system_short_name`, `organization_name`, `system_version` | string |
| `pagination_size`, `session_timeout_minutes` | integer |
| `date_format`, `timezone` | string |
| `password_min_length`, `failed_login_limit`, `account_lock_minutes` | integer |
| `fiscal_year_start_month`, `iap_default_annual_person_days` | integer |
| `document_upload_max_mb`, `notification_refresh_seconds` | integer |
| `document_number_format`, `iap_plan_number_format`, `siap_plan_number_format` | string |
| `risk_period_number_format`, `prioritization_number_format` | string |
| `default_risk_level_code`, `default_workflow_sla_hours` | string/integer |
| `workflow_mapping_core`, `workflow_mapping_iap` | workflow code |
| `logo_url` | managed URL |
| `mail_enabled`, `mail_mailer`, `mail_host`, `mail_port` | boolean/string/integer |
| `mail_encryption`, `mail_username`, `mail_password` | string/encrypted secret |
| `mail_from_address`, `mail_from_name` | string |

## 11. Source-of-truth rule

This reference describes the current implementation. When behavior and this file
disagree, verify `backend/routes/api.php`, Form Requests, services, migrations,
and feature tests, then update the documentation in the same change.

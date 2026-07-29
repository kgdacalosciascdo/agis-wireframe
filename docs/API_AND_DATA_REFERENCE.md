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

## 8. Reference/master-list codes

Important configurable list families include:

```text
OFFICE_TYPE
SECTOR
POSITION
EMPLOYMENT_TYPE
AUDIT_AREA_TYPE
DOCUMENT_TYPE
DOCUMENT_CONFIDENTIALITY
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

## 9. Runtime configuration keys

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

## 10. Source-of-truth rule

This reference describes the current implementation. When behavior and this file
disagree, verify `backend/routes/api.php`, Form Requests, services, migrations,
and feature tests, then update the documentation in the same change.

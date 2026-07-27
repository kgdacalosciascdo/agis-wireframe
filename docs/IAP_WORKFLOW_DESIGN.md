# Internal Audit Planning (IAP) Workflow Design

## 1. Purpose and scope

The IAP module decides what should be audited and when. Its authoritative planning
sequence is:

```text
Audit Universe
  -> Risk Assessment
  -> Prioritization
  -> Internal Annual Audit Plan
  -> Scheduling
  -> Approval
```

The module also maintains the longer-term Strategic Internal Audit Plan that guides
annual priorities. The approved planning scope therefore consists of seven
connected capabilities:

1. Strategic Internal Audit Plan (SIAP)
2. Audit Universe Registry
3. Risk Assessment
4. Audit Prioritization
5. Internal Annual Audit Plan (IAAP)
6. Audit Scheduling
7. IAP Dashboard

The Annual Internal Audit Plan implementation described later in this document is
the IAAP capability. It is one part of the complete IAP module and must not be
treated as the whole module.

Detailed engagement execution belongs to AEM. Findings and recommendations belong
to AFR. Recommendation monitoring belongs to CMS. Staff availability and capacity
come from ARMIS.

## 1.1 Required IAP screens and capabilities

### A. Strategic Internal Audit Plan (SIAP)

SIAP defines the multi-year direction that annual plans must support.

Required functions:

- create a strategic plan and define its planning period;
- add strategic objectives;
- add audit priorities or themes;
- map audit areas to one or more strategic objectives;
- submit for review;
- approve or return the plan;
- create a formal plan revision;
- preserve and view every approved version.

Example: `SIAP 2026-2030`, with objectives for revenue collection controls, IT
governance, and procurement compliance.

### B. Audit Universe Registry

The Audit Universe is the authoritative inventory of auditable subjects. An
auditable subject may be a process, program, system, service, project, location,
entity, fund, contract, or cross-office activity.

Required functions:

- add, edit, archive, and restore an auditable subject;
- assign its responsible office;
- assign its primary Audit Area;
- assign zero or more stakeholder offices;
- record its last audit date;
- record materiality or exposure information;
- view linked historical audits;
- search, sort, paginate, and filter the Audit Universe.

Example: `Business Tax Collection Process`, owned by the City Treasurer's Office
under the Revenue Collection Audit Area, last audited in 2023.

### C. Risk Assessment

Risk assessment evaluates an Audit Universe item during an opened assessment
period.

Required functions:

- open and close an assessment period;
- select an Audit Universe item;
- answer configured risk criteria;
- assign criterion scores and evidence;
- add assessment justification;
- upload supporting evidence;
- calculate inherent risk;
- record control effectiveness;
- calculate residual risk;
- submit the assessment;
- validate or return the assessment;
- lock a validated assessment.

The initial criteria include financial exposure, service impact, control weakness,
complexity, and compliance sensitivity. The ten detailed criteria already seeded
by AGIS remain valid configurable criteria.

### D. Audit Prioritization

Prioritization produces a repeatable ranked list from validated Audit Universe risk
assessments.

Required functions:

- calculate and store a priority score;
- rank assessed Audit Universe items;
- filter High and Critical risks;
- compare risk components and total scores;
- decide `Selected`, `Deferred`, or `Not Selected`;
- require a reason for manual ranking or decision overrides;
- preserve the source risk assessment and ranking snapshot used by an IAAP.

### E. Internal Annual Audit Plan (IAAP)

IAAP converts selected prioritized items into the approved annual audit workload.
The existing `internal_audit_plans` implementation is the foundation of this
capability.

Required functions:

- create an annual plan;
- add selected prioritized Audit Universe items;
- remove or defer an item with a reason;
- define planned objectives and preliminary scope;
- select engagement type;
- assign planned person-days;
- set a target quarter;
- calculate total planned demand;
- compare demand against available ARMIS capacity;
- submit, return, revise, and approve the plan;
- freeze every approved revision.

### F. Audit Scheduling

Scheduling turns approved IAAP items into dated proposed engagements.

Required functions:

- schedule an approved audit item;
- set planned start, end, and expected-report dates;
- assign a proposed Team Leader;
- display a calendar view;
- detect auditor and date conflicts;
- reschedule only with a reason;
- cancel a schedule with authorization and a reason;
- preserve complete schedule history.

### G. IAP Dashboard

The dashboard must derive its values from the records above. Initial cards and
views:

- Total Audit Universe;
- Critical Risk Areas;
- High Risk Areas;
- Planned Audits;
- Unplanned Audits;
- Available Person-Days from ARMIS;
- Allocated Person-Days;
- Plan Accomplishment;
- Upcoming Audits.

## 1.2 Expanded data model

The complete IAP module requires these additional normalized record groups:

| Capability         | Required records                                                                                                                                                      |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SIAP               | strategic plans, objectives, themes/priorities, objective-to-audit-area mappings, revisions, and workflow events                                                      |
| Audit Universe     | auditable subjects, responsible office, stakeholder-office pivot, primary audit area, materiality/exposure, last-audit metadata, and archive state                    |
| Assessment periods | period header, open/close/lock status, responsible assessors, and workflow history                                                                                    |
| Risk               | assessment linked to an Audit Universe item, criterion answers, evidence, inherent score, control-effectiveness result, residual score, validation, and lock metadata |
| Prioritization     | prioritization run, ranked item snapshot, calculated rank/score, final decision, manual override, and deferral/non-selection reason                                   |
| IAAP               | annual plan, selected prioritization items, proposed objectives/scope/type/quarter/person-days, capacity snapshot, revisions, and approval                            |
| Scheduling         | schedule header, proposed team leader, expected report date, conflict result, cancellation, rescheduling reason, and immutable schedule history                       |

An Audit Universe item is the planning subject. Office and Audit Area are
classifications and ownership relationships; they are not substitutes for the
auditable subject itself.

## 1.3 Implementation status and required evolution

| Capability      | Current status                            | Required next work                                                                                                                                                                                     |
| --------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| SIAP            | Not yet implemented                       | Build strategic-plan records, objective/theme mappings, versioning, and approval                                                                                                                       |
| Audit Universe  | Not yet implemented                       | Build first because risk, prioritization, and IAAP selection depend on it                                                                                                                              |
| Risk Assessment | Partially implemented                     | Current office/audit-area assessment and weighted scoring must be linked to Audit Universe items and assessment periods; add evidence, inherent/control/residual calculations, validation, and locking |
| Prioritization  | Not yet implemented                       | Add ranked snapshots and selected/deferred/not-selected decisions                                                                                                                                      |
| IAAP            | Foundation implemented                    | Link proposed engagements to selected prioritization items, add target quarter, capacity comparison, and approved-version freeze                                                                       |
| Scheduling      | Partially represented by engagement dates | Add a dedicated schedule and immutable reschedule/cancellation history with conflict detection                                                                                                         |
| Dashboard       | Existing dashboard is illustrative        | Replace IAP cards with derived planning metrics                                                                                                                                                        |

## 1.4 Implementation dependency order

The remaining work must be implemented in this order:

1. Audit Universe Registry
2. SIAP and strategic objective/theme mappings
3. Assessment Periods and Audit-Universe-based Risk Assessment
4. Audit Prioritization
5. IAAP selection and ARMIS capacity comparison
6. Audit Scheduling and conflict/history controls
7. IAP Dashboard and reports

This order prevents annual-plan and scheduling records from being built on
temporary office-only risk subjects.

## 2. Core design decisions

1. AGIS normally has one current Annual Internal Audit Plan for each fiscal year.
2. An approved plan is immutable. Changes require a new revision copied from the
   approved plan.
3. Every revision has its own engagements, risk assessments, assignments,
   comments, attachments, and approval history.
4. Workflow transitions are performed by server actions, never by directly editing
   the status field.
5. Workflow history is permanent and cannot be edited or deleted.
6. Deletion is implemented as archival through soft deletion.
7. Risk ratings come from scored criteria, while an authorized reviewer may make a
   documented override.
8. Proposed engagements may cover multiple offices, audit areas, and audit focuses.
9. Team members are assigned to individual proposed engagements, with a planned
   person-day allocation.
10. All workflow actions use database transactions, row locking, backend
    authorization, and audit logging.

## 3. Main records

### 3.1 Annual Internal Audit Plan

Suggested record name: `internal_audit_plans`

| Field                   | Required | Purpose                                             |
| ----------------------- | -------: | --------------------------------------------------- |
| `id`                    |      Yes | Internal primary key                                |
| `plan_code`             |      Yes | Human-readable identifier, such as `IAP-2027-R00`   |
| `fiscal_year`           |      Yes | Covered fiscal year                                 |
| `planning_period_start` |      Yes | Beginning of the covered planning period            |
| `planning_period_end`   |      Yes | End of the covered planning period                  |
| `title`                 |      Yes | Formal plan title                                   |
| `executive_summary`     |       No | High-level planning context and priorities          |
| `planning_methodology`  |       No | Risk assessment and prioritization approach         |
| `overall_objective`     |      Yes | Overall objective of the annual plan                |
| `overall_scope`         |      Yes | Overall organizational and operational coverage     |
| `limitations`           |       No | Known planning limitations and exclusions           |
| `status`                |      Yes | Controlled workflow status                          |
| `revision_number`       |      Yes | Starts at `0` and increases for formal revisions    |
| `supersedes_plan_id`    |       No | Previous approved revision                          |
| `is_current_revision`   |      Yes | Identifies the current revision for the fiscal year |
| `prepared_by`           |      Yes | Primary plan preparer                               |
| `coordinator_id`        |       No | CIAS management coordinator                         |
| `submitted_at/by`       |       No | Submission metadata                                 |
| `approved_at/by`        |       No | Approval metadata                                   |
| `activated_at/by`       |       No | Activation metadata                                 |
| `completed_at/by`       |       No | Completion metadata                                 |
| `lock_version`          |      Yes | Prevents concurrent overwrite or double approval    |
| `is_active`             |      Yes | Operational active flag                             |
| timestamps              |      Yes | Creation and update timestamps                      |
| `deleted_at`            |       No | Soft-deletion timestamp                             |

Recommended constraints:

- `plan_code` is globally unique.
- `(fiscal_year, revision_number)` is unique.
- only one non-archived row per fiscal year may have `is_current_revision=true`;
- `planning_period_start <= planning_period_end`;
- fiscal year is between 2000 and 2100.

### 3.2 Risk Assessment

Suggested records:

- `iap_risk_assessments`
- `iap_risk_assessment_scores`

One assessment evaluates an office and audit area for a specific plan revision.

Risk assessment fields:

| Field                       |    Required | Purpose                            |
| --------------------------- | ----------: | ---------------------------------- |
| `plan_id`                   |         Yes | Plan revision being prepared       |
| `office_id`                 |         Yes | Assessed city office               |
| `audit_area_id`             |         Yes | Assessed audit area                |
| `assessed_by`               |         Yes | Responsible assessor               |
| `assessment_date`           |         Yes | Date assessed                      |
| `last_audit_date`           |          No | Most recent related audit          |
| `inherent_risk_notes`       |          No | Inherent exposure                  |
| `control_environment_notes` |          No | Known control conditions           |
| `total_weighted_score`      |         Yes | Calculated score from 1.00 to 5.00 |
| `calculated_risk_level`     |         Yes | System-derived risk level          |
| `override_risk_level`       |          No | Reviewer-authorized risk override  |
| `override_reason`           | Conditional | Required when an override is used  |
| `final_risk_level`          |         Yes | Calculated or authorized override  |
| `justification`             |         Yes | Planning rationale                 |

Risk score fields:

- risk assessment;
- risk criterion master-list item;
- criterion weight;
- rating from 1 to 5;
- weighted score;
- assessor comment.

Proposed initial criteria:

1. Financial exposure and materiality
2. Prior audit findings and unresolved recommendations
3. Internal-control maturity
4. Legal and regulatory exposure
5. Operational complexity and organizational change
6. Fraud, integrity, and safeguarding exposure
7. Public-service and stakeholder impact
8. Time elapsed since the last audit
9. Management or oversight concern
10. Information-system and data dependency

The criteria weights for an assessment must total 100%.

Initial risk thresholds:

| Weighted score | Risk level |
| -------------: | ---------- |
|      1.00–1.99 | Low        |
|      2.00–2.99 | Medium     |
|      3.00–3.99 | High       |
|      4.00–5.00 | Critical   |

The thresholds should be centrally configurable rather than hard-coded in React.

### 3.3 Proposed Audit Engagement

Suggested record name: `iap_plan_engagements`

| Field                   | Required | Purpose                                           |
| ----------------------- | -------: | ------------------------------------------------- |
| `plan_id`               |      Yes | Parent plan revision                              |
| `engagement_code`       |      Yes | Proposed identifier, such as `IAP-2027-001`       |
| `title`                 |      Yes | Proposed engagement title                         |
| `engagement_type_id`    |      Yes | Master-list engagement type                       |
| `priority_id`           |      Yes | Master-list planning priority                     |
| `risk_level_id`         |      Yes | Final planning risk level                         |
| `risk_assessment_id`    |       No | Principal supporting assessment                   |
| `background`            |       No | Reason for proposing the engagement               |
| `objectives`            |      Yes | Intended engagement objectives                    |
| `scope`                 |      Yes | Processes, units, periods, and boundaries covered |
| `exclusions`            |       No | Explicit scope exclusions                         |
| `audit_criteria`        |       No | Laws, policies, standards, or criteria expected   |
| `proposed_methodology`  |       No | Planned approach and methods                      |
| `planned_start_date`    |      Yes | Proposed start                                    |
| `planned_end_date`      |      Yes | Proposed end                                      |
| `estimated_person_days` |      Yes | Total planned audit effort                        |
| `estimated_cost`        |       No | Estimated direct cost                             |
| `sequence_number`       |      Yes | Display and implementation order                  |
| `planning_notes`        |       No | Additional planning notes                         |
| `aem_engagement_id`     |       No | AEM record created after plan activation          |
| `is_active`             |      Yes | Operational active flag                           |
| timestamps              |      Yes | Creation and update timestamps                    |
| `deleted_at`            |       No | Soft deletion                                     |

Coverage relationships:

- `iap_engagement_offices`: one engagement to one or more offices;
- `iap_engagement_audit_areas`: one engagement to one or more audit areas;
- `iap_engagement_audit_focuses`: optional detailed focus coverage.

Every selected audit focus must belong to a selected audit area. Every selected
office/audit-area combination must be valid in the Core Office and Audit Area
registries.

### 3.4 Assigned Audit Team

Suggested record name: `iap_engagement_team_members`

| Field                 | Required | Purpose                                        |
| --------------------- | -------: | ---------------------------------------------- |
| `plan_engagement_id`  |      Yes | Proposed engagement                            |
| `user_id`             |      Yes | Active CIAS user                               |
| `team_role_id`        |      Yes | Lead, member, reviewer, specialist, or support |
| `planned_person_days` |      Yes | Planned allocation                             |
| `assignment_notes`    |       No | Responsibility or specialist scope             |

Rules:

- a user may appear only once per engagement;
- at least one lead auditor and one reviewer must be assigned before submission;
- only active CIAS Management or AGIS User accounts may be assigned;
- member person-days must total the engagement's estimated person-days;
- allocations may not exceed the user's available planning-period capacity;
- the reviewer cannot also be the sole lead auditor.

### 3.5 Approval History

Suggested record name: `iap_workflow_events`

Each workflow action creates an immutable event containing:

- plan revision;
- action;
- previous status;
- resulting status;
- actor and actor role at the time of action;
- comment or decision rationale;
- action timestamp;
- IP address and user agent;
- plan `lock_version`;
- optional metadata, such as validation warnings or generated report identifiers.

This domain history is separate from the technical audit trail. Both are retained.

### 3.6 Comments

Suggested record name: `iap_comments`

A comment may belong to the plan or a proposed engagement.

Comment types:

- general planning comment;
- reviewer comment;
- return-for-revision instruction;
- management comment;
- approval note;
- revision explanation.

Fields include author, comment type, body, internal/shared visibility, optional
parent comment, timestamps, and soft deletion. Comments required by a workflow
decision become immutable after the decision is recorded.

### 3.7 Attachments

Suggested record name: `iap_attachments`

Attachments use the shared private document-storage service and associate a stored
document with:

- a plan revision;
- optionally, a proposed engagement or risk assessment;
- attachment purpose/type;
- display name;
- uploaded-by and uploaded-at metadata;
- internal/shared visibility.

IAP working attachments must not automatically appear in the general reference
library. Step 2 should extend the shared document service with module ownership or
a document-link association rather than expose plan working files publicly.

## 4. Workflow

```text
Draft
  └─ Submit ───────────────→ Pending Review
                                  ├─ Return ─────→ Returned for Revision
                                  │                    └─ Resubmit ─→ Resubmitted
                                  │                                      ├─ Return
                                  │                                      ├─ Approve
                                  │                                      └─ Reject
                                  ├─ Approve ────→ Approved
                                  └─ Reject ─────→ Rejected

Approved ── Activate ─────────→ Active
Active ──── Complete ─────────→ Completed
```

### 4.1 Transition rules

| Current status        | Action   | Next status           | Required permission | Comment                     |
| --------------------- | -------- | --------------------- | ------------------- | --------------------------- |
| Draft                 | Submit   | Pending Review        | `iap.submit`        | Optional                    |
| Returned for Revision | Resubmit | Resubmitted           | `iap.submit`        | Required summary of changes |
| Pending Review        | Return   | Returned for Revision | `iap.review`        | Required                    |
| Resubmitted           | Return   | Returned for Revision | `iap.review`        | Required                    |
| Pending Review        | Approve  | Approved              | `iap.approve`       | Optional                    |
| Resubmitted           | Approve  | Approved              | `iap.approve`       | Optional                    |
| Pending Review        | Reject   | Rejected              | `iap.review`        | Required                    |
| Resubmitted           | Reject   | Rejected              | `iap.review`        | Required                    |
| Approved              | Activate | Active                | `iap.activate`      | Optional                    |
| Active                | Complete | Completed             | `iap.complete`      | Optional                    |

Archive is not a workflow status. It is a recoverable record state.

### 4.2 Status behavior

**Draft**

- Editable by authorized preparers.
- Risk assessments, engagements, teams, comments, and attachments may be changed.
- May be archived if never submitted.

**Pending Review**

- Content is locked.
- Reviewers may comment, return, approve, or reject.
- Preparers may not edit until returned.

**Returned for Revision**

- Content is editable.
- The reviewer’s return comment remains visible.
- Resubmission requires a change summary.

**Resubmitted**

- Content is locked.
- Review actions are the same as Pending Review.

**Approved**

- Content is immutable.
- Activation is allowed.
- A change requires `Create Revision`, which clones the approved plan into a new
  Draft revision and preserves the approved revision.

**Active**

- The approved plan is the basis for creating and monitoring AEM engagements.
- Planning content is immutable.
- Schedule adjustments must be processed through a formal revision.

**Completed**

- All linked AEM engagements must be completed, closed, cancelled with authority,
  or formally carried forward before plan completion.
- The plan is read-only.

**Rejected**

- The revision is terminal and read-only.
- A new revision may be created using the rejected content as a starting point.

## 5. Submission completeness checks

The server must reject submission unless:

1. the fiscal year, period, objective, and scope are complete;
2. at least one risk assessment exists;
3. every risk assessment has 100% criterion weight and a final risk level;
4. at least one proposed engagement exists;
5. every engagement has an office, audit area, objectives, scope, priority, risk
   level, start date, end date, and positive person-day estimate;
6. engagement dates fall within the planning period;
7. every selected audit focus belongs to a selected audit area;
8. every engagement has a lead, reviewer, and valid team allocation;
9. team person-days equal the engagement estimate;
10. there are no duplicate engagement codes;
11. required attachments or management comments configured by policy are present;
12. no stale edit exists according to `lock_version`.

The UI may display the checks early, but Laravel and PostgreSQL remain authoritative.

## 6. Revision rules

1. Only Approved or Active plans may use `Create Revision`.
2. The action creates a new Draft row with the next revision number.
3. The header, assessments, scores, engagements, coverage, and team allocations are
   copied in one transaction.
4. The previous revision remains immutable and readable.
5. A revision reason is required.
6. Only one revision for a fiscal year may be current.
7. Activating an approved revision supersedes the previously active revision.
8. Links to AEM engagements are preserved and reconciled rather than silently
   deleted.

## 7. Permission model

Recommended action permissions:

- `iap.view`
- `iap.create`
- `iap.update`
- `iap.assess_risk`
- `iap.manage_engagements`
- `iap.assign_team`
- `iap.submit`
- `iap.review`
- `iap.approve`
- `iap.activate`
- `iap.complete`
- `iap.create_revision`
- `iap.archive`
- `iap.restore`
- `iap.export`

Recommended role defaults:

| Role                   | Default IAP access                                                                                             |
| ---------------------- | -------------------------------------------------------------------------------------------------------------- |
| Platform Administrator | All actions for support and emergency administration; business approval use should be restricted operationally |
| AGIS Administrator     | View and administrative monitoring only                                                                        |
| CIAS Management        | Full planning, review, approval, activation, completion, revision, archive, restore, and export                |
| AGIS User              | View; edit assigned Draft/Returned plans; assess risk; manage assigned engagements and teams                   |
| Auditee Representative | No IAP access by default                                                                                       |
| Read Only User         | View and export Approved, Active, and Completed plans only                                                     |

Permissions do not replace record scope. Backend policies must also check status,
assignment, office, role, and whether the user is acting on their own submission.

## 8. Separation of duties

- A preparer should not be the sole approver of the same plan.
- The actor who submits a plan cannot approve it unless an explicitly documented
  emergency override is authorized.
- A plan reviewer should not be the only lead auditor for every engagement.
- Risk overrides require CIAS Management authority and a reason.
- The approval action stores the approver’s role and permission snapshot.

## 9. Archival and retention

- Draft, Returned, Rejected, and Completed revisions may be archived by authorized
  users.
- Pending Review, Resubmitted, Approved, and Active revisions cannot be archived.
- A current plan revision cannot be archived until another current revision is
  established or the plan is formally completed.
- Restore must verify that referenced offices, audit areas, users, master-list
  values, and attachments still exist.
- Workflow events and technical audit logs are never soft-deleted with the plan.
- Retention duration must follow the approved City Government and National Archives
  records-disposition rules configured for production.

## 10. Notifications

The workflow should generate notifications for:

- plan submitted for review;
- plan returned with required revisions;
- plan resubmitted;
- plan approved or rejected;
- plan activated;
- plan nearing its planning-period start without activation;
- team assignment or assignment change;
- approved plan revision created;
- active plan completed.

Notification delivery failure must not roll back the workflow transaction. Events
should be queued after the database commit.

## 11. Reports and outputs

Initial outputs:

1. Annual Internal Audit Plan
2. Risk Assessment Summary
3. Audit Universe and Coverage Matrix
4. Proposed Engagement Schedule
5. Auditor Person-Day Allocation
6. Office and Audit Area Coverage Summary
7. Approval and Revision History
8. Uncovered High/Critical Risk Areas

Approved reports must show the plan code, fiscal year, revision number, status,
generation timestamp, and approving authority.

## 12. Step 1 acceptance criteria

Step 1 is complete when the project accepts this specification as the basis for:

- normalized migrations and database constraints;
- master-list seed data;
- granular IAP permissions;
- backend workflow policies and transition endpoints;
- list, form, risk assessment, engagement, team, review, and detail screens;
- notification events, reports, and automated tests.

The next implementation step is to convert this design into the IAP migrations,
models, master-list seeders, and permission updates.

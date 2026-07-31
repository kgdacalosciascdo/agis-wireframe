# CMS Workflow Design

## 1. Purpose and implemented boundary

Compliance Management receives finalized recommendations from issued AEMS
reports and preserves management accountability through a controlled
implementation workflow.

Implemented as-built scope:

- CMS-1 immutable AEMS recommendation intake, operational case, and initial
  append-only event;
- CMS-2 scoped dashboard, server-driven registry, safe recommendation detail,
  Compliance Monitor assignment history, and React workspace;
- CMS-3A management-owned Corrective Action Plan family, immutable versions,
  measurable milestones, independent compliance review, return, acceptance,
  and controlled revision;
- CMS-3B responsive React Action Plan workspace for management preparation,
  milestone editing, submission, reviewer decisions, accepted-baseline
  visibility, immutable history, and controlled revision;
- CMS-4A management-reported Progress Update families, immutable versions,
  accepted-baseline milestone reports, exact Core Document Version evidence,
  completeness review, recording, and controlled correction.
- CMS-4B responsive React Progress Update workspace; and
- CMS-5A independent Validation Review families, historical Primary Validator
  assignments, professional Validation Versions and Items, evidence
  assessments, validator-obtained Core Document Versions, supervisory review,
  four controlled conclusions, and authoritative implementation-state
  transitions.

Target-date extensions, due-soon configuration, reminders, escalation, closure, accepted risk,
no-longer-applicable decisions, reopening, and CMS reports/exports remain
future scope. The CMS-5B React validation workspace is operational against the
CMS-5A contracts and the scoped `validation-options` endpoint described in
section 13.6.

## 2. Record lineage

```text
Issued AEMS Report Version
  → immutable CmsRecommendation intake
  → one CmsRecommendationCase
  → zero or one CmsCorrectiveActionPlan family
  → one or more immutable CmsActionPlanVersion records
  → one or more version-owned CmsActionPlanMilestone records
  → zero or more CmsProgressUpdate reporting-period families
  → one or more immutable CmsProgressUpdateVersion records
  → accepted-baseline CmsMilestoneProgress records
  → exact CmsProgressEvidenceLink → Core DocumentVersion records
  → one CmsValidationReview per exact recorded Progress Update Version
  → immutable CmsValidationVersion revisions and CmsValidationItem procedures
  → CmsValidationEvidenceAssessment and exact validator DocumentVersion links
```

The `{recommendation}` CMS route identifier is always
`cms_recommendation_cases.id`. CMS never rewrites the issued recommendation,
finding, report, original responsible-office snapshot, or original target date.

## 3. Recommendation case status

```text
TRANSFERRED
  → FOR_ACTION_PLAN       first Action Plan draft is created
  → MONITORING            a reviewed Action Plan version is accepted
```

Draft updates, submission, review start, return, and an initial revision keep
the case in `FOR_ACTION_PLAN`. A revision of an accepted plan leaves the case in
`MONITORING`; the previously accepted version remains authoritative until the
revision is accepted. Clients cannot set case status.

## 4. Action Plan aggregate

### 4.1 Stable family

`cms_corrective_action_plans` has one row per recommendation case. It records
the owner office, creator, current-version pointer, accepted-version pointer,
and optimistic lock. Its display code is derived as
`CAP-CMS-REC-{zero-padded case ID}`; no unsupported numbering configuration is
stored.

### 4.2 Immutable versions

`cms_action_plan_versions` stores narratives, plan dates, owner/focal user,
workflow actors and timestamps, immutable submission snapshot, prior-version
lineage, and optimistic lock.

Statuses:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → ACCEPTED
                                └→ RETURNED
RETURNED → new DRAFT revision
ACCEPTED → new DRAFT revision
```

Only `DRAFT` content is editable. Submitted, returned, and accepted records are
never reopened. A newer accepted version changes the family accepted pointer;
the old accepted record remains `ACCEPTED` and is derived as superseded.

PostgreSQL uses a partial unique index and all supported test/runtime databases
use a nullable `active_slot` unique key to enforce one
`DRAFT`/`SUBMITTED`/`UNDER_REVIEW` version per family.

### 4.3 Milestones

Milestones belong to one version and record sequence, measurable output,
indicator/verification information, responsible office/user, dates, optional
weight, and display order. They can be replaced or removed only while their
version is `DRAFT`.

Submission requires at least one milestone. Sequence numbers are unique.
Milestone responsibility remains with the Action Plan owner office in CMS-3A.
Dates must fit the plan period. If weights are unused, no weighted progress is
implied; if any weight is used, every milestone is weighted and the total must
equal 100%.

## 5. Ownership, access, and separation

`CmsRecommendationScopeService` remains the single visibility and
confidentiality boundary.

Responsible-office actions require the exact granular permission, a usable
account, case visibility, and an office matching the case lead responsible
office:

- create;
- update a draft;
- submit;
- revise a returned or accepted current version.

Reviewer actions require the assigned active Compliance Monitor or CIAS
Management, the exact review/return/accept permission, case visibility,
independence from the owner office, and separation from preparer, focal user,
and submitter. Platform and AGIS administrators receive no professional review
or acceptance authority.

Permissions:

```text
cms.action-plan.view
cms.action-plan.create
cms.action-plan.update
cms.action-plan.submit
cms.action-plan.review
cms.action-plan.accept
cms.action-plan.return
cms.action-plan.revise
```

Legacy `cms.*` permissions remain unchanged and do not bypass office,
assignment, state, confidentiality, or separation rules.

## 6. Completeness and target dates

Submission and acceptance validate narratives, focal user, plan dates, at
least one complete milestone, office/user eligibility, unique sequences, date
consistency, weighting, and current `lockVersion`.

An Action Plan target cannot exceed the case effective target without the
future extension workflow. When the case has no effective target, management
may propose an Action Plan date, but CMS-3A does not establish or overwrite the
case target. Resources expose that missing-policy condition.

## 7. Transactions, locking, and immutable baseline

Creation, update, submission, review start, return, acceptance, and revision
use database transactions and row locks. Unique constraints protect one family,
version numbers, active versions, and milestone sequences. Optimistic version
locks reject stale browser state.

Acceptance atomically:

1. verifies independent authority, completeness, and submission-snapshot
   integrity;
2. marks the version `ACCEPTED`;
3. sets current and accepted family pointers;
4. moves an initial case to `MONITORING`;
5. appends the event and both log layers;
6. schedules notifications after commit.

Only the family `accepted_version_id` is the official monitoring baseline.

## 8. Events, logs, and notifications

Append-only recommendation event codes:

```text
ACTION_PLAN_CREATED
ACTION_PLAN_UPDATED
ACTION_PLAN_SUBMITTED
ACTION_PLAN_REVIEW_STARTED
ACTION_PLAN_RETURNED
ACTION_PLAN_REVISION_CREATED
ACTION_PLAN_ACCEPTED
```

Matching `cms.action-plan.*` Activity Log and Audit Trail actions retain IDs,
statuses, pointers, ownership, milestone count, actor, and controlled reasons
without copying full plan narratives into operational logs.

After-commit workflow notifications cover submission, review start, return, and
acceptance. Recipients are limited to authorized reviewers, submitter, focal
user, current monitor, and responsible-office representatives as appropriate.
No reminder or overdue notification is implemented.

## 9. API

```text
GET  /api/cms/recommendations/{recommendation}/action-plan
POST /api/cms/recommendations/{recommendation}/action-plans
GET  /api/cms/action-plans/{actionPlan}
PUT  /api/cms/action-plans/{actionPlan}/versions/{version}
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/submit
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/start-review
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/return
POST /api/cms/action-plans/{actionPlan}/versions/{version}/transitions/accept
POST /api/cms/action-plans/{actionPlan}/versions/{version}/revisions
```

Resources expose safe case context, family pointers, version lineage,
milestones, completeness, derived current/accepted/superseded state, and
actor-specific permitted actions. They do not expose storage paths,
unrestricted AEMS records, or raw confidential metadata.

The CMS recommendation detail response adds only a backward-compatible
`actionPlanSummary`.

## 10. CMS-3B React workspace

The protected route
`/compliance-management/recommendations/{caseId}/action-plan` requires
`cms.action-plan.view`. It is reached contextually from the recommendation
detail page because the backend has no Action Plan collection endpoint; no
unsupported sidebar registry is implied.

The workspace:

- preserves the recommendation, owner-office, target-date, confidentiality,
  family, current-version, and accepted-baseline context;
- supports draft narratives, focal/responsible users, plan dates, and
  add/edit/remove/reorder milestone operations;
- shows whether weighting is unused, incomplete, or totals 100%;
- submits only complete drafts and exposes start-review, return, accept, and
  revision controls from backend `availableActions` plus the matching granular
  frontend permission;
- renders every non-draft and every historical version read-only, including
  current, accepted-baseline, and superseded indicators;
- keeps the previously accepted baseline visible while a later draft is under
  preparation or review;
- sends the latest `lockVersion` with every mutation, blocks duplicate
  submissions, warns before discarding unsaved work, preserves local draft
  values after a stale-lock response, and offers an explicit authoritative
  reload; and
- presents validation details without clearing entered values, treats Laravel
  authorization as final, and uses a generic unavailable state for scoped
  `404` responses.

The responsive Playwright coverage exercises responsible-office preparation,
milestone weighting and ordering, reviewer return and acceptance, immutable
history, revision, accepted-baseline continuity, authorization failure,
optimistic-lock recovery, duplicate-click prevention, and scope-safe
unavailability on desktop and mobile.

## 11. CMS-4A management-reported progress

### 11.1 Boundary and baseline

CMS-4A records what management reports and the evidence management supplies.
`RECORDED` means the submission passed completeness review and was recorded for
follow-up; it is not an independent validation or an implementation
conclusion. A reported 100% is exposed as
`reportedCompleteAwaitingValidation`, never as `implemented`.

Progress creation requires a `MONITORING` or `PARTIALLY_IMPLEMENTED` case and
the current accepted Action Plan Version. Every family stores that exact
accepted version. Historical
updates remain pinned when a later plan revision is accepted; a new update must
use the new accepted baseline. Submission rejects a draft if its baseline is no
longer current.

### 11.2 Family, versions, and reporting periods

`cms_progress_updates` is the stable family for one case/reporting period.
Reporting periods require start and end dates, cannot predate baseline
acceptance, cannot overlap another family for the case, and are fixed after
creation. No monthly or quarterly frequency is assumed.

`cms_progress_update_versions` follows:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → RECORDED
                              └→ RETURNED → new DRAFT revision
RECORDED → new DRAFT correction revision
```

Only `DRAFT` is editable. Current and recorded pointers are distinct; a
previously recorded version remains authoritative until its correction is
recorded. Older recorded versions derive supersession without changing their
stored `RECORDED` status. The recommendation case remains `MONITORING`
throughout.

### 11.3 Milestone progress and calculations

Each version reports against milestone IDs and immutable milestone snapshots
from its exact accepted Action Plan Version. Submission requires exactly one
entry for every accepted milestone. Allowed management-reported states are
`NOT_STARTED`, `IN_PROGRESS`, `REPORTED_COMPLETED`, `DELAYED`, and `ON_HOLD`.

For weighted baselines, the server calculates:

```text
sum(reported milestone percentage × accepted milestone weight) / 100
```

The result uses half-up rounding to two decimal places and cannot be overridden
by the client. Unweighted baselines require an overall management-reported
percentage and are not automatically averaged.

### 11.4 Evidence

Evidence uploads create a private Core `Document` and immutable
`DocumentVersion`; CMS stores the exact document-version ID, checksum, and
effective confidentiality snapshot. The stricter of recommendation and
requested document confidentiality applies. CMS never copies files to a
separate evidence repository or follows a document's later current-version
pointer.

Progress above 0% requires milestone evidence or a no-evidence explanation.
`REPORTED_COMPLETED` requires evidence. The whole update requires evidence or a
general explanation. These checks assess completeness only, not sufficiency,
reliability, or effectiveness. A draft link may be marked removed without
deleting the Core document; submitted and historical links are immutable.

### 11.5 Authority, permissions, and controls

Responsible-office actions require matching office, case visibility, usable
account, and the matching `cms.progress.*` or `cms.evidence.*` permission.
Review, return, and recording require the active Compliance Monitor or CIAS
Management, independent from the responsible office, preparer, submitter, and
accepted-plan focal user. Technical administrator roles receive no
professional recording authority.

Permissions are:

```text
cms.progress.view
cms.progress.create
cms.progress.update
cms.progress.submit
cms.progress.review
cms.progress.return
cms.progress.record
cms.progress.revise
cms.evidence.view
cms.evidence.upload
cms.evidence.download
cms.evidence.remove_draft
```

Legacy `cms.*` compatibility permissions remain unchanged and do not bypass
the new granular permissions or scope.

Transactions, row locks, `lockVersion`, unique period/version/active-slot
constraints, immutable submission snapshots, append-only events, Activity Log,
Audit Trail, and after-commit notifications protect every transition.
Notifications explicitly describe recorded progress as management-reported
and not independently validated.

### 11.6 API

```text
GET    /api/cms/recommendations/{recommendation}/progress-updates
POST   /api/cms/recommendations/{recommendation}/progress-updates
GET    /api/cms/progress-updates/{progressUpdate}
PUT    /api/cms/progress-updates/{progressUpdate}/versions/{version}
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/submit
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/start-review
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/return
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/record
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/revisions
POST   /api/cms/progress-updates/{progressUpdate}/versions/{version}/evidence
GET    /api/cms/progress-evidence/{evidence}/download
DELETE /api/cms/progress-evidence/{evidence}
```

Recommendation detail and dashboard responses add backward-compatible
management-reported summaries.

## 12. CMS-4B React workspace

The frontend exposes Progress Updates from the recommendation-specific Detail
Workspace and does not create a global Progress Update registry. The protected
routes are:

```text
/compliance-management/recommendations/{recommendationId}/progress-updates
/compliance-management/recommendations/{recommendationId}/progress-updates/{progressUpdateId}
```

The list displays reporting period, current version/status, accepted baseline,
reported percentage, evidence count, recorded-version indicators, and the
server-authorized create action. The detail workspace provides Overview,
Milestone Progress, Evidence, and Versions & History regions. Historical
versions are read-only; only a current `DRAFT` version exposes the editor.

The editor preserves accepted-baseline milestone wording, owners, dates, and
weights. Weighted progress is presented as the server-calculated
management-reported result; unweighted progress remains management-entered and
is not averaged. A reported 100% is shown as “awaiting independent validation,”
never as “Implemented.”

Evidence uploads use the existing Core-backed multipart contract and protected
CMS download route. Confidentiality options come from the Core Document API.
Draft evidence links may be removed with a reason; submitted and historical
links have no removal control. Internal storage paths and generated filenames
are never shown.

Submit, start-review, return, record, and revision dialogs use the version's
server-provided `availableActions`, current `lockVersion`, and existing
permission helpers. Stale locks, changed baselines, 403/404/409/422 responses,
validation errors, uncertain network results, loading, empty, and retry states
are presented without silently resending a mutation.

CMS-4B remains a presentation layer for management-reported information. It
does not add independent validation, implementation conclusions, extensions,
escalation, closure, accepted-risk decisions, reopening, reports, exports, AIS,
or ARMIS integration.

## 13. CMS-5A independent validation

### 13.1 Professional boundary and lineage

Management prepares the Action Plan, reports progress, and supplies evidence.
A Compliance reviewer records completeness only. A separately assigned
Primary Validator performs procedures and proposes a conclusion; an eligible
CIAS supervisory reviewer independently returns or finalizes it. Neither a
recorded update nor management-reported 100% establishes implementation.

One `cms_validation_reviews` family pins the exact recommendation case,
accepted Action Plan Version, Progress Update family, and current recorded
Progress Update Version. A recorded version may be used by only one review.
Only one review is active per case. Historical records are never remapped to a
new plan, progress report, evidence file, validator, or conclusion.

The review owns:

- immutable `cms_validation_versions`;
- milestone/recommendation `cms_validation_items`;
- management/validator `cms_validation_evidence_assessments`;
- exact Core-backed `cms_validation_evidence_links`; and
- ended, never overwritten, `cms_validation_assignments`.

Derived display codes use
`VAL-CMS-REC-{zero-padded case ID}-{sequence}-V{version}`.

### 13.2 Statuses and case transitions

Validation Version lifecycle:

```text
DRAFT → SUBMITTED → UNDER_REVIEW → FINALIZED
                              └→ RETURNED → new DRAFT revision
```

Only `DRAFT` is editable. Submission snapshots the source recommendation,
accepted milestones, recorded management progress, exact evidence checksums,
Validation Items, assessments, professional narratives, validator, and
proposed conclusion. Submitted, under-review, returned, and finalized versions
are immutable. Only a returned current version may create a revision.

Starting a review changes `MONITORING` or `PARTIALLY_IMPLEMENTED` to
`FOR_VALIDATION`. Finalization applies only:

| Final conclusion | Case state |
| --- | --- |
| `NOT_IMPLEMENTED` | `MONITORING` |
| `INADEQUATE_BASIS` | `MONITORING` |
| `PARTIALLY_IMPLEMENTED` | `PARTIALLY_IMPLEMENTED` |
| `IMPLEMENTED` | `IMPLEMENTED` |

`IMPLEMENTED` is independently validated implementation, not closure.
Progress reporting resumes after `NOT_IMPLEMENTED`, `INADEQUATE_BASIS`, or
`PARTIALLY_IMPLEMENTED`; it is blocked during `FOR_VALIDATION` and while
`IMPLEMENTED`.

### 13.3 Validation Items and evidence assessment

The server initializes one milestone Validation Item for every milestone in
the pinned accepted baseline and associates the exact milestone-progress row
when available. Submission requires complete criterion, procedure,
population/source, result, and item conclusion for every accepted milestone.
Cross-baseline and duplicate milestone items are rejected.

Item conclusions are `SATISFIED`, `PARTIALLY_SATISFIED`, `NOT_SATISFIED`,
`INADEQUATE_BASIS`, and `NOT_APPLICABLE`.

Every exact management evidence link is assessed without modifying it.
Validator-obtained evidence creates a private Core `Document` and immutable
`DocumentVersion`, pins its version/checksum/classification, and receives its
own assessment. Assessment controls are relevance, reliability, sufficiency,
relied-upon state, summary, and limitation. Relied-upon evidence cannot remain
`NOT_ASSESSED`; evidence not relied upon requires an explanation. Draft
validator links may be marked removed without deleting the Core document.

### 13.4 Conclusions and supervisory control

Professional conclusions are only `NOT_IMPLEMENTED`,
`PARTIALLY_IMPLEMENTED`, `IMPLEMENTED`, and `INADEQUATE_BASIS`. The service
rejects obvious contradictions: implemented work cannot contain a partial,
unsatisfied, inadequate-basis, or materially insufficient relied-upon result;
partial implementation requires both established progress and remaining work;
not implemented cannot contradict all-satisfied milestones; inadequate basis
requires a documented limitation and inadequate item or evidence basis.

Finalization requires an `UNDER_REVIEW` immutable submission, confirmation,
comment, current locks, complete evidence assessment, valid source lineage,
and an independent supervisor. Changing the validator's proposal requires an
explicit override reason. Version finalization, review pointer, case state,
event, Activity Log, Audit Trail, and notifications commit atomically.

### 13.5 Independence, scope, permissions, and API

The Primary Validator must be active, unlocked, professionally permitted,
outside the responsible office, and not the plan preparer/focal
user/submitter, progress preparer/submitter/recorder, or current Compliance
Monitor. The supervisor must be CIAS Management with the exact professional
permission and cannot be the validator, responsible-office user, or source
participant. Technical administration and the legacy `cms.validate` code do
not bypass these rules.

Permissions:

```text
cms.validation.view
cms.validation.create
cms.validation.assign
cms.validation.update
cms.validation.submit
cms.validation.review
cms.validation.return
cms.validation.finalize
cms.validation.revise
cms.validation-evidence.view
cms.validation-evidence.upload
cms.validation-evidence.download
cms.validation-evidence.remove_draft
```

Routes:

```text
GET|POST /api/cms/recommendations/{recommendation}/validations
GET      /api/cms/validations/{validation}
GET|POST /api/cms/validations/{validation}/assignments
POST     /api/cms/validations/{validation}/assignments/{assignment}/end
PUT      /api/cms/validations/{validation}/versions/{version}
POST     /api/cms/validations/{validation}/versions/{version}/transitions/submit
POST     /api/cms/validations/{validation}/versions/{version}/transitions/start-review
POST     /api/cms/validations/{validation}/versions/{version}/transitions/return
POST     /api/cms/validations/{validation}/versions/{version}/transitions/finalize
POST     /api/cms/validations/{validation}/versions/{version}/revisions
POST     /api/cms/validations/{validation}/versions/{version}/evidence
GET      /api/cms/validation-evidence/{evidence}/download
DELETE   /api/cms/validation-evidence/{evidence}
```

All mutations use camelCase payloads and the latest `lockVersion`. Clients
cannot select case/version status, sequence/version number, baseline pointers,
actors, timestamps, or final pointers. Recommendation detail adds a
backward-compatible `validationSummary`; the dashboard adds scoped active,
awaiting-review, returned, and conclusion metrics. The CMS-5B React workspace is
operational against these contracts; its safe eligible-validator catalog gap is
documented below.

Append-only events, matching `cms.validation.*` Activity/Audit actions, and
after-commit notifications cover creation, assignment/replacement, updates,
evidence links/removal, submission, supervisory review, return, revision, and
finalization.

Explicitly deferred: target-date extension, automated reminders, escalation,
closure request/approval, accepted risk, no-longer-applicable decisions,
reopening, recurrence analysis, reports, exports, AIS, and ARMIS integration.

### 13.6 CMS-5B React validation workspace

The recommendation-scoped React workspace is available at:

```text
/compliance-management/recommendations/{recommendationId}/validations
/compliance-management/recommendations/{recommendationId}/validations/{validationId}
```

Recommendation Detail is the primary entry point; there is no global Validation
Registry. The workspace consumes the CMS-5A routes through the existing `cmsApi`
client and presents Overview, Procedures & Conclusions, Evidence Assessment,
Validator Evidence, Assignments, and Versions & History tabs. Draft narratives,
validation items, and evidence assessments are editable only when the server
returns the corresponding `availableActions`; all submitted, returned, and
finalized versions are read-only.

The UI uses only recorded Progress Update versions and Primary Validators
returned by the authorized `validation-options` endpoint. It never loads an
unrestricted User Registry. Laravel applies active/unlocked/non-archived,
professional-permission, office, source-participant, Compliance Monitor, and
confidentiality filters through the existing aggregate eligibility logic.

Submission, supervisory review, return, revision, and finalization use current
lock versions and reload after conflicts. Protected validator-evidence downloads
use authenticated Core document routes. A finalized `IMPLEMENTED` conclusion is
displayed as independently validated while closure remains pending; the React
workspace does not create closure, extensions, reminders, escalation, reopening,
reports, exports, or CMS/AIS/ARMIS integrations.

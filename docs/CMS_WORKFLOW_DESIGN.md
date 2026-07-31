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

The CMS-4B React progress workspace, independent validation, implementation
conclusions, target-date extensions, due-soon configuration, reminders,
escalation, closure, accepted risk, no-longer-applicable decisions, reopening,
and CMS reports/exports remain future scope.

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

Progress creation requires a `MONITORING` case and the current accepted Action
Plan Version. Every family stores that exact accepted version. Historical
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
management-reported summaries. No CMS-4B React page is implemented.

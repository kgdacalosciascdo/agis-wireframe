# Audit Engagement Management (AEMS)

## Governance, implementation, and acceptance contract

**Compiled module document:** AEMS-G0 through AEMS-G10E  
**Status:** accepted as the current AEMS as-built contract  
**Effective review:** 14 August 2026  
**Owner:** CIAS Management / AGIS Product Governance

This is the single module-level reference for the AEMS governance and
conformance phases. It combines the decisions, implementation controls,
workspaces, integration boundaries, and verification gates previously spread
across the AEMS-G documents. The individual phase files remain available as
historical implementation evidence and detailed appendices; they are not
separate modules.

The application retains the compatibility identifiers `AEMS`, `AEMS-*`,
`aems.*`, and legacy `aem.*`, while the descriptive display name is **Audit
Engagement Management**. Source code and tests remain authoritative when a
draft MDS/UID/DGM reference conflicts with runtime behavior.

## 1. Module responsibility and boundaries

AEMS manages an audit engagement from authorized source or special
authorization through planning, execution, findings, reporting, transfer, and
administrative closure.

| Boundary | AEMS responsibility | Other owner |
| --- | --- | --- |
| Identity and scope | Consume users, offices, roles, permissions, areas, focuses, documents, workflows, notifications, logs, configuration, and numbering | Core |
| Engagement planning source | Import one approved IAP engagement-plan source without mutating it | IAP |
| Resource authority | Consume competencies, availability, workload, assignments, and actuals through the configured provider; retain fallback/reconciliation evidence | ARMIS provider boundary; IAP interim fallback |
| Findings and recommendations | Own Issues, Findings, Recommendations, management dialogue, rejoinders, report provenance, and final snapshots | AEMS |
| Post-issuance monitoring | Transfer finalized recommendation snapshots once | CMS owns Action Plans, monitoring, validation, dispositions, reopening, and CMS closure |
| Intelligence | AEMS does not own an AIS business workflow or source mutation | AIS owns a separate read-only analytical/integration contract |

## 2. Current AEMS module surfaces

| Surface | Route | SCR/permission contract | Function |
| --- | --- | --- | --- |
| AEMS Dashboard | `/audit-engagement-management/dashboard` | `aems.engagement.view` | Scope-aware progress cards, phase counts, overdue work, evidence gaps, findings, responses, conferences, reports, transfer exceptions, closure readiness, queue indicators, and protected exports |
| Engagement Registry | `/audit-engagement-management` | SCR-210 / `aems.engagement.view` | IAP import or special engagement creation, office scope, search/filter, archive/restore, and engagement details |
| Audit Team | `/audit-engagement-management/team` | SCR-213 / `aems.team.view` | Team assignment, competency/availability/capacity, independence and conflict safeguards, ARMIS status, planned/actual person-days, amendments, and approval |
| Engagement Orders | `/audit-engagement-management/aeo` | SCR-214 / `aems.aeo.view` | AEO draft, independent review, signatures, approval, issue, distribution, acknowledgement, amendment, cancellation, void, and supersession |
| Planning Package | `/audit-engagement-management/planning-package` | SCR-221 / `aems.planning-package.view` | Preliminary survey, process flow, risk matrices/items, traceability, KPI/sampling/planned-WP readiness, review, approval, return, revision, and immutable baseline |
| Engagement Plan | `/audit-engagement-management/aep` | SCR-222 / `aems.aep.view` | Objectives, scope, criteria, period, resources, procedures, communication/effectivity, controlled review and issue |
| Audit Program | `/audit-engagement-management/audit-program` | SCR-223 / `aems.program.view` | Program/procedure register, area/focus/risk/method/criteria/planned-day fields, execution state, reviewer notes, and links |
| Execution Workspace | `/audit-engagement-management/execution` | SCR-226 / `aems.fieldwork.view` | Fieldwork records, procedure execution, timeline, tasks, due dates, reviewer notes, WP/evidence links, blockers, and issue creation |
| Entry Conferences | `/audit-engagement-management/entry-conferences` | SCR-225 child / `aems.entry-conference.view` | Entry schedule, agenda, participants, attendance, venue/online details, attachments, minutes, and acknowledgement |
| Conference Management | `/audit-engagement-management/conferences` | SCR-225 / `aems.conference.view` | Entry/exit timelines, findings discussed, agreements/disagreements, revised dates, attendance, and dialogue history |
| Working Papers & Evidence | `/audit-engagement-management/working-papers` | SCR-228 / `aems.working-paper.view` | WP index, objective/procedure/population/sample/results/conclusion, preparer/reviewer dates, cross-references, immutable versions, approval locking, and evidence links |
| Evidence Management | `/audit-engagement-management/evidence` | SCR-229 / `aems.evidence-request.view` | Evidence Request lifecycle, receipt/submission, custody/checksum/confidentiality, assessment/outcome, restrictions/gaps, versions, and traceability |
| Audit Issues | `/audit-engagement-management/issues` | SCR-230 / `aems.issue.view` | Issue review, validation, merge, referral, resolution, observation, conversion, withdrawal, dismissal, and terminal disposition |
| Findings & Recommendations | `/audit-engagement-management/findings` | SCR-240 / `aems.finding.view` | Criteria, condition, cause, conclusion, effect, risk, evidence, responsible office, management response, rejoinder, recommendation, controlled correction, finalization, and immutable snapshots |
| Auditee Responses | `/audit-engagement-management/auditee-responses` | SCR-241 / `aems.management-response.view` | Formally communicated findings only; agree/partial/disagree, comments, corrective actions, responsible personnel, target dates, attachments, clarification, extension, late, and supplemental responses |
| Exit Conferences | `/audit-engagement-management/exit-conferences` | SCR-225 child / `aems.conference.view` | Exit schedule, participants, attendance, findings, agreements/disagreements, revised targets, minutes, attachments, and acknowledgement |
| Audit Reporting Workspace | `/audit-engagement-management/reports` | SCR-250 / `aems.report.view` or `aems.report.view_issued` | Interim/draft/final assembly, section order, executive summary, review, authority decisions, signatures, transmittal, distribution, acknowledgement, amendment, withdrawal, supersession, and protected PDF |
| Operational Work Queues | `/audit-engagement-management/work-queues` | `aems.task.view` | Tasks, review notes, due process, escalation candidates, assignment, due/overdue transitions, notifications, and audited actions |
| Audit Calendar | `/audit-engagement-management/calendar` | `aems.calendar.view` | Engagement milestones, owners, dates, overdue state, and completion monitoring |
| Registers & Exports | `/audit-engagement-management/registers` | `aems.engagement.view` and export permissions | Engagement, progress, queue, records, reports, document index, and authenticated protected CSV/PDF exports |
| Records & Administrative Closure | `/audit-engagement-management/records-closure` | closure/records/retention permissions | Completion assessment, closure blockers, retention monitoring, legal holds, archive/disposition, destruction eligibility, record search, formal closure, and controlled reopening |

Process Flow and Risk Matrix are artifacts inside the SCR-221 Planning Package;
they are intentionally not duplicate sidebar pages. The SCR registry contains
32 canonical identifiers, including reserved `SCR-243`.

## 3. Governance decisions (G0)

These decisions are the professional-control contract that later phases must
follow. They are not optional UI conventions.

| Decision | Current rule |
| --- | --- |
| Authority/signatories | Preparer drafts/revises; independent reviewer assesses; approver decides; issuer/signatory releases. AEO and report authority matrices are append-only and version-bound. |
| Direct AFR | Direct Finding creation requires an authorized reason, source/authority context, engagement scope, and audit event. Normal conversion from an Issue remains supported. |
| Evidence Request | `DRAFT → SUBMITTED → SENT → ACKNOWLEDGED → PARTIALLY_RECEIVED → RECEIVED → FOR_REVIEW → ASSESSED → CLOSED`; controlled `OVERDUE`, extension, escalation, cancellation, and closed-without-submission states are separate decisions. |
| Audit Evidence | Technical states such as `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `DUPLICATE`, `SUPERSEDED`, and `VOIDED` are distinct from assessment and document lock state. `LOCKED` never means professional acceptance by itself. |
| Assessment scale | Positive/adequate dimensions, explicit confidentiality, no unresolved gaps, and approved exceptions for restrictions/limitations are required before evidence may support a validated/finalized finding. |
| Response extension | An extension needs a reason, future date, independent review, immutable event, and effective due date. Late, supplemental, and replacement responses are distinct versioned records. |
| Retention | Approved retention metadata is immutable; legal hold overrides archive/disposition; destruction eligibility is a reviewed state, not physical deletion. |
| Planning units | Risk matrices may be multiple where authorized; programs/procedures carry area, focus, process, method, criteria, sampling, planned days, and planned-WP requirements. |
| Conference waiver | Waiver authority, reason, actor, and audit reference are required; absence of a conference cannot be silently inferred. |
| Effort/provider | ARMIS is authoritative only after accepted reconciliation and provider status checks; fallback is explicit and stale/missing data blocks mandatory approval. |
| Signatures/transmittal | Signatures are authenticated in-app attestations with actor, timestamp, method/reference, version, and immutable transmittal/acknowledgement events. |
| Report distribution | IAU Head recommendation and LCE approval are recorded; required signatories, recipient decisions, delivery, acknowledgement, and confidentiality are preserved. |
| Completed vs Closed | `COMPLETED` means substantive audit work finished. `CLOSED` means formal administrative closure after authoritative records, retention, transfer, and blocker reconciliation. |
| Acceptance/change control | Governance changes require a new versioned decision, compatibility plan, updated rule/SCR/role tests, and documentation truth pass. |

## 4. Unified engagement lifecycle

```text
Approved IAP source or special/emergency authorization
  → one-office scope and AEO/team authority
  → Planning Package + AEP approval
  → Audit Program and fieldwork execution
  → Working Papers + Evidence Requests/assessment
  → Issues → Findings → management dialogue
  → Entry/Exit Conferences
  → Interim/Draft/Final reporting and distribution
  → finalized recommendation snapshot → one-time CMS transfer
  → completion assessment → COMPLETED
  → retention/records/blocker reconciliation → CLOSED
```

The aggregate lifecycle is atomic and scope-aware. Child records retain their
own versions and events. Approved/issued artifacts cannot be overwritten;
corrections create revisions or superseding records.

## 5. Phase implementation record (G0–G10E)

| Phase | Module capability consolidated | Current implementation and gate |
| --- | --- | --- |
| G0 | Governance and conformance contract | 14 professional decisions, status compatibility map, rule-to-code-to-test matrix, authority and change-control ownership published |
| G1A/G1B | Immediate professional-control hardening | Evidence eligibility enforced; immutable request/assessment versions; required Finding Conclusion; direct-Finding authority; real planning progress/KPI gates; UI removes ineligible actions |
| G2A/G2B | Foundation, scope, lifecycle, navigation | Reviewed one-office backfill/invariant, structured Area/Focus scope, limitations/source variance, special authorization, Core numbering, IAP risk discriminator, distinct COMPLETED, SCR-212 workspace |
| G3A/G3B | Planning conformance | Structured Process Flow, multiple matrices, Rule-35 risk fields, program/procedure criteria/process/method/person-days, KPI/planned-WP/sampling traceability, strict fieldwork readiness |
| G4A/G4B | AEO and team authority | Signatory matrix, AEO signatures, distribution/transmittal/acknowledgement, cancel/void/amend/supersede, stronger separation, team amendments/consequence assessment, access history |
| G5A/G5B | Complete Evidence lifecycle | Full request states, acknowledgement, extension, overdue/escalation, cancellation/no-submission closure, explicit outcomes, acquisition/source links, report links, consolidated Evidence workspace |
| G6A/G6B | Issues, AFR, dialogue, queues | Status compatibility, withdrawal and terminal dispositions, AFR delivery/acknowledgement, response extensions and late/supplemental versions, operational Tasks/Review Notes/due process/escalation actions |
| G7A/G7B | Reporting and distribution | Interim source links and final treatment, Issue/WP/Evidence traceability, IAU Head/LCE decisions, signatories/transmittals, reproducible protected exports, administrative report closure |
| G8A/G8B | Records, calendar, closure hardening | Retention/archive/disposition/legal-hold controls, destruction review, Audit Calendar/milestones, complete closure blockers, records search and monitoring, distinct COMPLETED/CLOSED |
| G9 | Verification and documentation truth | Rule/SCR/role matrices, duplicate-route check, mutation/download/responsive coverage, migration rehearsal, documentation corrections |
| G10A/G10B | Dashboard and frontend conformance | Scope-aware backend dashboard, responsive hoverable cards, phase/status filters, work queues, notification/empty/error/unauthorized states, extended taxonomy/criteria traceability |
| G10C | Operational queues and output surfaces | Dedicated Tasks, Review Notes, Due Process, Escalation Candidates, Calendar, Registers/Exports routes with actions and responsive layouts |
| G10D | Records and administrative closure surface | Dedicated records/closure workspace with retention, legal hold, archive/disposition, blockers, and reopening history |
| G10E | Governance and final acceptance | Final status map, semantic Rule 1–35 tests, 32 SCR checks, 6-role navigation matrix, acceptance record and documentation truth pass |

## 6. Professional controls that apply everywhere

- Every API is authenticated and permission-protected. Engagement, office,
  assignment, confidentiality, and role scope are checked by Laravel services
  and policies; React visibility is not the security boundary.
- Separation of duties prevents preparers from independently reviewing,
  approving, validating, issuing, or finalizing their own professional work.
- Current optimistic lock versions are required for mutable transitions.
- Approved/issued/finalized versions are immutable. Revisions preserve the
  superseded version and record correction/amendment reasons.
- Core `document_versions` is authoritative for checksum, file size, MIME type,
  custody, confidentiality, version lineage, and protected authenticated
  downloads. Public document/export URLs are not exposed.
- Material mutations create Activity Log entries, Audit Trail events,
  workflow events, and notifications where the module contract requires them.
- Automation may identify readiness, create reminders/candidates, or prepare a
  draft; it may not make final professional decisions, close a record, reopen a
  record, or issue an escalation notice automatically.

## 7. API/data and test references

The complete payload and endpoint inventory is in [API and Data Reference](API_AND_DATA_REFERENCE.md). The implementation authorities are:

```text
Frontend routes/navigation  src/App.jsx, src/config/navigation.js
Backend routes              backend/routes/api.php
Validation                  backend/app/Http/Requests
Business rules              backend/app/Services and Policies
Persistence                 backend/app/Models and migrations
Defaults                    backend/database/seeders
Backend verification        backend/tests/Feature/Api/Aems*Test.php
Browser verification        tests/e2e/aems-*.spec.js
```

The final acceptance evidence is:

- semantic Rule 1–35 runtime acceptance tests;
- 32 canonical SCR registry checks, including reserved SCR-243;
- six seeded role navigation/contextual-tab matrices;
- authenticated protected-download and mutation checks;
- migration rehearsal and full Feature regression suites; and
- desktop/mobile AEMS conformance suites.

The exact command results and the limitation that the broad Playwright matrix
was stopped at the user's request are recorded in [AEMS G10E Final Acceptance](AEMS_G10E_FINAL_ACCEPTANCE.md).

## 8. Compatibility and non-goals

- Do not rename `AEMS`, `AEMS-*`, `aems.*`, or legacy `aem.*` identifiers.
- Do not merge or remove `iap_risk_assessments` or
  `iap_universe_risk_assessments` without a new governance decision.
- The standalone AFR route is a compatibility/navigation entry; AEMS owns the
  operational Findings and Recommendations workspace.
- AIS is outside the current AEMS implementation scope.
- Historical phase documents are retained for auditability, but this compiled
  module document and the source/tests define the current status.

## 9. Detailed phase appendices

The following documents contain the original phase-level implementation notes,
API details, and focused verification records:

- [AEMS-G0 Governance and Conformance Contract](AEMS_G0_GOVERNANCE_CONFORMANCE_CONTRACT.md)
- [AEMS-G1 Professional Controls](AEMS_G1_PROFESSIONAL_CONTROLS.md)
- [AEMS-G2 Foundation, Scope, Lifecycle](AEMS_G2_FOUNDATION_SCOPE_LIFECYCLE.md)
- [AEMS-G3 Planning Conformance](AEMS_G3_PLANNING_CONFORMANCE.md)
- [AEMS-G4 AEO and Team Authority](AEMS_G4_AEO_TEAM_AUTHORITY.md)
- [AEMS-G5 Evidence Lifecycle](AEMS_G5_EVIDENCE_LIFECYCLE.md)
- [AEMS-G6 Issues, AFR, Dialogue](AEMS_G6_ISSUES_AFR_DIALOGUE.md)
- [AEMS-G7 Reporting and Distribution](AEMS_G7_REPORTING_DISTRIBUTION.md)
- [AEMS-G8 Records, Calendar, Closure](AEMS_G8_RECORDS_CALENDAR_CLOSURE.md)
- [AEMS-G9 Verification and Truth](AEMS_G9_VERIFICATION_AND_TRUTH.md)
- [AEMS-G10A Backend Conformance](AEMS_G10A_BACKEND_CONFORMANCE.md)
- [AEMS-G10B Frontend Conformance](AEMS_G10B_FRONTEND_CONFORMANCE.md)
- [AEMS-G10C Operational Queues and Outputs](AEMS_G10C_OPERATIONAL_QUEUES_OUTPUTS.md)
- [AEMS-G10D Records and Administrative Closure](AEMS_G10D_RECORDS_ADMINISTRATIVE_CLOSURE.md)
- [AEMS-G10E Final Acceptance](AEMS_G10E_FINAL_ACCEPTANCE.md)

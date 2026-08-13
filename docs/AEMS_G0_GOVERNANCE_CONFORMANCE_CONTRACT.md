# AEMS-G0 Governance and Conformance Contract

## 1. Purpose, authority, and scope

This document resolves the open professional and interoperability decisions
identified in **MDS-200 AEM v0.8**, **UID-200 v0.5**, and **DGM-200 v0.2**. It is
the AGIS implementation and acceptance contract for AEMS. Runtime behavior is
still determined by the source code and tests; compatibility exceptions are
listed explicitly in the status map and rule matrix rather than left implicit.

The reference documents are draft/review artefacts. This contract is the
project-level decision record that prevents a later implementation phase from
inventing a different authority, status, retention, evidence, or reporting
policy. A decision may be changed only through a new, versioned governance
decision and a documented compatibility plan.

The source code and automated tests remain the as-built authority. Therefore,
each decision below identifies both the target rule and the compatibility
behavior that remains in the current release. AEMS compatibility identifiers
(`AEMS`, `aems.*`, and `aem.*`) are retained.

**Effective date:** 13 August 2026  
**Contract version:** G0.1  
**Owner:** CIAS Management / AGIS Product Governance  
**Implementation scope:** governance, conformance, and test contract only;
no schema, route, permission, or operational workflow change is included.

## 2. Resolved decision register

### G0-01 — Authority and signatory matrix

The following matrix is the minimum authority separation for professional
decisions. “Preparer” may draft and revise only. “Reviewer” performs the
independent assessment. “Approver” records the final professional decision.
“Issuer/signatory” authorizes release to an external recipient.

| Record or action | Preparer | Independent reviewer | Approver | Issuer/signatory | Required separation |
| --- | --- | --- | --- | --- | --- |
| Engagement source and AEO | Team Leader or Engagement Supervisor | Assigned Reviewer | CIAS Management authorized audit authority | CIAS Management designated issuance authority | Preparer, reviewer, approver, and issuer are distinct users. |
| AEP, Planning Package, and Audit Program | Team Leader or assigned auditor | Reviewer | CIAS Management authorized audit authority | Not externally issued; approved baseline is the authority | Preparer cannot approve the same version. |
| Team safeguards and ARMIS authority | Engagement Supervisor | Independent safeguard reviewer | Designated CIAS/ARMIS resource authority | Not applicable | The person declaring a conflict cannot approve its mitigation. |
| Working Paper | Assigned Auditor | Reviewer | Reviewer with approval permission | Not externally issued | Preparer cannot review or approve the same version. |
| Issue and Finding validation | Assigned Auditor/Finding author | Independent Finding Reviewer | CIAS Management validation authority | Not applicable until report communication | Finding author cannot validate the finding. |
| AFR communication and finalization | Finding team | Independent AFR Reviewer | CIAS Management AFR authority | Designated CIAS Management signatory | AFR issuance is independent from preparation. |
| Management Response | Auditee Representative | Assigned Auditor/Reviewer | Auditee office authority for its response | Not applicable | The auditor cannot author the auditee response. |
| Auditor Rejoinder and dialogue finalization | Assigned Auditor/Reviewer | Engagement Supervisor or independent reviewer | CIAS Management dialogue authority | Not applicable | Auditee author cannot approve the rejoinder. |
| Interim/Final Report | Report preparer | Quality Reviewer | CIAS Management report authority | CIAS Management designated signatory | Preparer and reviewer cannot approve; issuer cannot be the preparer. |
| Report distribution | Distribution officer | Quality/records reviewer | CIAS Management distribution authority | Same designated signatory as the approved report, or a separately delegated signatory | Recipient, method, delivery, and acknowledgement are recorded per version. |
| Completion and formal closure | Completion assessor | Independent closure reviewer | CIAS Management closure authority | Not externally issued | Completion assessor cannot approve closure. |
| Controlled reopening | Reopening requester | Independent reviewer | CIAS Management reopening authority | Not applicable | Original closure decision is never replaced. |
| CMS transfer | AEMS transfer officer/service | Transfer reconciliation reviewer for exceptions | No new professional decision; transfer is permitted only from an issued report | Not applicable | Transfer is idempotent and cannot create or alter a recommendation decision. |

An authenticated user identity, authority basis, decision date/time, reason,
and exact version/document hash are recorded for every approval, issuance,
waiver, distribution, closure, and reopening decision. A permission grants the
ability to perform an action; it does not by itself grant professional
authority.

### G0-02 — Direct AFR policy

The normal path is **Issue → Finding → AFR**. A Finding may be created without
an Issue only when one of these controlled reasons is selected:

1. `URGENT_OR_MATERIAL_RISK` — delay in creating an Issue would expose a
   material or urgent risk;
2. `FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT` — a law, regulator, CIAS directive,
   or formally approved request requires a direct AFR; or
3. `CROSS_CUTTING_OR_SYSTEMIC_MATTER` — the matter spans issues or offices and
   a single authoritative finding is more traceable than duplicate Issues.

Direct AFR creation requires an immutable authorization record containing the
reason, factual basis, directing authority, date, affected engagement/area,
and independent reviewer. The finding must still contain all required
criteria, condition, cause, conclusion, effect/significance, risk, evidence,
and recommendation fields. “No Issue was convenient” is not an authorized
reason. A direct finding cannot be self-authored and validated.

### G0-03 — Evidence Request statuses and lifecycle

The canonical future lifecycle is:

```text
DRAFT → FOR_REVIEW → SENT → ACKNOWLEDGED →
PARTIALLY_RECEIVED → RECEIVED → ASSESSED → CLOSED
```

The following exception states are controlled, not automatic professional
decisions:

- `OVERDUE` is a due-date condition recorded when a complete receipt is not
  recorded by the due date;
- `EXTENDED` is an approved target-date extension and preserves the original
  due date;
- `ESCALATED` is a reviewable escalation candidate or approved escalation
  record, never an automatic notice;
- `CANCELLED` requires an authorized reason before complete receipt; and
- `CLOSED_WITHOUT_SUBMISSION` requires a documented authority decision and
  records why no submission will be expected.

The current `SUBMITTED` value is the compatibility representation of
`FOR_REVIEW`. Existing `PARTIALLY_RECEIVED`, `RECEIVED`, `ASSESSED`, and
`CLOSED` values retain their meaning. A request is not `ASSESSED` merely because
files were received; every received exact document version must have an
eligible assessment or an approved exception.

### G0-04 — Audit Evidence statuses

Professional evidence status is separate from Core document storage and
checksum state. The canonical evidence status set is:

```text
REGISTERED → FOR_ASSESSMENT → ACCEPTED
                         ├→ LIMITED
                         ├→ ADDITIONAL_REQUIRED
                         ├→ REJECTED
                         ├→ DUPLICATE
                         └→ SUPERSEDED
VOIDED (controlled terminal state)
```

The current `DRAFT` maps to `REGISTERED`, and `VERIFIED` maps to
`FOR_ASSESSMENT` because technical file verification is not professional
acceptance. Current `LOCKED` means the record/file is immutable; it must not be
interpreted as `ACCEPTED` without a professional assessment. Current `VOIDED`
remains `VOIDED`. Core `document_versions` continues to own checksum, file
size, MIME type, version, custody, confidentiality, and protected download
controls.

### G0-05 — Evidence assessment scale and eligibility

Each applicable assessment dimension uses exactly one of:

| Value | Meaning | Final-finding eligibility |
| --- | --- | --- |
| `NOT_ASSESSED` | No professional conclusion has been recorded | Always blocks |
| `INADEQUATE` | The dimension does not meet the evidence requirement | Blocks |
| `PARTIAL` | The dimension is only partly met | Blocks unless a separate authorized exception explicitly accepts the limitation and compensating corroboration |
| `ADEQUATE` | The dimension is acceptable for the stated purpose | May pass that dimension |
| `NOT_APPLICABLE` | The dimension genuinely does not apply | Requires written assessor rationale and independent review; prohibited for a mandatory dimension |

The scale applies to sufficiency, appropriateness, relevance, reliability,
competence, accuracy, completeness, corroboration, contradiction,
authenticity, integrity, and confidentiality. For contradiction,
`ADEQUATE` means no unresolved contradiction remains. For confidentiality,
`ADEQUATE` means the classification and access controls are sufficient.

An evidence version is eligible for a finalized finding only when it links to
the exact Core `document_versions` row, is current, has an assessment in the
`ASSESSED` family state, has no unresolved gaps/limitations/contradictions,
has all mandatory dimensions `ADEQUATE`, and satisfies restriction/exception
rules. `NOT_ASSESSED`, `INADEQUATE`, or `PARTIAL` evidence cannot silently pass
the gate.

Legacy rating values are read compatibly as `YES → ADEQUATE`, `NO →
INADEQUATE`, `PARTIAL → PARTIAL`, and `NOT_ASSESSED → NOT_ASSESSED` until the
assessment schema is migrated.

### G0-06 — Management-response extension and no-response policy

The communication snapshot sets the original management-response due date;
that date is never overwritten. An auditee office may request:

- one ordinary extension of up to **15 calendar days**; and
- one exceptional extension of up to **15 additional calendar days** for a
  documented force-majeure, legal, or materially complex circumstance.

Requests should be submitted before the due date. A late request is allowed
only with a recorded reason and CIAS Management approval. The auditor may
recommend an extension but cannot approve the auditee's extension request.
Each approved/rejected extension is an immutable version with requester,
assessor, authority, reason, old date, new date, and decision timestamp.

Reminder and due-process timing is: reminder three business days before the
date, due notice on the date, first non-response notice after five business
days, and final non-response decision after ten business days. A no-response
decision does not fabricate agreement, close the finding, or bypass the
existing dialogue/finalization workflow.

### G0-07 — Retention and records periods

The default AGIS retention schedule is measured from the formal engagement
`CLOSED` decision (or from the final disposition for cancelled engagements):

| Record class | Minimum retention |
| --- | --- |
| Engagement identity, AEO/AEP, Planning Package, Audit Program, Working Papers, Evidence metadata/files, Issues, Findings, Responses, Conferences, Reports, CMS transfer manifests, completion and reopening records | 10 years |
| Issued Final Reports, formal Closure Decisions, Reopening Decisions, legal-hold records, and immutable audit/Activity Trail needed to prove those decisions | Permanent |
| Superseded/returned drafts and report versions | 10 years, unless placed on legal hold |
| Reminder, notification, and transient queue records not part of the decision trail | 3 years |

Legal hold, litigation, statutory schedules, or a formally approved records
schedule always overrides the default and may extend retention. Retention does
not authorize deletion: archive, destruction eligibility, approval, execution,
and the immutable disposition event are separate records-management actions.

### G0-08 — Risk Matrix and Audit Program per Area

The default planning unit is the **Audit Area**:

- each approved Planning Package version has one or more Risk Matrices as
  needed, with at least one matrix for every in-scope Audit Area;
- a matrix belongs to one Audit Area and may cover multiple Audit Focuses;
- a Risk Matrix Item has one primary Audit Area and at least one Audit Focus;
  cross-area risks are represented by linked items or an explicit cross-area
  relationship, never by an unowned item;
- each Audit Area has one approved Audit Program baseline per Planning Package
  version; revisions create a new immutable program version;
- each procedure has one primary Audit Area and Audit Focus, with explicit
  links for any additional focus; and
- a shared procedure is allowed only when every covered Area/Focus is linked,
  the expected evidence and criteria are explicit, and area-specific
  ownership is preserved.

Process Flow documents follow the same Area/Focus scope and are revisioned.
Imported IAP risks remain source lineage; AEMS Risk Matrices and Programs are
engagement-specific planning artifacts and cannot mutate IAP records.

### G0-09 — Conference waiver authority

An Entry Conference waiver must be approved by the CIAS Management authorized
audit authority before fieldwork begins. An Exit Conference waiver must be
approved by the CIAS Management closure/report authority before final report
approval. A Team Leader, Reviewer, or Auditee Representative cannot approve
their own waiver. Each waiver records reason, authority, date, affected scope
or findings, compensating communication, and expiry/validity. Emergency or
special engagements use the same rule unless a separate written special
authority explicitly names the waiver authority.

### G0-10 — Effort boundary and ARMIS authority

AEMS records engagement planned and actual person-days; ARMIS is authoritative
only in `ARMIS_AUTHORITATIVE` mode after its independent reconciliation and
authority decision. `IAP_INTERIM_FALLBACK` and `ARMIS_SHADOW` are visible,
non-authoritative modes. Planned effort covers approved engagement work and
procedures. Actual effort covers recorded engagement work, fieldwork, review,
and report/closure effort; leave, training, and unrelated administration are
excluded unless explicitly configured as a separate category. Completion
requires reconciliation of AEMS and the active provider. Missing or stale
provider data blocks approval; it never causes an automatic provider switch.

### G0-11 — Signature, transmittal, and acknowledgement method

The authoritative signature is an authenticated AGIS electronic approval. The
system stores signer, role, authority basis, UTC timestamp, exact record
version, and SHA-256 of the signed Core Document Version. A wet signature or
external signature file may be attached as supporting evidence but does not
replace the AGIS decision record.

The default transmittal is a protected, authenticated AGIS portal download.
Email may notify a recipient but may not be the authoritative delivery of a
confidential report or evidence file. Every transmittal receives a unique
identifier and immutable recipient snapshot, method, delivery timestamp,
acknowledgement/rejection decision, actor, and exact version/hash. Public file
URLs are prohibited.

### G0-12 — Report distribution authority

The final report quality reviewer records a recommendation. The CIAS
Management authorized distribution authority records the final decision. The
signatory matrix must identify the IAU Head recommendation (where applicable),
the LCE/Presiding Officer or delegated CIAS authority decision, and the
authorized signatory. A report cannot be marked issued until the authority,
recipient list, confidentiality classification, transmittal method, and exact
PDF checksum are recorded.

### G0-13 — Completed versus Closed

`COMPLETED` means the audit work is substantively finished: approved objectives
and procedures are executed or formally waived, Working Papers and evidence
are finalized, Issues/Findings/AFRs and responses are dispositioned, reports
and required CMS transfer reconciliation are complete, person-days and
retention/index records are reconciled, and lessons learned are recorded.
The engagement may still be awaiting the formal closure decision.

`CLOSED` means CIAS Management has approved the formal Closure Decision after
the completion assessment. The aggregate and ordinary child records are
locked, retention/records controls are active, and any reopening requires a
new immutable Reopening Decision that preserves the original closure.

The current release stores `COMPLETED` as a distinct aggregate status and only
the formal Closure workflow may move an engagement to `CLOSED`. No client may
treat `CLOSED` as mere management-reported completion.

### G0-14 — Acceptance authority and change control

The G10E acceptance gate is owned by CIAS Management and the AGIS product
owner. A release is accepted only when the semantic Rule 1–35 suite, SCR and
role/navigation suite, migration rehearsal, full Feature suite, lint/build,
protected-download checks, and desktop/mobile Playwright projects have exact
recorded results. A compatibility alias, legacy status, or deferred AIS
integration is not an unresolved governance decision. Any change to this
contract requires a versioned decision, an updated compatibility map, and
regression evidence before deployment.

## 3. Status compatibility map

G0 defines semantic compatibility; it does not rename existing database
values. Later migrations may add canonical values only with a backfill,
read/write compatibility, and regression tests.

| Contract concept | Canonical G0 meaning | Current as-built value(s) | Compatibility rule |
| --- | --- | --- | --- |
| Engagement review | `FOR_REVIEW` | `RETURNED_FOR_REVISION` and stage-specific review | Keep aggregate status; expose review stage/action separately. |
| Engagement completed | `COMPLETED` | `COMPLETED` | Completion assessment and aggregate transition establish substantive completion; formal Closure is still required for `CLOSED`. |
| AEO/AEP review | `FOR_REVIEW`, `RETURNED`, `APPROVED`, `ISSUED`, `SUPERSEDED` | `PENDING_REVIEW`, `RETURNED_FOR_REVISION`, `APPROVED`, `ISSUED`, `SUPERSEDED` | Map labels only; preserve stored codes. |
| Audit Program | `DRAFT`, `FOR_REVIEW`, `APPROVED`, `ACTIVE`, `COMPLETED`, `SUPERSEDED` | Same except `PENDING_REVIEW` represents `FOR_REVIEW` | Preserve current code. |
| Working Paper | `DRAFT`, `SUBMITTED`, `RETURNED`, `RESUBMITTED`, `APPROVED`, `SUPERSEDED`, `VOIDED` | Same, with `RETURNED_FOR_REVISION` | UI label may say Returned; code remains compatible. |
| Evidence Request | `DRAFT`, `FOR_REVIEW`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `OVERDUE`, `EXTENDED`, `ESCALATED`, `ASSESSED`, `CLOSED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION` | `DRAFT`, `SUBMITTED`, `SENT`, `ACKNOWLEDGED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `FOR_REVIEW`, `ASSESSED`, `OVERDUE`, `EXTENSION_REQUESTED`, `EXTENDED`, `ESCALATED`, `CANCELLED`, `CLOSED_WITHOUT_SUBMISSION`, `CLOSED` | `SUBMITTED` remains the read-compatible alias for `FOR_REVIEW`; `EXTENSION_REQUESTED` is the explicit pending-extension state. |
| Audit Evidence | `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `SUPERSEDED`, `DUPLICATE`, `VOIDED` | Technical `DRAFT`, `VERIFIED`, `LOCKED`, `VOIDED` plus professional `outcome` values | `LOCKED` is technical immutability; only `outcome=ACCEPTED` satisfies the final-finding gate. |
| Issue | `DRAFT`, `FOR_REVIEW`, `OPEN`, `UNDER_EVALUATION`, `DISPOSED`, `WITHDRAWN` | `DRAFT`, `SUBMITTED`, `VALIDATED`, `DISMISSED`, `CONVERTED_TO_FINDING` plus dispositions | Preserve disposition history; add projections only through a controlled migration. |
| Finding/AFR | `DRAFT`, `PENDING_REVIEW`, `VALIDATED`, `COMMUNICATED`, `AWAITING_MANAGEMENT_RESPONSE`, `UNDER_DIALOGUE`, `FINALIZED`, `WITHDRAWN`, `SUPERSEDED` | Same | `FINALIZED` remains the only CMS/reporting source state. |
| Management response | `DRAFT`, `SUBMITTED`, `UNDER_AUDITOR_REVIEW`, `CLARIFICATION_REQUESTED`, `RESUBMITTED`, `DIALOGUE_FINALIZED` | Same | Extensions are separate immutable decisions, not status overwrites. |
| Report | `DRAFT`, `PENDING_REVIEW`, `RETURNED`, `RESUBMITTED`, `APPROVED`, `ISSUED`, `SUPERSEDED`, `WITHDRAWN`, `ADMINISTRATIVELY_CLOSED` | `DRAFT`, `PENDING_REVIEW`, `RETURNED_FOR_REVISION`, `RESUBMITTED`, `APPROVED`, `ISSUED`, `SUPERSEDED`, `WITHDRAWN`, `ADMINISTRATIVELY_CLOSED` | `RETURNED_FOR_REVISION` is the current `RETURNED` code; administrative closure is a report-family state and never mutates an issued version. |
| Conference | `DRAFT/SCHEDULED`, `HELD`, `ACKNOWLEDGED`, `COMPLETED`, `WAIVED`, `CANCELLED` | Entry and Exit conference-specific status sets | Conference-specific codes remain; waiver authority follows G0-09. |

## 4. Rule-to-code-to-test conformance matrix

The matrix is the acceptance index for later phases. “Partial” means the
current source protects part of the rule but does not yet implement the G0
decision. “Gap” means the required field, state, authority, or test must be
added. Test paths are existing tests unless marked **required**.

| MDS rule | Current code anchor | Existing protection / required test | G0 status |
| --- | --- | --- | --- |
| 1. Exactly one Office | `AemsEngagementRegistryService`, `AuditEngagement`, office pivot migration | `AemsEngagementRegistryTest.php`, `AemsFoundationContractTest.php`, `AemsFoundationG2Test.php`, G10E semantic row | Implemented |
| 2. Area belongs to Office | `AemsAccessService`, engagement area relations | `AemsAccessControlTest.php`, G10E semantic row | Implemented |
| 3. Focus belongs to Area | engagement focus relations and access scopes | `AemsFoundationG2Test::test_scope_rejects_focus_outside_the_selected_area`, G10E semantic row | Implemented |
| 4. Approved IAP or authorized unplanned source | `AemsEngagementRegistryService`, source validation | `AemsCrossModuleIntegrationTest.php`, `AemsEngagementRegistryTest.php` | Implemented |
| 5. Source linked and not overwritten | IAP gateway and `source_snapshot` | `AemsCrossModuleIntegrationTest.php` | Implemented |
| 6. Objectives/coverage/boundaries/limitations/variance | `AuditEngagement`, SCR-212 structured scope service | `AemsFoundationG2Test`, G10E semantic row | Implemented |
| 7. Required team roles before AEO | team service and AEO guards | `AemsTeamAeoTest.php`, `AemsEngagementLifecycleTest.php` | Implemented |
| 8. Competency/capacity/conflict/safeguards | team safeguard and ARMIS provider services | `AemsTeamSafeguardTest.php`, `AemsIntegrationBoundaryTest.php`, G10E semantic row | Implemented |
| 9. Team validation is not fieldwork authority | aggregate transition service | `AemsEngagementLifecycleTest.php` | Implemented |
| 10. Team changes require authority/reason/date/consequence/history | team update/history models | `AemsTeamSafeguardTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 11. Fieldwork authority/planning/Entry or waiver/emergency | `AemsEngagementTransitionService` | `AemsFieldworkRecordTest.php`, `AemsEngagementLifecycleTest.php`, G10E semantic row | Implemented |
| 12. Exit before final unless waived | report/closure transition guards | `AemsReportTest.php`, `AemsCompletionClosureTest.php`, G10E semantic row | Implemented |
| 13. Material work/conclusions in WPs | `WorkingPaper`, fieldwork/WP links | `AemsWorkingPaperEvidenceTest.php` | Implemented |
| 14. Completed procedure has conclusion/WP/disposition | `AemsFieldworkService`, `AuditProgramProcedure` | `AemsFieldworkRecordTest.php`, `AemsAepProgramTest.php` | Implemented |
| 15. Requests and evidence are distinct | `AemsEvidenceRequest`, `AuditEvidence`, link model | `AemsEvidenceRequestTest.php`, `AemsWorkingPaperEvidenceTest.php` | Implemented |
| 16. Receipt is not acceptance | `AemsEvidenceRequestService` | `AemsEvidenceRequestTest.php`, `AemsG5EvidenceLifecycleTest.php`, G10E semantic row | Implemented |
| 17. Evidence assessed and fully traceable | evidence assessment/link services | `AemsEvidenceRequestTest.php`, `AemsG5EvidenceLifecycleTest.php`, G10E semantic row | Implemented |
| 18. No uncontrolled evidence alteration | Core `document_versions`, evidence/WP version services | `AemsWorkingPaperEvidenceTest.php`, `AemsEvidenceRequestTest.php`, G10E semantic row | Implemented |
| 19. Issue history/disposition/conversion preserved | `AemsFindingService`, Issue disposition models | `AemsIssueFindingRecommendationTest.php` | Mostly implemented |
| 20. Issue→AFR normal; direct AFR authorized | `AemsFindingService::createFinding` | `AemsIssueFindingRecommendationTest.php`, G10E semantic row | Implemented |
| 21. Complete Finding structure | finding request/service | `AemsIssueFindingRecommendationTest.php`, G10E semantic row | Implemented |
| 22. Full finding/recommendation traceability | finding, WP, evidence, fieldwork links | `AemsIssueFindingRecommendationTest.php`, G10A conformance tests, G10E semantic row | Implemented |
| 23. Management comments do not overwrite findings | response/rejoinder services | `AemsIssueFindingRecommendationTest.php` | Implemented |
| 24. Corrective action belongs in response | management response model/service | `AemsIssueFindingRecommendationTest.php`; **required:** response-action boundary test | Mostly implemented |
| 25. Interim source and final treatment linked | report assembly/service | `AemsReportTest.php`, G10E semantic row | Implemented |
| 26. Final report uses finalized findings only | report selection guard | `AemsReportTest.php` | Implemented |
| 27. Distribution authority/signatory/transmittal/ack | report distribution service | `AemsReportTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 28. Only issued recommendations transfer to CMS | CMS intake and completion transfer | `CmsIntakeTest.php`, `AemsCompletionClosureTest.php` | Implemented |
| 29. Controlled revision/version/audit trail | version families and AEMS support events | AEMS API tests and `AemsFoundationContractTest.php` | Mostly implemented |
| 30. Completion assessment coverage | `AemsCompletionAssessmentService` | `AemsCompletionClosureTest.php` | Implemented |
| 31. Completed/closed protected | closure service and locked records | `AemsCompletionClosureTest.php`, `AemsFoundationG2Test.php`, G10D Playwright, G10E semantic row | Implemented |
| 32. Significant changes require approval/audit | transition/event/audit services | `AemsFoundationTest.php`, `AemsNotificationTest.php`, `AemsG4AuthorityTest.php`, G10E semantic row | Implemented |
| 33. Engagement/procedure/finding criteria traceable | AEP/program/finding payloads and `audit_finding_procedure` | `AemsAepProgramTest.php`, `AemsIssueFindingRecommendationTest::test_finding_exposes_explicit_procedure_criteria_traceability` | Implemented |
| 34. Planning package complete before fieldwork | planning service and transition gates | `AemsPlanningPackageTest.php`, `AemsEngagementLifecycleTest.php`, G10E semantic row | Implemented |
| 35. Rule-35 Risk Item/Program fields and links | `AemsRiskMatrixItem`, `AuditProgram`, `AuditProgramProcedure` | `AemsPlanningPackageTest.php`, `AemsFoundationG2Test.php`, G10E semantic row | Implemented |

The matrix distinguishes runtime enforcement from compatibility labels. The
G10E semantic acceptance suite executes every row; a compatibility alias is
reported as an alias and is never treated as a second professional decision
path.

## 5. Compatibility and implementation rules

1. No G0 decision permits a browser-only enforcement. Backend authorization,
   transactions, optimistic locking, Activity Log, Audit Trail, and immutable
   versions remain mandatory.
2. Existing `aem.*` compatibility permissions, legacy columns, and coexisting
   IAP risk tables are preserved. A compatibility alias is not a second
   professional decision path.
3. A status migration must be additive, backfillable, dual-readable during
   rollout, and covered by old-record and new-record tests. No status is renamed
   in place.
4. Every exact evidence/report file reference uses a Core
   `document_versions` identifier and checksum; public download URLs are never
   introduced.
5. Automation may calculate due/overdue conditions, reminders, or reviewable
   candidates. It may not approve evidence, validate findings, issue reports,
   close engagements, or make a direct AFR decision.
6. AEMS owns engagement execution and transfer provenance. IAP owns approved
   plan/risk source records, ARMIS owns authoritative resource data only after
   its authority gate, CMS owns post-transfer monitoring/validation/closure,
   and AIS remains outside this contract.

## 6. G0 gate and final acceptance order

G0 is complete when:

- all fourteen MDS open decisions are recorded above;
- the status compatibility map is accepted by backend and frontend owners;
- every MDS rule has a code anchor, runtime protection, and a semantic
  acceptance test;
- retention, signature/transmittal, direct AFR, waiver, and distribution
  authority are no longer implicit; and
- this contract is linked from the AEMS README and implementation baseline.

The historical implementation order after this documentation gate was:

1. **G1 professional-control corrections:** evidence eligibility and immutable
   assessment versions, mandatory Finding Conclusion, direct-AFR authority,
   and the hard-coded planning/KPI gate.
2. **G2 foundation and lifecycle conformance:** database one-office invariant,
   structured Area/Focus scope, team amendment authority, special/emergency
   waiver handling, and explicit `COMPLETED` versus `CLOSED`.
3. **G3 planning conformance:** Area/Focus Process Flow fields, multiple Risk
   Matrices, Rule-35 Risk Item fields, Program/Procedure criteria and process
   links, and planning UI/readiness tests.
4. **G4 authority and evidence lifecycle:** AEO/report signatory and
   transmittal records, complete Evidence Request/Evidence status states,
   extensions/overdue/escalation handling, and direct report traceability.
5. **G5 issues, dialogue, reporting, and records:** Issue status projections,
   response extensions/late submissions, operational task/review queues,
   interim/final lineage, distribution authority, archive/disposition, and
   calendar/milestone outputs.
6. **G6 verification and release hardening:** complete rule/SCR/role matrix,
   backend Feature tests, focused Playwright journeys, fresh migration/seed,
   lint/build, protected-download checks, and documentation reconciliation.

Those dependencies have now been exercised through G10E. AIS remains outside
the AEMS scope, and the compatibility identifiers and both IAP risk systems
remain intentionally preserved.

## 7. Verification record

The G0 contract was initially documentation-only. G10E is the final acceptance
checkpoint and records the current semantic Rule/SCR/role matrix and complete
regression results in `docs/AEMS_G10E_FINAL_ACCEPTANCE.md`.

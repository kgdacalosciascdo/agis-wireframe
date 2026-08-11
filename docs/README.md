# AGIS Documentation

This directory is the documentation entry point for the implemented AGIS Core
and Internal Audit Planning modules and the approved design baseline for AEMS.

## Document map

| Document | Audience | Contents |
| --- | --- | --- |
| [System Flow](SYSTEM_FLOW.md) | Product owners, developers, reviewers | End-to-end browser, API, database, files, authorization, logging, configuration, and IAP flow |
| [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md) | Core administrators, analysts, developers | Authentication, registries, roles/scopes, master lists, documents, workflows, notifications, logs, configuration |
| [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md) | CIAS management, auditors, analysts, developers | SIAP, Audit Universe, risk, prioritization, annual plan, schedules, resources, approval, reports |
| [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md) | CIAS management, auditors, auditee representatives, developers | Engagement authorization, planning, fieldwork, findings, responses, reporting, and closure state machines |
| [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md) | Responsible offices, Compliance Monitors, validators, CIAS management, developers | Immutable intake, Action Plans, progress reporting, independent validation, professional conclusions, and future boundaries |
| [API and Data Reference](API_AND_DATA_REFERENCE.md) | Frontend/backend developers and integrators | Endpoint families, request conventions, entities, relationships, lists, configuration keys |
| [Operations Guide](OPERATIONS_GUIDE.md) | Developers, deployers, administrators | Setup, migrations, seeders, storage, SMTP, verification, production, backup, monitoring, troubleshooting |
| [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md) | New users, testers, reviewers, and acceptance teams | Step-by-step Core, IAP, AEMS, CMS, security, integrity, responsive, and automated acceptance testing |
| [Development Standards](DEVELOPMENT_STANDARDS.md) | Everyone changing the system | Security, integrity, UX, reliability, testing, and definition of done |

## Current module status

| Module | Documentation status | Implementation status |
| --- | --- | --- |
| AGIS Core | As-built documentation complete | Implemented |
| IAP — Internal Audit Planning | As-built documentation complete | Implemented |
| AEMS — Audit Engagement Monitoring System | As-built workflow, data, access, integration, Completion Assessment, Closure, retention, final-index, reopening, notification, and test boundaries documented | Operational through formal approved Completion Assessment, authoritative Closure review, atomic `CLOSED`, immutable final document index, interim retention/custody metadata, lessons learned, and exceptional controlled reopening |
| AFR — Audit Findings and Recommendations | Placeholder routes only | Not implemented |
| CMS — Compliance Management | CMS-1 through CMS-7A documented | Immutable intake, scoped React dashboard/registry/detail, monitor assignment, Action Plans, versioned progress, independent validation, target-date extensions, and the CMS-7A escalation backend are operational; the CMS-7B React workspace and closure remain deferred |
| ARMIS — Audit Resource Management | ARMIS-0 through ARMIS-6B documented | Resource registry, competency/certification, planning, assignment, conflict/capacity, actuals, immutable reports/exports, responsive reports/administration workspace, provider adapter, immutable reconciliation, independent review, authority activation, and rollback are operational; AIS integration remains deferred |
| AIS — Audit Intelligence System | Placeholder routes only | Not implemented |

Documentation must not describe placeholder modules as operational.

ARMIS current-state correction: [ARMIS Workflow and Implementation
Checkpoint](ARMIS_WORKFLOW_DESIGN.md) records the verified interim provider
boundary, ARMIS-1A resource foundation, ARMIS-1B resource registry workspace,
ARMIS-2A competency/certification backend, ARMIS-2B competency workspace,
ARMIS-3A availability/capacity/workload planning backend, ARMIS-3B planning
workspace, ARMIS-4A assignment/actuals backend, and ARMIS-4B assignment/actuals
workspace.
Competency claims now have controlled submission, independent verification,
exact Core Document Version evidence, immutable revisions, Activity Log/Audit
Trail records, Core notifications, and responsive registry/detail pages.
ARMIS-3A now provides controlled availability, annual capacity, planned
workload, utilization read models, revision lineage, independent review,
locking, optimistic concurrency, notifications, and Core audit records.
ARMIS-5A now provides immutable scope-pinned reports, protected CSV/PDF
exports, administration status, notification counts, and operational
hardening. ARMIS-5B now provides the protected responsive reports and
administration workspace. ARMIS-6A provides the read-only ARMIS provider
adapter and fallback/shadow boundary. ARMIS-6B provides immutable provider
reconciliation, independent review, authority activation, and rollback. AIS
integration remains deferred. Existing
`arms.view` and `arms.manage` compatibility permissions remain in place.

ARMIS-7A and ARMIS-7B are also verified: ARMIS-7A covers the complete
backend/browser security gate, and ARMIS-7B covers read-only migration
preflight and Render deployment hardening. ARMIS-7C adds the read-only
post-deployment Render smoke verifier. AIS integration remains deferred.

CMS current-state correction: CMS-1 through CMS-12B are operational and
verified. This supersedes the older CMS summary row above, which predates the
CMS-7B through CMS-12B increments. AIS and ARMIS provider integration remain
deferred.

## Recommended reading order

For a new developer:

1. [System Flow](SYSTEM_FLOW.md)
2. [Development Standards](DEVELOPMENT_STANDARDS.md)
3. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
4. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
5. [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md)
6. [API and Data Reference](API_AND_DATA_REFERENCE.md)
7. [CMS Workflow Design](CMS_WORKFLOW_DESIGN.md)
8. [Operations Guide](OPERATIONS_GUIDE.md)
9. [End-to-End Testing Guide](END_TO_END_TESTING_GUIDE.md)
10. [ARMIS Workflow and Implementation Checkpoint](ARMIS_WORKFLOW_DESIGN.md)

For a CIAS reviewer:

1. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
2. [AEMS Workflow Design](AEMS_WORKFLOW_DESIGN.md)
3. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
4. [System Flow](SYSTEM_FLOW.md)

## Documentation maintenance

Documentation is part of the definition of done. A behavior change must update:

- the relevant workflow document;
- API/data reference if routes, payloads, entities, lists, or configuration change;
- System Flow if a cross-cutting request, security, file, notification, or logging
  path changes;
- Operations Guide if deployment, migration, seeding, storage, email, backup, or
  testing changes.

Use current code and tests as the implementation source of truth:

- frontend routes: `src/App.jsx`;
- navigation/permissions: `src/config/navigation.js`;
- API client: `src/services/api.js`;
- backend routes: `backend/routes/api.php`;
- business rules: `backend/app/Services` and Form Requests;
- persistence: `backend/app/Models` and `backend/database/migrations`;
- defaults: `backend/database/seeders`;
- verification: `backend/tests/Feature`.

CMS-6A/6B target-date extensions are implemented as an approval-controlled
backend and recommendation-specific React workspace with immutable request
versions, exact evidence links, protected downloads, approval decisions, and
append-only effective-date history. CMS-7A adds the backend escalation workflow
with formal notice, acknowledgement, response review, follow-up, and resolution
records; CMS-7B provides the protected recommendation-scoped React workspace,
including notice, acknowledgement, response, evidence, follow-up, and
resolution views. CMS-9A now adds the accepted-risk and no-longer-applicable
disposition backend and CMS-9B React workspace. CMS-10A now adds the controlled
reopening backend and CMS-10B now provides its protected React workspace.
CMS-11A/B scheduled automation and the CMS-12A protected report/export backend
are implemented; the CMS-12B React reports workspace remains deferred. ARMIS
resource, competency, ARMIS-3A planning backend, ARMIS-3B planning workspace,
ARMIS-4A/4B assignment and actuals, ARMIS-5A report/export backend,
ARMIS-5B reports/administration workspace, ARMIS-6A provider adapter, and
ARMIS-6B reconciliation/authority gate are operational; AIS integration remains
deferred.

## Diagram rendering

## CMS-8B verification status

CMS-8A closure backend and the CMS-8B recommendation-scoped React workspace are
implemented. The workspace preserves backend readiness, authorization,
optimistic locking, immutable lineage, Core-version evidence links, controlled
review/decision actions, and closed-case read-only behavior. CMS-9A disposition
backend controls accepted-risk and no-longer-applicable decisions. CMS-10A
controlled reopening and its React workspace are backend/frontend verified;
CMS-11A/B automation and the CMS-12A report/export backend are implemented;
CMS-12B React reporting remains deferred. ARMIS-3A/3B planning, ARMIS-4A
assignment/actuals backend, ARMIS-4B assignment/actuals workspace,
ARMIS-5A report/export backend, and the ARMIS-5B reports/administration
workspace are implemented; AIS and ARMIS provider integration remain deferred.

The workflow documents use Mermaid diagrams. GitHub and compatible Markdown
viewers render them automatically. In a viewer without Mermaid support, the
adjacent headings, tables, and numbered rules remain the authoritative text.
# CMS-8A status

Recommendation Closure Request, independent review, final decision, immutable snapshots, Core evidence links, and closed-case guards are implemented in the backend. The React closure workspace (CMS-8B), CMS-9A/B dispositions, and CMS-10A/B controlled reopening workspace are implemented. Reopening is a separate authorized workflow; automatic closure, reporting, and AIS integrations remain deferred. ARMIS-3A/3B planning, ARMIS-4A assignment/actuals backend, ARMIS-4B assignment/actuals workspace, ARMIS-5A reports/exports, and the ARMIS-5B reports/administration workspace are operational while the ARMIS provider and AIS integrations remain deferred.

CMS-8B frontend status

The recommendation-scoped Closure Request workspace, readiness presentation, source lineage, evidence controls, review/decision actions, version history, Recommendation Detail entry point, dashboard closure metrics, and responsive browser coverage are implemented. The client preserves backend authorization and optimistic-lock handling.

## CMS-9A status

Accepted-Risk and No-Longer-Applicable request families, immutable versions,
independent assessments, separate CIAS Management decisions, exact Core
Document Version evidence links, readiness checks, case transitions,
permissions, events, Activity Log/Audit Trail entries, notifications, APIs, and
Recommendation Detail/dashboard contracts are implemented and backend-tested.
CMS-10A controlled reopening and CMS-10B React workspace are implemented and
verified. Scheduled automation and closure-readiness candidates are implemented;
the CMS-12A report/export backend is implemented, while CMS-12B React reporting
remains. ARMIS-3A/3B planning, ARMIS-4A assignment/actuals backend, ARMIS-4B
assignment/actuals workspace, ARMIS-5A reports/exports, and ARMIS-5B
reports/administration workspace are implemented; AIS and ARMIS provider
integration remain deferred.

## CMS-9B status

The recommendation-scoped React Dispositions workspace is implemented at
`/compliance-management/recommendations/:recommendationId/dispositions` and its
request detail route. It supports backend readiness, Accepted-Risk and
No-Longer-Applicable draft narratives, immutable workflow presentation,
protected Core-version evidence links/downloads, review and decision panels,
returned revisions, terminal banners, Recommendation Detail integration, and
CMS dashboard-compatible backend metrics. The current CMS-9A Resource does not
return historical versions; the UI does not fabricate them. CMS-10A reopening
backend and CMS-10B reopening UI are implemented; CMS-11A/B automation and the
CMS-12A report/export backend are implemented, with CMS-12B React reporting,
AIS and ARMIS provider integration remain deferred.

CMS-11A adds scheduled reminder processing, closure-readiness detection,
reviewable closure candidates, overdue escalation candidates, and versioned
automation rules in the backend. CMS-11B adds the protected Automation &
Candidate Review React workspace, including rule administration, run history,
and candidate acknowledgement/dismissal. CMS-11B is verified with 66/66 CMS
desktop/mobile browser tests, 177 Feature tests, lint, and the production
build. Automated processing is deliberately limited to reminders and
reviewable drafts; it cannot make final professional decisions or issue
notices. CMS-12A provides the protected report/export backend and CMS-12B
provides the protected React reports workspace; both are verified. AIS and
ARMIS-3A/3B planning, ARMIS-4A assignment/actuals backend, ARMIS-4B
assignment/actuals workspace, ARMIS-5A reports/exports, and the ARMIS-5B
reports/administration workspace are operational; ARMIS provider integration
remains deferred.

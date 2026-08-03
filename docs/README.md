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
| [Development Standards](DEVELOPMENT_STANDARDS.md) | Everyone changing the system | Security, integrity, UX, reliability, testing, and definition of done |

## Current module status

| Module | Documentation status | Implementation status |
| --- | --- | --- |
| AGIS Core | As-built documentation complete | Implemented |
| IAP — Internal Audit Planning | As-built documentation complete | Implemented |
| AEMS — Audit Engagement Monitoring System | As-built workflow, data, access, integration, Completion Assessment, Closure, retention, final-index, reopening, notification, and test boundaries documented | Operational through formal approved Completion Assessment, authoritative Closure review, atomic `CLOSED`, immutable final document index, interim retention/custody metadata, lessons learned, and exceptional controlled reopening |
| AFR — Audit Findings and Recommendations | Placeholder routes only | Not implemented |
| CMS — Compliance Management | CMS-1 through CMS-7A documented | Immutable intake, scoped React dashboard/registry/detail, monitor assignment, Action Plans, versioned progress, independent validation, target-date extensions, and the CMS-7A escalation backend are operational; the CMS-7B React workspace and closure remain deferred |
| ARMIS — Audit Resource Management | Temporary capacity exists in IAP | Full module not implemented |
| AIS — Audit Intelligence System | Placeholder routes only | Not implemented |

Documentation must not describe placeholder modules as operational.

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
resolution views. Automatic escalation, reminders, recommendation closure,
accepted risk, reopening, reporting, exports, AIS, and ARMIS remain deferred.

## Diagram rendering

## CMS-8B verification status

CMS-8A closure backend and the CMS-8B recommendation-scoped React workspace are
implemented. The workspace preserves backend readiness, authorization,
optimistic locking, immutable lineage, Core-version evidence links, controlled
review/decision actions, and closed-case read-only behavior. Accepted-risk,
alternative dispositions, reopening, reporting, exports, AIS, and ARMIS remain
deferred.

The workflow documents use Mermaid diagrams. GitHub and compatible Markdown
viewers render them automatically. In a viewer without Mermaid support, the
adjacent headings, tables, and numbered rules remain the authoritative text.
# CMS-8A status

Recommendation Closure Request, independent review, final decision, immutable snapshots, Core evidence links, and closed-case guards are implemented in the backend. The React closure workspace (CMS-8B), reopening, accepted-risk, no-longer-applicable, automatic closure, reporting, AIS, and ARMIS integrations remain deferred.

CMS-8B frontend status

The recommendation-scoped Closure Request workspace, readiness presentation, source lineage, evidence controls, review/decision actions, version history, Recommendation Detail entry point, dashboard closure metrics, and responsive browser coverage are implemented. The client preserves backend authorization and optimistic-lock handling.

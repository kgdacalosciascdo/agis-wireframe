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
| CMS — Compliance Management | CMS-1 through CMS-6A documented | Immutable intake, scoped React dashboard/registry/detail, monitor assignment, Action Plans, versioned management-reported progress, independent validation, and the CMS-6A target-date extension backend are operational; the CMS-6B extension workspace, escalation, and closure remain unimplemented |
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

CMS-6A target-date extensions are implemented as a backend workflow with
immutable request versions, exact evidence links, approval decisions, and
append-only effective-date history. The frontend extension workspace is
deferred to CMS-6B; reminders, escalation, and formal closure remain separate
future increments.

## Diagram rendering

The workflow documents use Mermaid diagrams. GitHub and compatible Markdown
viewers render them automatically. In a viewer without Mermaid support, the
adjacent headings, tables, and numbered rules remain the authoritative text.

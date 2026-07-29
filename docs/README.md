# AGIS Documentation

This directory is the documentation entry point for the implemented AGIS Core and
Internal Audit Planning modules.

## Document map

| Document | Audience | Contents |
| --- | --- | --- |
| [System Flow](SYSTEM_FLOW.md) | Product owners, developers, reviewers | End-to-end browser, API, database, files, authorization, logging, configuration, and IAP flow |
| [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md) | Core administrators, analysts, developers | Authentication, registries, roles/scopes, master lists, documents, workflows, notifications, logs, configuration |
| [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md) | CIAS management, auditors, analysts, developers | SIAP, Audit Universe, risk, prioritization, annual plan, schedules, resources, approval, reports |
| [API and Data Reference](API_AND_DATA_REFERENCE.md) | Frontend/backend developers and integrators | Endpoint families, request conventions, entities, relationships, lists, configuration keys |
| [Operations Guide](OPERATIONS_GUIDE.md) | Developers, deployers, administrators | Setup, migrations, seeders, storage, SMTP, verification, production, backup, monitoring, troubleshooting |
| [Development Standards](DEVELOPMENT_STANDARDS.md) | Everyone changing the system | Security, integrity, UX, reliability, testing, and definition of done |

## Current module status

| Module | Documentation status | Implementation status |
| --- | --- | --- |
| AGIS Core | As-built documentation complete | Implemented |
| IAP — Internal Audit Planning | As-built documentation complete | Implemented |
| AEM — Audit Engagement Management | Placeholder routes only | Not implemented |
| AFR — Audit Findings and Recommendations | Placeholder routes only | Not implemented |
| CMS — Compliance Management | Placeholder routes only | Not implemented |
| ARMIS — Audit Resource Management | Temporary capacity exists in IAP | Full module not implemented |
| AIS — Audit Intelligence System | Placeholder routes only | Not implemented |

Documentation must not describe placeholder modules as operational.

## Recommended reading order

For a new developer:

1. [System Flow](SYSTEM_FLOW.md)
2. [Development Standards](DEVELOPMENT_STANDARDS.md)
3. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
4. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
5. [API and Data Reference](API_AND_DATA_REFERENCE.md)
6. [Operations Guide](OPERATIONS_GUIDE.md)

For a CIAS reviewer:

1. [IAP Workflow Design](IAP_WORKFLOW_DESIGN.md)
2. [AGIS Core Workflow Design](CORE_WORKFLOW_DESIGN.md)
3. [System Flow](SYSTEM_FLOW.md)

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

## Diagram rendering

The workflow documents use Mermaid diagrams. GitHub and compatible Markdown
viewers render them automatically. In a viewer without Mermaid support, the
adjacent headings, tables, and numbered rules remain the authoritative text.

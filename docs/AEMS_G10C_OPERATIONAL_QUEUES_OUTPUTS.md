# AEMS-G10C Operational Queues and Output Surfaces

## Scope

G10C exposes the existing AEMS-7A/7B and G8 calendar contracts as dedicated,
responsive workspaces. It does not duplicate workflow state or bypass the
backend authorization rules.

## Frontend workspaces

| Workspace | Route | Backend contract |
| --- | --- | --- |
| Operational Work Queues | `/audit-engagement-management/work-queues` | `/api/aems/engagements/{engagement}/work-queue` |
| Audit Calendar and Milestones | `/audit-engagement-management/calendar` | `/api/aems/engagements/{engagement}/calendar` |
| Registers and Protected Exports | `/audit-engagement-management/registers` | dashboard, document-index, records, and report endpoints |

Operational Work Queues has dedicated tabs for Tasks, Review Notes,
Due Process, and Escalation Candidates. Each tab uses the selected engagement
scope and displays status, due/overdue state, linked records, actors, and
version/lock information returned by the API.

## Controls

- Task assignment is limited to active engagement participants or authorized
  global users. Office and engagement scope are checked by the service.
- Task transitions require the current optimistic lock version. Terminal tasks
  cannot be edited; reopening is an explicit audited action.
- Review notes remain draft-editable only by their author. Finalization requires
  an independent actor and creates an immutable audit/event record.
- Due-process entries remain append-only. Follow-up reminders and clarification
  requests create new exchanges linked to the original finding.
- Escalation candidates are reviewable prompts. Acknowledgement, resolution,
  and dismissal require role permission, engagement scope, a comment, and the
  current lock version. No notice is issued automatically.
- Calendar milestones use the Core activity/audit infrastructure and current
  lock version for mutations. Overdue indicators are derived from authoritative
  dates.
- CSV/PDF/document-index downloads are authenticated protected endpoints. No
  public export URL is introduced.

## Permissions

The existing seeded permissions remain authoritative:

`aems.task.view`, `aems.task.create`, `aems.task.update`,
`aems.task.complete`, `aems.task.cancel`, `aems.task.reopen`,
`aems.task.escalate`, `aems.review-note.view`, `aems.review-note.create`,
`aems.review-note.update`, `aems.review-note.finalize`,
`aems.due-process.view`, `aems.due-process.create`,
`aems.escalation-candidate.view`, `aems.escalation-candidate.review`,
`aems.escalation-candidate.resolve`, `aems.escalation-candidate.dismiss`,
`aems.calendar.view`, `aems.calendar.manage`, and the existing report/export
permissions.

The React routes use the least broad page gate (`aems.task.view`,
`aems.calendar.view`, or `aems.engagement.view`) and hide mutations when the
individual action permission is absent. Laravel remains the final authority.

## Output surfaces

The Registers and Protected Exports page links the engagement registry,
operational queues, calendar, report workspace, progress CSV, work-queue CSV,
and document-index CSV. Report PDFs/CSVs continue to be downloaded through the
existing protected AEMS report endpoints.

## Verification contract

The G10C gate is met when the dedicated workspaces are reachable for each
authorized role, empty/loading/error states are usable on desktop and mobile,
mutations refresh from the authoritative API, and existing queue/calendar
feature tests plus frontend lint/build and route checks remain green.

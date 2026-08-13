# AEMS-G6 — Issues, AFR, Dialogue, and Queues

Implemented as an additive contract over the existing AEMS-6 through AEMS-7
workflows. Legacy issue statuses remain compatible: `DISMISSED` is retained for
existing disposition rows while `statusCompatibility.canonical` maps
`SUBMITTED` to `FOR_REVIEW`, `VALIDATED` to `UNDER_EVALUATION`, and terminal
rows to `DISPOSED`/`WITHDRAWN`; `disposition`/`terminalDisposition` identifies
the precise professional terminal decision. New controlled withdrawal uses `WITHDRAWN`
and requires an independent actor, a reason, engagement scope, and the current
lock version.

## AFR communication

Formal Finding communication creates an immutable `aems_finding_transmittals`
snapshot with the exact Finding revision, evidence/working-paper references,
confidentiality, method, response due date, sender, and recipient register.
Recipient delivery and acknowledgement are controlled transitions. Each event
is append-only and records actor, timestamp, content, and status change. The
API exposes protected transmittal creation and recipient delivery/acknowledgement
routes; no public download URL is introduced.

## Response extensions and late/supplemental dialogue

Management responses retain immutable version history and now carry a response
kind (`ORIGINAL`, `LATE`, `SUPPLEMENTAL`, `REPLACEMENT`), extension request and
approval metadata, effective due date, late reason, and supplemental reason.
Auditee representatives may request an extension; an independent reviewer may
approve or reject it. Submission after the effective due date requires a late
reason. Supplemental responses are separate current records and do not replace
the original response. Extension, late, and supplemental events are recorded in
the immutable due-process stream. Response kind cannot be changed while editing
a draft; a supplemental or late exchange must be created through its own
controlled record path.

## Queues and professional controls

The existing operational Task, Review Note, due-process, and escalation
services remain the single work-queue implementation. G6 actions use the same
engagement scope, office restrictions, separation-of-duties checks,
optimistic-lock checks, notifications, Activity Log, Audit Trail, and immutable
version conventions as the preceding phases.
Task assignment additionally requires an active engagement team assignment (or
an explicitly global account), and an assigned office must be in the engagement
scope and match the assigned user's office. Auditee acknowledgement is limited
to the responsible office and the named recipient user/office on the exact AFR
transmittal.

Focused verification is covered by `AemsG6IssuesDialogueContractTest`, the
existing issue/finding/recommendation feature suite, frontend lint/build, and
the AEMS issue/dialogue browser suite.

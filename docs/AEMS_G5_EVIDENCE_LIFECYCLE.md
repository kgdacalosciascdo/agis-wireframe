# AEMS-G5 — Complete Evidence Lifecycle

Implementation status: backend and consolidated Evidence Management workspace are implemented.

## Evidence Request lifecycle

`DRAFT → SUBMITTED → SENT → ACKNOWLEDGED → PARTIALLY_RECEIVED → RECEIVED → FOR_REVIEW → ASSESSED → CLOSED`

Control states are available when applicable: `OVERDUE`, `EXTENSION_REQUESTED`, `EXTENDED`, `ESCALATED`, `CANCELLED`, and `CLOSED_WITHOUT_SUBMISSION`.

- Acknowledgement is restricted to the requested custodian user/office (or an authorized internal participant).
- Extensions are requested with a reason and future due date, then independently approved.
- Overdue and escalation actions are recorded with actor, timestamp, reason, status transition, and immutable request-event history.
- Cancellation and no-submission closure require an explicit reason. No-submission closure is not ordinary assessed closure.
- Request and receipt records use optimistic locking. Request versions and request lifecycle events are append-only.

## Evidence outcomes and professional eligibility

Every current Evidence record has an explicit outcome: `REGISTERED`, `FOR_ASSESSMENT`, `ACCEPTED`, `LIMITED`, `ADDITIONAL_REQUIRED`, `REJECTED`, `DUPLICATE`, `SUPERSEDED`, or `VOIDED`.

An Evidence record may support a validated or finalized Finding only when:

1. the current Evidence version is verified or locked;
2. the exact current Core `document_versions` row is cited;
3. a current immutable professional assessment exists;
4. all assessment dimensions are positive/adequate, with no unresolved gap, limitation, restriction, or contradiction; and
5. the explicit outcome is `ACCEPTED`, or `LIMITED` with an independently approved exception.

Negative or incomplete assessments are retained for audit history but cannot be accepted for reporting. Evidence acceptance through the API also rechecks the assessment independently.

## Traceability

Evidence captures acquisition method/form, planning objective, risk-matrix item, and control reference. Evidence can be linked to an exact `audit_report_versions` row through the protected report-link endpoint. Issued/locked report versions cannot have links changed.

## API contract

- `GET /api/aems/engagements/{engagement}/evidence-requests` — consolidated requests, evidence, assessments, statuses, and lifecycle events.
- `POST /api/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/transition` — all request transitions; requires `lockVersion`, and extension requests additionally require `extensionDueDate`.
- `POST /api/aems/engagements/{engagement}/evidence/{evidence}/transition` — checksum verification, explicit outcome, and void actions.
- `POST /api/aems/engagements/{engagement}/evidence/{evidence}/report-links` — link accepted evidence to an unlocked report version.

All actions use scoped AEMS permissions, separation-of-duties checks where professional decisions are made, Core activity/audit events, and notifications.

The seeded operational permission contracts include `aems.evidence.outcome`,
`aems.evidence.link_report`, `aems.evidence-request.acknowledge`,
`aems.evidence-request.extend`, `aems.evidence-request.extension_approve`,
`aems.evidence-request.overdue`, `aems.evidence-request.escalate`, and
`aems.evidence-request.cancel`. Auditee representatives retain view and
acknowledgement compatibility access without receiving internal assessment,
outcome, or closure permissions.

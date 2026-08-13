# AEMS-G8 — Records, calendar, and closure hardening

G8 completes the operational records controls around AEMS closure while
preserving Core `documents` and immutable `document_versions`. AEMS records a
controlled archive or disposition decision; it does not physically delete a
Core document from these endpoints.

## Retention and disposition states

`engagement_retention_records` now has append-only operational state in addition
to its approved retention snapshot:

- `archive_status`: `ACTIVE`, `ARCHIVED`, or `DISPOSITION_RECORDED`;
- `legal_hold_flag`, release time, actor, and reason;
- legal-hold release reference where an external authority supplies one;
- `destruction_eligibility_status`: `NOT_REVIEWED`, `ELIGIBLE`, or
  `NOT_ELIGIBLE`;
- review and disposition timestamps, actors, reasons, and references.

Every archive, legal-hold release, destruction review, and disposition record
is preserved in `aems_record_disposition_actions`. These rows are immutable and
include actor, timestamp, reason, before/after state, reference, and a
retention snapshot. A legal hold blocks archive and disposition. Destruction
review reports the reasons for ineligibility; a disposition record can only be
entered after an approved, eligible review with no active hold. The disposition
endpoint records the authorized external/provider reference and does not remove
files.

## Closure blocker register

The authoritative closure checklist now blocks unresolved legal holds and
overdue required Audit Calendar milestones. Existing document-index,
retention-approval, transfer, reporting, evidence, dialogue, and completion
guards remain atomic in `AemsEngagementTransitionService`. `COMPLETED` remains
the substantive completion state and `CLOSED` remains the subsequent formal
administrative closure state; neither state is silently collapsed into the
other.

## Audit Calendar

`aems_engagement_milestones` stores milestone code, category, dates, owner,
required flag, related record, status, lock version, and actor history. A
completed milestone is immutable. The calendar API calculates open, overdue,
and completed totals, and milestone transitions use optimistic locking.

## Protected API contract

- `GET /aems/engagements/{engagement}/records?q=...`
- `GET /aems/engagements/{engagement}/calendar`
- `POST /aems/engagements/{engagement}/calendar/milestones`
- `PUT /aems/engagements/{engagement}/calendar/milestones/{milestone}`
- `POST /aems/engagements/{engagement}/calendar/milestones/{milestone}/transition`
- `POST /aems/engagements/{engagement}/retention/{retention}/archive`
- `POST /aems/engagements/{engagement}/retention/{retention}/legal-hold-release`
- `POST /aems/engagements/{engagement}/retention/{retention}/destruction-review`
- `POST /aems/engagements/{engagement}/retention/{retention}/disposition`

The new permission family is `aems.records.view/search`,
`aems.calendar.view/manage`, and the controlled `aems.retention.archive`,
`aems.retention.legal_hold_release`, `aems.retention.destruction_review`, and
`aems.retention.disposition_execute` actions. Scope and closed-engagement
guards are applied by `AemsAccessService`; sensitive actions are CIAS-only or
reviewer-separated. The React engagement workspace exposes **Records &
Disposition** and **Audit Calendar** tabs with empty, error, search, and
permission-aware states. Records marked `RESTRICTED` or `SECRET` are omitted
unless the user has the Core `documents.view_restricted` permission.

## Verification

The focused closure regression now covers records search, milestone creation,
scope enforcement, and an auditable ineligible destruction review. The
existing four closure/completion tests continue to pass.

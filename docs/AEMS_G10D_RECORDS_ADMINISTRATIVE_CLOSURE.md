# AEMS-G10D Records and Administrative Closure Conformance

G10D adds the dedicated `Records and Administrative Closure` route:

`/audit-engagement-management/records-closure`

The workspace is engagement-scoped and provides three controlled views:

- **Closure Readiness** — authoritative checklist, completion/transfer
  reconciliation, closure blockers, formal Closure actions, and reopening
  history where applicable;
- **Retention Monitoring** — approved retention classification, trigger,
  period, custodian, storage location, legal-hold state, and immutable approval;
- **Records & Disposition** — searchable final document index, archive status,
  legal-hold release, destruction-eligibility review, disposition recording, and
  closure blockers.

## Professional controls

- `COMPLETED` means substantive audit work has finished. It is not formal
  administrative closure.
- `CLOSED` is created only by the formal Closure workflow after the backend
  re-evaluates authoritative blockers. Closed child records and the final
  document index are immutable.
- Approved retention metadata is immutable. Archive is allowed only after
  formal closure and approved retention metadata.
- Active legal holds block archive and disposition. Legal-hold release records
  an actor, reason, reference, and audit event.
- Destruction eligibility is a review result, not physical deletion. A
  disposition record requires an eligible review, no legal hold, and the
  authorized disposition permission.
- Closure blockers are calculated from authoritative records; the UI cannot
  override them with manual checkboxes.

## Permission contract

The route is visible when the user has one of `aems.closure.view`,
`aems.retention.view`, or `aems.records.view`. Individual tabs and mutations
remain permission-aware. Laravel services enforce engagement scope, office
scope, separation of duties, optimistic locking, audit logs, and activity
events regardless of frontend visibility.

The existing protected APIs remain the source of truth:

- `/api/aems/engagements/{engagement}/closure`;
- `/api/aems/engagements/{engagement}/records`;
- `/api/aems/engagements/{engagement}/retention/{retention}/archive`;
- `/api/aems/engagements/{engagement}/retention/{retention}/legal-hold-release`;
- `/api/aems/engagements/{engagement}/retention/{retention}/destruction-review`;
- `/api/aems/engagements/{engagement}/retention/{retention}/disposition`.

No migration or physical-record deletion is introduced in G10D.

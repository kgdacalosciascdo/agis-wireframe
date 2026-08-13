# AEMS-G4 AEO and Team Authority Contract

## Scope

G4 adds authority and auditable distribution controls without renaming the
existing `AEMS`, `AEMS-*`, `aems.*`, or legacy `aem.*` identifiers.

## AEO signatory matrix

Every AEO content version receives three required matrix entries:

1. `INDEPENDENT_REVIEWER`;
2. `APPROVING_AUTHORITY`; and
3. `ISSUING_AUTHORITY`.

The matrix is stored in `aems_aeo_signatories`. A transition records an
in-app attestation by the actor and preserves the signing method, reference,
actor, timestamp, and AEO version. Existing audited review events are
materialized as `LEGACY_EVENT_ATTESTATION` entries when an older AEO is first
approved after G4.

The preparer cannot independently review, approve, or issue. Approval requires
an independent reviewer signature. Issuance requires approval and a different
issuing authority. Signed matrix entries are immutable.

## AEO status controls

Existing draft/review/approval/issuance transitions remain supported. G4 adds:

- `CANCEL` → `CANCELLED` for an authorized cancellation;
- `VOID` → `VOIDED` for an authorized invalidation; and
- `SUPERSEDE` → `SUPERSEDED` when an issued order is retired before a new
  active AEO is created.

All three require a reason, CIAS Management authority, a lock version, an
engagement event, an audit log entry, and the actor identity. They deactivate
the order but do not delete versions. `AMEND` creates a new draft version and
preserves the previous issued version.

## Distribution and acknowledgement

Only an `ISSUED` AEO can be distributed. `aems_aeo_distributions` records the
version, recipient user or office, transmittal method, transmittal reference,
sent timestamp, and acknowledgement. A recipient or authorized internal user
may acknowledge once; the acknowledgement note and actor are retained.

API endpoints:

- `GET /api/aems/engagements/{engagement}/aeo/{order}/distribution`
- `POST /api/aems/engagements/{engagement}/aeo/{order}/distribution`
- `POST /api/aems/engagements/{engagement}/aeo/{order}/distribution/{distribution}/acknowledge`

## Team amendments and access history

Team assignment, update, reassignment, and ending create immutable records in
`aems_team_amendments`. Each record includes authority code, reason,
consequence assessment, before/after snapshots, actor, and timestamp.

`aems_team_access_history` separately records access grants, updates, and
revocations with role, dates, actor, reason, and assignment snapshot. Existing
`engagement_team_history` remains the compatibility workflow history.

## Permissions

G4 permissions are:

- `aems.aeo.sign`, `aems.aeo.amend`, `aems.aeo.distribute`,
  `aems.aeo.acknowledge`, `aems.aeo.cancel`, `aems.aeo.void`,
  `aems.aeo.supersede`;
- `aems.team.amend`; and
- `aems.team.history`.

Distribution, cancellation, voiding, supersession, and team amendment are
CIAS-authorized operations. Assignment-scoped users can inspect history. The
API remains authoritative for engagement scope, lock versions, and separation
of duties.

## Verification

The migration is additive and passed a fresh seeded migration. Existing AEO
and team tests remain useful regression coverage; tests that intentionally
reuse one actor for review and approval must use separate authorities under
the G4 separation-of-duties rule.

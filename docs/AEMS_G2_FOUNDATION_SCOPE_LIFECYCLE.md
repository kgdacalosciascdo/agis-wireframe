# AEMS-G2 Foundation, Scope, Lifecycle, and Navigation Contract

## Status

G2A/G2B is implemented as an additive foundation hardening phase. Existing
`AEMS`, `AEMS-*`, `aems.*`, and legacy `aem.*` identifiers remain compatible.
The descriptive module name in the React navigation is **Audit Engagement
Management**.

## Office invariant and reviewed backfill

Every active engagement has one Engagement Office. The canonical office is
stored in `audit_engagements.engagement_office_id` and the compatibility pivot
`audit_engagement_offices` contains one row marked primary. The G2 migration:

1. records every legacy office set in `aems_engagement_scope_backfill_reviews`;
2. retains the deterministic primary office when legacy duplicates exist;
3. removes duplicate compatibility pivot rows only after recording their IDs in
   the review record; and
4. creates a unique database index so a second pivot cannot be inserted.

Records with no historical office are marked `REQUIRES_REVIEW`; the API and
SCR-212 workspace refuse activation or scope completion until an authorized
reviewer assigns exactly one office. Existing source data is never silently
reassigned.

## Structured scope

The SCR-212 Define Engagement Scope workspace records:

- one Engagement Office;
- engagement-level boundaries and known limitations;
- an explicit source-variance decision (`ALIGNED`, `VARIANCE_APPROVED`, or
  `NOT_APPLICABLE`) with explanation/authority; and
- one or more Audit Areas, each with an objective, boundary, limitations, and
  source-variance note, plus its in-scope Audit Focus IDs.

Area and Focus metadata is stored on the existing compatibility pivots in
`coverage_metadata`. Approved IAP source records remain read-only; the
engagement-specific scope is the AEMS planning input.

## Lifecycle

`COMPLETED` is distinct from `CLOSED`:

- `COMPLETED` means substantive audit work and completion gates are complete;
  the engagement remains active while the formal Closure record and records
  controls are processed.
- `CLOSED` means the approved formal Closure decision has locked the engagement
  and its final records.

The aggregate transition service supports `CLOSURE_REVIEW` → `COMPLETED`
(`COMPLETE_AUDIT_WORK`) and `COMPLETED` → `CLOSURE_REVIEW`
(`OPEN_CLOSURE_REVIEW`) before the existing formal close operation. No child
workflow is bypassed.

## Special and emergency authorization

Special/unplanned engagements preserve the free-form authority type code for
compatibility and now also record `special_authority_class` as `SPECIAL` or
`EMERGENCY`. The approving authority remains separate from the registry
creator. Emergency classification does not bypass ordinary scope, team,
independence, or closure gates.

## IAP risk-source discriminator

Imported engagements expose `iap_risk_source_type`:

- `UNIVERSE_RISK_ASSESSMENT` for `iap_universe_risk_assessments` lineage;
- `LEGACY_RISK_ASSESSMENT` for `iap_risk_assessments` lineage (preserved in
  `iap_legacy_risk_assessment_id`); or
- `null` where the approved source has no risk assessment.

Both IAP risk systems remain present and are not migrated into one another.

## Core numbering

New engagement codes use Runtime Configuration key
`aems_engagement_number_format` (default `AEMS-{YEAR}-{SEQ:3}`), preserving
manual code overrides and uniqueness checks.

## SCR-212 and navigation

SCR-212 is a contextual workspace at
`/audit-engagement-management/{engagementId}?tab=scope`; it is intentionally
not a duplicate sidebar destination. The engagement workspace retains the
canonical SCR-220 tabs: Overview, Planning, Execution, Audit Issues, AFRs,
Conferences, Audit Reporting Workspace, Completion & Transfer, and Activity.
The React module label is reconciled to Audit Engagement Management while
route, permission, table, service, and activity compatibility identifiers
remain AEMS.

## Verification

Focused backend coverage is in
`backend/tests/Feature/Api/AemsFoundationG2Test.php` and protects schema
columns, SCR-212 API behavior, structured coverage, IAP risk discrimination,
the one-office database index, and the `COMPLETED` projection. Existing
registry, lifecycle, and foundation tests remain regression coverage.

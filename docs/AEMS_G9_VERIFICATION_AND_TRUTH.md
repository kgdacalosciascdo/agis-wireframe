# AEMS-G9 Verification and Documentation Truth Pass

Status: implemented as a verification and documentation phase on 13 August
2026. This phase adds regression contracts and corrects the as-built record; it
does not change AEMS workflow behavior.

## Verification index

`backend/tests/Feature/Api/AemsG9ConformanceTest.php` is the historical G9
registry index. The final semantic acceptance index is
`backend/tests/Feature/Api/AemsG10EAcceptanceTest.php`. Together they create:

- 35 G9 source-contract rows and 35 G10E runtime semantic Rule tests (`Rule 01`
  through `Rule 35`), each requiring an executable authoritative Laravel
  service/model anchor or compatibility status constant;
- 32 independent SCR tests for the UID/DGM registry (`SCR-210` through
  `SCR-263`, including reserved `SCR-243`);
- authenticated AEMS download/export route coverage;
- migration-chain manifest coverage for the G3 through G8 migrations.

The 32 SCR count excludes the AEMS dashboard identifier and the two sidebar
entries that are legacy/contextual records without an SCR id. Process Flow and
Risk Matrix remain artifacts inside `SCR-221`/the Planning Package; they are
not additional sidebar screens.

## Frontend verification

`tests/e2e/aems-g9-conformance.spec.js` adds:

- a static duplicate-route check proving every operational AEMS sidebar route
  has exactly one explicit `<Route>` and is present in `implementedCorePaths`,
  while the generic `ModulePage` fallback remains excluded;
- a 6-role menu and contextual-tab matrix for `platform_admin`, `agis_admin`,
  `cias_management`, `agis_user`, `auditee_representative`, and `read_only`;
- browser-level mutation payload checks for optimistic-lock transitions and a
  negative Evidence assessment;
- protected-download and negative-evidence suite presence checks;
- desktop/mobile project and responsive-shell coverage checks.

The fixture at
`tests/e2e/fixtures/aems-role-navigation-matrix.js` is permission-driven and
mirrors the stable permission codes seeded by `RolePermissionSeeder`. It does
not add role-name branches to the application.

## Migration rehearsal

Run `scripts/verify-aems-g9.ps1` from the repository root. By default it uses
an ephemeral SQLite schema, performs a fresh seeded migration rehearsal, the G9
PHP contracts, lint/build, and the G9 desktop/mobile Playwright projects. Use
`-SkipFrontend` or `-SkipPlaywright` when isolating a layer. Pass
`-UseConfiguredDatabase` only when an explicitly disposable PostgreSQL database
has been provisioned.

The rehearsal is intentionally explicit and local; it does not stage, commit,
reset, clean, or push files.

## As-built truth rules

1. AEMS is the compatibility identifier used by the current application;
   reference documents may call the module AEM (Audit Engagement Management).
2. `Audit Reporting Workspace` is the one reporting sidebar destination.
   Interim, Draft, Final, and Distribution are contextual stages under that
   workspace, not four duplicate sidebar modules.
3. Child-record workflows are implemented. Aggregate lifecycle transitions,
   formal closure, records controls, and calendar controls are represented by
   the current G8 services and routes; the strict planning gate remains the
   authoritative fieldwork gate.
4. Core Document Versions remain the source of file checksum, MIME type, size,
   version, custody, confidentiality, and protected download data.
5. IAP remains a read-only source for approved engagement lineage. ARMIS is
   authoritative only when its provider decision and reconciliation contract
   are satisfied; explicit fallback remains visible. AIS is outside AEMS scope.
6. Historical checkpoint statements in
   `AEMS_IMPLEMENTATION_BASELINE.md` describe the state at their checkpoint.
   Later G4-G8 sections supersede earlier “not started” statements; they are
   not current missing-feature claims.

## Current verification result

The focused G9 backend contract passes **69 tests and 123 assertions**. The
G10E acceptance contract adds the runtime semantic Rule matrix and final
status/route assertions. Exact full-suite, migration, lint/build, and
desktop/mobile Playwright results are recorded in
`docs/AEMS_G10E_FINAL_ACCEPTANCE.md` for each release; a stale fixture must be
updated to satisfy the strict planning readiness gate rather than weakening
that gate.

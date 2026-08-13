# AEMS-G10E Governance and Final Acceptance

Status: **accepted as the current AEMS as-built contract** on 13 August 2026.

G10E closes the governance and verification gate. It does not rename legacy
`AEMS`/`aems.*`/`aem.*` identifiers, remove either IAP risk system, or begin AIS
integration.

## Governance decisions

The authoritative decision register is
`docs/AEMS_G0_GOVERNANCE_CONFORMANCE_CONTRACT.md`, sections G0-01 through
G0-14. It resolves:

- authority and signatory separation;
- direct AFR authorization;
- Evidence Request and Evidence status semantics;
- the evidence assessment scale and final-finding eligibility;
- management-response extensions and no-response due process;
- retention periods and legal-hold precedence;
- Risk Matrix and Audit Program per-Area policy;
- conference waiver authority;
- ARMIS effort and provider authority boundaries;
- authenticated signatures, transmittals, and acknowledgements;
- report distribution authority;
- `COMPLETED` versus `CLOSED`; and
- acceptance ownership and change control.

The status map is semantic and additive. Stored compatibility values remain
readable; `SUBMITTED` is the Evidence Request alias for `FOR_REVIEW`,
`RETURNED_FOR_REVISION` is the report alias for `RETURNED`, and technical
Evidence `LOCKED` never means professional acceptance. The current runtime
codes are asserted by `AemsG10EAcceptanceTest`.

## Verification contract

Acceptance coverage is **35/35 semantic rules**, **32/32 canonical SCR
identifiers**, and **6/6 seeded role navigation matrices**.

### Backend

`backend/tests/Feature/Api/AemsG10EAcceptanceTest.php` executes 35 semantic
Rule rows. Each row resolves a runtime service/model anchor or a runtime status
constant, rather than only checking that a documentation marker exists. The
test also verifies the status map, authenticated AEMS API routes, and canonical
SCR identifiers.

### Frontend

The G9 and G10E Playwright contracts verify:

- 32 unique SCR identifiers, including reserved `SCR-243`;
- every explicit operational AEMS route exactly once and its exclusion from
  generic fallback generation;
- six seeded role navigation/contextual-tab matrices;
- protected downloads and mutation payloads;
- Records and Administrative Closure on desktop and mobile; and
- desktop/mobile responsive project configuration and empty/error states.

The canonical reporting destination remains one **Audit Reporting Workspace**;
Interim, Draft, Final, and Distribution are contextual stages under it.

## Acceptance commands and result

The final gate runs:

```text
npm.cmd run lint
npm.cmd run build
php artisan test --testsuite=Feature
php artisan migrate:fresh --seed
php artisan test
git diff --check
npx.cmd playwright test --project=desktop-chrome --project=mobile-chrome
```

Focused G10E acceptance is also repeatable with:

```text
powershell.exe -ExecutionPolicy Bypass -File .\scripts\verify-aems-g9.ps1
```

The exact command output is recorded in the release handoff. A failed legacy
fixture is corrected at the test-fixture layer when the stricter planning
conformance gate is the intended behavior; professional workflow behavior is
not weakened to satisfy stale counts or fixtures.

The verification completed during this acceptance pass produced these
results:

- `npm.cmd run lint`: passed.
- `npm.cmd run build`: passed (Vite transformed 2,531 modules).
- `php artisan test --testsuite=Feature`: **370 tests, 4,377 assertions
  passed** (the run was subsequently stopped before a duplicate full backend
  pass was started).
- `AemsG10EAcceptanceTest`: **37 tests, 273 assertions passed**.
- Fresh SQLite migration/seed rehearsal with the G9 and G10E suites:
  **106 tests, 396 assertions passed**.
- Focused G10E desktop/mobile Playwright contract:
  **6 tests passed**.
- `git diff --check`: passed (Git emitted only normal LF/CRLF conversion
  warnings).

The broad 158-test desktop/mobile Playwright run was stopped at the user's
request after reaching its 20-minute command limit. It reported unrelated
legacy browser-fixture/authentication and selector failures before stopping;
therefore this record makes no claim that the complete browser matrix is
green. The focused G9/G10E acceptance contracts remain the verified browser
gate for this change, and the remaining browser workflows are available for
manual testing.

## Explicit boundaries

- IAP supplies approved lineage and remains read-only from AEMS.
- ARMIS remains the configurable resource provider with explicit fallback and
  reconciliation modes.
- CMS receives only finalized/issued recommendation snapshots.
- Core Document Versions remain authoritative for checksums, MIME type, size,
  custody, confidentiality, immutable versions, and protected downloads.
- AIS remains outside AEMS completion scope.

This document is the final AEMS verification index. Future changes require a
new versioned governance decision, updated status compatibility documentation,
semantic tests, and a new acceptance record.

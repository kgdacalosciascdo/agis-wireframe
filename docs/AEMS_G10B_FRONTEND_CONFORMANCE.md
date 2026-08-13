# AEMS-G10B — Frontend conformance

Status: implemented.

The Execution Workspace consumes the backend `recordTypes` contract, so Inquiry,
Meeting, Field Note, and Other are available in the create/edit record form
alongside the existing fieldwork types. The selected procedure panel displays
its audit criteria when the planning contract supplies it.

The Findings workspace now consumes `procedures` from the findings workspace
contract. Draft and edit forms can select one or more approved-program
procedures, while procedure links inferred by the backend remain visible after
save. Finding detail displays each procedure's objective, criteria reference,
and traceability note and explains when the professional gate is not met.

The SCR-212 Scope workspace detects stored Focus links that do not belong to
their selected Area, presents an explicit integrity warning, and disables save
until the invalid relationship is corrected. Backend validation remains
authoritative.

Focused Playwright coverage covers the extended taxonomy and procedure/criteria
detail on desktop and mobile projects. Existing Execution and Issues/Findings
regressions continue to run unchanged.

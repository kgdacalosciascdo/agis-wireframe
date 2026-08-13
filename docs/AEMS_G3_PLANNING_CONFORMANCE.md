# AEMS-G3 Planning Conformance

Implemented on 13 August 2026 as an additive conformance layer over the
existing immutable Planning Package and Audit Program workflows.

## Backend contract

- Process-flow records support Area/Focus scope, boundary statement, ordered
  steps, inputs, outputs, records/systems, controls, decision points, risk
  points, limitations, and Core Document Version references.
- A planning version may contain multiple risk matrices. The legacy
  `riskMatrix` relation and payload remain as the first-matrix compatibility
  alias; the canonical payload is `riskMatrices`.
- Risk Matrix Items carry Rule-35 planning fields: Area/Focus, process, risk
  area, planned audit approach, criteria, process-flow traceability, response
  rationale, and source reference.
- Audit Programs carry Area, type, period, criteria, risk-statement set,
  sampling approach, and planned Working Paper requirements.
- Procedures carry Area/Focus, process, method, criteria, planned person-days,
  sampling requirements, planned Working Paper requirements, and risk links.
- `aems_planning_kpis` stores immutable-version KPI records.
- `aems_planned_working_paper_requirements` stores required planned work
  products and their evidence expectations.

## Readiness and fieldwork gate

The Planning Package workspace returns both the historical compatibility
`ready` value and the strict `fieldworkReady` value. `fieldworkReady` requires
valid IAP lineage, a complete survey and objectives, approved AEP and current
Audit Program, structured process-flow details, risk-matrix coverage for every
authorized Area, complete Rule-35 risk item fields and traceability, program
period/criteria/sampling, complete procedure process/method/criteria/person-
days/sampling/planned-WP values, KPI records or a documented not-applicable
decision, and at least one complete required planned Working Paper.

`START_FIELDWORK` requires an approved package whose current version is the
approved version and whose strict `fieldworkReady` result is true. The
aggregate transition service reports failed conformance checks and does not
mutate child workflow records.

## API and compatibility

Existing Planning Package and Audit Program endpoints accept the new camelCase
fields and return them in snapshots. No new sidebar route was added: Process
Flow, Risk Matrix, KPI, and planned-WP controls remain artifacts in the
canonical Planning Package and Program workspaces. Existing legacy fields,
endpoints, statuses, singular `riskMatrix` payload, and old planning tests
remain supported.

Additive migration:
`2026_08_30_000000_add_aems_g3_planning_conformance.php`.

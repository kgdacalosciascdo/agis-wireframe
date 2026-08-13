# AEMS-G7 — Reporting and distribution

G7 extends the AEMS reporting workspace without changing the immutable report
version contract. Each generated version carries a source manifest and a
SHA-256 manifest hash. The manifest pins the engagement, finding revisions,
Issues, approved Working Paper versions, and the exact Core Document Version
and checksum for every linked Evidence item.

## Source traceability

Draft, Interim, and Final report requests may include `issueIds`,
`workingPaperVersionIds`, and `evidenceIds`. Links are stored in immutable
version-bound tables. Working Papers must be approved/current and Final Reports
may only link Evidence with an `ACCEPTED` outcome. Final creation records the
approved Interim/Draft source version and an `interimTreatment` value
(`RETAINED_WITH_REVIEW`, `REVISED`, `OMITTED`, or `RESOLVED`). Existing report
versions remain immutable; amendments and supersessions still create new
versions.

## Authority, signatures, and transmittal

The authority matrix is append-only and version-bound. It records IAU Head
recommendation and LCE approval decisions (and permits a Presiding Officer
approval record), including actor, date, comment, and reference. The existing
approval transition records controlled compatibility decisions for legacy
report flows. Issuance requires both matrix decisions and creates immutable LCE
and Presiding Officer signatory records. Issuance also creates an immutable
controlled-system transmittal; additional transmittals can be recorded with a
reference, method, status, note, and sending actor.

For legacy report flows that do not submit separate authority rows, the
controlled approval action records compatibility IAU Head/LCE decision rows;
new clients can submit explicit version-bound decisions before approval.

## Protected reproducible exports

`GET .../versions/{version}/exports/PDF` and `.../CSV` are authenticated,
permissioned, scope-aware endpoints. They require an issued locked version.
PDF exports use the locked Core Document Version; CSV exports are generated
from the stored source manifest. Every export records its format, manifest
hash, file checksum, size, scope hash, generated actor/time, and protected
storage path. No public URL is exposed.

## Administrative closure

An issued report may be administratively closed with a reason and optional
reference by a separately authorized supervisor. Closure changes only the
report-family status to `ADMINISTRATIVELY_CLOSED`; the issued version, source
manifest, signatures, and transmittal remain locked and auditable.

## API additions

- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/authority-decisions`
- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/signatories`
- `POST /aems/engagements/{engagement}/reports/{report}/versions/{version}/transmittals`
- `POST /aems/engagements/{engagement}/reports/{report}/administrative-close`
- `GET /aems/engagements/{engagement}/reports/{report}/versions/{version}/exports/{PDF|CSV}`

Permissions are `aems.report.authority`, `aems.report.signatory`,
`aems.report.transmit`, `aems.report.export`, and `aems.report.close_admin`.
All actions use the existing AEMS scope, activity-event, audit-trail, and
optimistic-locking infrastructure.

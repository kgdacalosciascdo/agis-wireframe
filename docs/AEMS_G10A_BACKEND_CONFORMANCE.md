# AEMS-G10A — Backend conformance closure (bounded pass)

Status: implemented as an additive backend pass; frontend queue work remains a
separate follow-up.

## Controls delivered

### Fieldwork record taxonomy

`AemsFieldworkRecord::TYPES` is now the single source used by the request and
workspace contract. In addition to the existing Interview, Observation,
Walkthrough, Inspection, Testing, Sampling, and Analysis types, the API accepts
Inquiry, Meeting, Field Note, and Other. Existing values and persisted records
remain compatible.

### Finding-to-procedure criteria traceability

The `audit_finding_procedure` pivot records the approved audit procedure(s)
supporting a finding, the procedure's criteria reference, a traceability note,
the linking actor, and timestamps. Finding revisions copy these links into the
new immutable revision. Communication and finalization snapshots include the
procedure IDs alongside the existing working-paper, evidence, and fieldwork
version IDs.

The API accepts optional `procedureIds`. For compatibility, links are also
derived from cited Working Paper Versions and Fieldwork Record Versions. Every
derived or supplied procedure must belong to the same engagement. Finding
submission, validation, and finalization require at least one procedure link;
the existing approved-paper, finalized-fieldwork, and evidence eligibility
gates continue to apply.

The finding detail contract now exposes `procedures` with procedure code,
objective, audit criteria, criteria reference, traceability note, and linking
actor.

## Focused verification

The Issue/Finding feature test suite covers:

- successful automatic procedure traceability from an approved working paper;
- persistence of the normalized finding/procedure link;
- rejection of an unscoped procedure ID;
- the expanded fieldwork taxonomy.

The full `AemsIssueFindingRecommendationTest` suite remains green after this
pass. This phase does not change Issue, Evidence, Report, Closure, CMS, AIS, or
ARMIS workflow transitions.

## Remaining conformance work

This is not a declaration that every MDS/UID requirement is complete. The
remaining reference gaps are tracked in the as-built baseline and require
separate phases, including dedicated operational queues, records/archive
operations, and any governance decisions still marked open in the reference
documents.

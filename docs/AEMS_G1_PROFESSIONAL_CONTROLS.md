# AEMS-G1 Professional-Control Hardening

Status: implemented and verified 13 August 2026.

This checkpoint hardens the professional gates identified by the AEMS-G0
governance contract. It does not change the AEMS navigation model or start a
new evidence/reporting lifecycle.

## Evidence eligibility

Evidence can support Finding validation or finalization only when all of the
following are true:

- the Evidence row is the current revision and is `VERIFIED` or `LOCKED`;
- the assessment is the current immutable `ASSESSED` revision;
- the assessment cites the exact current Core `document_versions` row;
- sufficiency, appropriateness, relevance, accuracy, completeness,
  corroboration, authenticity, and integrity are positive (`YES`, `HIGH`, or
  `ADEQUATE`);
- reliability and competence are positive (`HIGH` or `ADEQUATE`);
- contradiction is explicitly negative (`NO` or `ADEQUATE`);
- confidentiality is classified;
- no evidence gaps remain; and
- limitations, restrictions, or exception-required use have an approved
  exception decision.

The API returns `eligibleForFinalizedFinding` and `eligibilityReasons` for
Evidence and Assessment records. A failed reason is shown to reviewers and
prevents the Validate and Finalize actions. This is a backend rule; the UI is
not the security boundary.

## Immutable assessment and request versions

`AemsEvidenceRequestVersion` and `AemsEvidenceAssessment` rows are immutable.
Corrections create a new version with `supersedes_*`, a revision number, and a
change reason. Approving a restricted-evidence exception also creates a new
immutable assessment revision; it never overwrites the assessed snapshot.

## Findings

- `conclusion` is required by the finding request and by every Submit,
  Validate, and Finalize transition.
- A finding created without a source Issue must include an authorized reason
  (`URGENT_OR_MATERIAL_RISK`, `FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT`, or
  `CROSS_CUTTING_OR_SYSTEMIC_MATTER`) and an authority reference.
- The authority actor and timestamp are preserved on the finding and returned
  by the Recommendation/AFR detail contract.
- Findings converted from an Issue must reference a validated Issue in the
  same engagement.

## Engagement progress controls

Entry-conference KPI and reporting progress gates are evaluated from the
approved immutable Planning Package baseline. A configured KPI control must
contain at least one indicator with a name, target, and measurement method.
Progress assessment controls require a recorded status and evidence
reference. Legacy packages without these optional controls remain compatible
and are explicitly reported as not configured/not applicable; they are no
longer represented by unconditional lifecycle gates.

## API and source contract

The existing AEMS finding and evidence endpoints retain their routes. New or
extended response fields are:

- `evidence[].eligibleForFinalizedFinding`;
- `evidence[].eligibilityReasons`;
- `evidence[].assessment.eligibilityReasons`;
- `directCreationReason`, `directCreationAuthority`, `directCreatedBy`, and
  `directCreatedAt` on Findings.

The migration
`2026_08_28_000000_harden_aems_professional_controls` adds the direct-finding
authority provenance columns. Legacy `aems.*` compatibility permissions and
existing route names are unchanged.

## Verification

The professional-control tests cover negative/incomplete evidence,
restricted-evidence exceptions, exact document-version pinning, immutable
request and assessment versions, required conclusions, direct-finding
authority, revision safety, and Planning Package KPI blockers.


<?php

namespace Database\Seeders;

use App\Models\MasterList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterListSeeder extends Seeder
{
    public const LISTS = [
        [
            'code' => 'CONFERENCE_TYPE',
            'name' => 'Conference Type',
            'description' => 'Standard conferences conducted during an audit engagement.',
            'items' => [
                ['ENTRANCE', 'Entrance Conference', 'Opening conference to confirm scope, objectives, responsibilities, and schedules.'],
                ['EXIT', 'Exit Conference', 'Closing conference to discuss observations, responses, and next actions.'],
            ],
        ],
        [
            'code' => 'ENGAGEMENT_STATUS',
            'name' => 'Engagement Status',
            'description' => 'Workflow states for audit engagements.',
            'items' => [
                ['DRAFT', 'Draft', 'Being prepared by the assigned audit team.'],
                ['PENDING_REVIEW', 'Pending for Review', 'Submitted to a reviewer.'],
                ['RETURNED', 'Returned', 'Returned for revision or additional information.'],
                ['APPROVED', 'Approved', 'Approved to proceed.'],
                ['REJECTED', 'Rejected', 'Not approved to proceed.'],
                ['PLANNING', 'Planning', 'Engagement planning is underway.'],
                ['EXECUTION', 'Execution', 'Fieldwork and testing are underway.'],
                ['REPORTING', 'Reporting', 'Results are being drafted or finalized.'],
                ['COMPLETED', 'Completed', 'All planned engagement work is complete.'],
                ['CLOSED', 'Closed', 'Engagement has been administratively closed.'],
            ],
        ],
        [
            'code' => 'RISK_LEVEL',
            'name' => 'Risk Level',
            'description' => 'Standard risk ratings used across AGIS.',
            'items' => [
                ['LOW', 'Low', 'Limited likelihood or impact.'],
                ['MEDIUM', 'Medium', 'Moderate likelihood or impact requiring monitoring.'],
                ['HIGH', 'High', 'Significant likelihood or impact requiring prompt action.'],
                ['CRITICAL', 'Critical', 'Severe exposure requiring immediate management attention.'],
            ],
        ],
        [
            'code' => 'FINDING_CLASSIFICATION',
            'name' => 'Finding Classification',
            'description' => 'Common classifications for audit findings.',
            'items' => [
                ['COMPLIANCE', 'Compliance', 'Noncompliance with laws, rules, contracts, or policies.'],
                ['CONTROL', 'Internal Control', 'Design or operating weakness in an internal control.'],
                ['EFFICIENCY', 'Efficiency', 'Resources are not used economically or efficiently.'],
                ['EFFECTIVENESS', 'Effectiveness', 'Objectives or intended outcomes are not being achieved.'],
                ['GOVERNANCE', 'Governance', 'Weakness in oversight, accountability, risk, or decision-making.'],
            ],
        ],
        [
            'code' => 'RECOMMENDATION_STATUS',
            'name' => 'Recommendation Status',
            'description' => 'Lifecycle states for audit recommendations.',
            'items' => [
                ['OPEN', 'Open', 'Recommendation has been issued and awaits action.'],
                ['IN_PROGRESS', 'In Progress', 'Management action is underway.'],
                ['FOR_VALIDATION', 'For Validation', 'Evidence is ready for CIAS validation.'],
                ['IMPLEMENTED', 'Implemented', 'Required corrective action has been validated.'],
                ['OVERDUE', 'Overdue', 'Target date has passed without validated completion.'],
                ['CLOSED', 'Closed', 'Recommendation monitoring is complete.'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ([
            ...self::LISTS,
            $this->sectorList(),
            $this->employmentTypeList(),
            $this->positionList(),
        ] as $listData) {
            $list = MasterList::query()->updateOrCreate(
                ['code' => $listData['code']],
                [
                    'name' => $listData['name'],
                    'description' => $listData['description'],
                    'is_active' => true,
                ],
            );

            foreach ($listData['items'] as $index => [$code, $label, $description]) {
                $item = $list->items()->withTrashed()->firstOrNew(['code' => $code]);
                $item->fill([
                    'label' => $label,
                    'description' => $description,
                    'display_order' => $index + 1,
                    'is_active' => true,
                ]);
                $item->save();

                if ($item->trashed()) {
                    $item->restore();
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function sectorList(): array
    {
        return [
            'code' => 'OFFICE_SECTOR',
            'name' => 'Office Sector',
            'description' => 'City government sector groupings used to classify offices in the Office Registry.',
            'items' => [
                ['SYSTEM', 'System', 'Platform-level and shared AGIS administration.'],
                ['ADMIN_FINANCE_LEGAL', 'Administration, Finance and Legal', 'Administrative, fiscal, legal, procurement, records, and governance services.'],
                ['PLANNING_INFRA_TECH_ENV', 'Planning, Infrastructure, Technology and Environment', 'Planning, engineering, infrastructure, ICT, land use, and environmental services.'],
                ['HEALTH_EDUCATION_SOCIAL', 'Health, Education and Social Services', 'Health, education, housing, social welfare, and community support services.'],
                ['AGRI_BUSINESS_EMPLOYMENT', 'Agriculture, Business and Employment', 'Agriculture, enterprise, market, tourism, licensing, and employment services.'],
                ['PUBLIC_SAFETY_OTHER', 'Public Safety, Community and Other Services', 'Public safety, emergency response, community affairs, and other frontline services.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function employmentTypeList(): array
    {
        return [
            'code' => 'GOVERNMENT_EMPLOYMENT_TYPE',
            'name' => 'Government Employment Type',
            'description' => 'Appointment and engagement categories used for government personnel records.',
            'items' => [
                ['PERMANENT', 'Permanent', 'Formal CSC appointment; normally has a plantilla item; provides the highest security of tenure.'],
                ['TEMPORARY', 'Temporary', 'Formal CSC appointment to a plantilla item with limited tenure.'],
                ['SUBSTITUTE', 'Substitute', 'Formal CSC appointment using an absent employee’s item until the incumbent returns.'],
                ['COTERMINOUS', 'Coterminous', 'Formal CSC appointment tied to an official, office, agency, or project; may have an authorized position.'],
                ['FIXED_TERM', 'Fixed-term', 'Formal CSC appointment, usually authorized by law, that ends when the specified term expires.'],
                ['CONTRACTUAL_APPOINTMENT', 'Contractual appointment', 'Formal CSC appointment for a contract or project period and not used to fill a regular vacant plantilla item.'],
                ['CASUAL', 'Casual', 'Formal appointment under a separate casual arrangement, generally for essential services and usually up to one year.'],
                ['JOB_ORDER', 'Job Order', 'Non-appointment engagement without a plantilla item, governed by a Job Order contract.'],
                ['CONTRACT_OF_SERVICE', 'Contract of Service', 'Non-appointment engagement without a plantilla item, governed by a Contract of Service.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function positionList(): array
    {
        $titles = [
            'Platform Administrator',
            'AGIS Administrator',
            'City Internal Audit Officer',
            'Internal Auditor',
            'Office Head',
            'Office Employee',
            'City Mayor',
            'City Government Department Head I',
            'City Government Department Head II',
            'City Government Assistant Department Head I',
            'City Government Assistant Department Head II',
            'Administrative Aide I',
            'Administrative Aide II',
            'Administrative Aide III',
            'Administrative Aide IV',
            'Administrative Aide V',
            'Administrative Aide VI',
            'Administrative Assistant I',
            'Administrative Assistant II',
            'Administrative Assistant III',
            'Administrative Assistant IV',
            'Administrative Assistant V',
            'Administrative Officer I',
            'Administrative Officer II',
            'Administrative Officer III',
            'Administrative Officer IV',
            'Administrative Officer V',
            'Accountant I',
            'Accountant II',
            'Accountant III',
            'Accountant IV',
            'Accounting Clerk I',
            'Accounting Clerk II',
            'Accounting Clerk III',
            'Accounting Clerk IV',
            'Internal Auditor I',
            'Internal Auditor II',
            'Internal Auditor III',
            'Internal Auditor IV',
            'Internal Auditor V',
            'Budget Officer I',
            'Budget Officer II',
            'Budget Officer III',
            'Budget Officer IV',
            'Cashier I',
            'Cashier II',
            'Cashier III',
            'Cashier IV',
            'Supply Officer I',
            'Supply Officer II',
            'Supply Officer III',
            'Supply Officer IV',
            'Records Officer I',
            'Records Officer II',
            'Records Officer III',
            'Records Officer IV',
            'Human Resource Management Officer I',
            'Human Resource Management Officer II',
            'Human Resource Management Officer III',
            'Human Resource Management Officer IV',
            'Planning Officer I',
            'Planning Officer II',
            'Planning Officer III',
            'Planning Officer IV',
            'Project Development Officer I',
            'Project Development Officer II',
            'Project Development Officer III',
            'Project Development Officer IV',
            'Project Development Officer V',
            'Engineer I',
            'Engineer II',
            'Engineer III',
            'Engineer IV',
            'Engineer V',
            'Architect I',
            'Architect II',
            'Architect III',
            'Architect IV',
            'Architect V',
            'Information Systems Analyst I',
            'Information Systems Analyst II',
            'Information Systems Analyst III',
            'Computer Programmer I',
            'Computer Programmer II',
            'Computer Programmer III',
            'Information Technology Officer I',
            'Information Technology Officer II',
            'Information Technology Officer III',
            'Social Welfare Officer I',
            'Social Welfare Officer II',
            'Social Welfare Officer III',
            'Social Welfare Officer IV',
            'Social Welfare Officer V',
            'Medical Officer I',
            'Medical Officer II',
            'Medical Officer III',
            'Medical Officer IV',
            'Medical Officer V',
            'Nurse I',
            'Nurse II',
            'Nurse III',
            'Nurse IV',
            'Nurse V',
            'Agriculturist I',
            'Agriculturist II',
            'Agriculturist III',
            'Agriculturist IV',
            'Veterinarian I',
            'Veterinarian II',
            'Veterinarian III',
            'Veterinarian IV',
            'Legal Officer I',
            'Legal Officer II',
            'Legal Officer III',
            'Legal Officer IV',
            'Legal Officer V',
            'Licensing Officer I',
            'Licensing Officer II',
            'Licensing Officer III',
            'Licensing Officer IV',
            'Revenue Collection Clerk I',
            'Revenue Collection Clerk II',
            'Revenue Collection Clerk III',
            'Tourism Operations Officer I',
            'Tourism Operations Officer II',
            'Tourism Operations Officer III',
            'Tourism Operations Officer IV',
            'Labor and Employment Officer I',
            'Labor and Employment Officer II',
            'Labor and Employment Officer III',
            'Labor and Employment Officer IV',
            'Environmental Management Specialist I',
            'Environmental Management Specialist II',
            'Environmental Management Specialist III',
            'Disaster Risk Reduction and Management Officer I',
            'Disaster Risk Reduction and Management Officer II',
            'Disaster Risk Reduction and Management Officer III',
            'Disaster Risk Reduction and Management Officer IV',
            'Utility Worker I',
            'Utility Worker II',
            'Driver I',
            'Driver II',
            'Driver III',
        ];

        return [
            'code' => 'POSITION',
            'name' => 'Position',
            'description' => 'Government position titles based on the DBM Index of Occupational Services and common LGU plantilla positions. Authorized custom titles may also be added.',
            'items' => collect($titles)
                ->map(fn (string $title): array => [
                    Str::of($title)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString(),
                    $title,
                    'Government position title for user plantilla and assignment records.',
                ])
                ->all(),
        ];
    }
}

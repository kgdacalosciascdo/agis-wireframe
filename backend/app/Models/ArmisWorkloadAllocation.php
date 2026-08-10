<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores planned workload allocations independently from IAP and AEMS records. */
class ArmisWorkloadAllocation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    protected $fillable = [
        'resource_profile_id', 'requirement_id', 'source_module', 'source_type', 'source_id',
        'fiscal_year', 'planned_person_days', 'status', 'notes', 'created_by',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'approved_by',
        'approved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer', 'planned_person_days' => 'decimal:2',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceRequirement::class, 'requirement_id');
    }
}

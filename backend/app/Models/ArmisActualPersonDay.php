<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores versioned actual person-day submissions; approved versions are locked. */
class ArmisActualPersonDay extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    protected $fillable = [
        'resource_profile_id', 'source_module', 'source_type', 'source_id', 'period_start',
        'period_end', 'version_number', 'actual_person_days', 'status', 'notes',
        'supersedes_id', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'approved_by', 'approved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'version_number' => 'integer',
            'actual_person_days' => 'decimal:2', 'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}

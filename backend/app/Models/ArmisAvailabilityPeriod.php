<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores a submitted or approved ARMIS availability period. */
class ArmisAvailabilityPeriod extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    public const TYPES = ['AVAILABLE', 'UNAVAILABLE', 'LEAVE', 'TRAINING', 'OTHER'];

    protected $fillable = [
        'resource_profile_id', 'availability_type', 'start_date', 'end_date',
        'person_days', 'status', 'notes', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'person_days' => 'decimal:2',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one assigned audit procedure and its completion or waiver disposition.
 */
class AuditProgramProcedure extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'WAIVED'];

    public const REVIEWER_RESULTS = [
        'SATISFACTORY',
        'NEEDS_REVISION',
        'NOT_APPLICABLE',
    ];

    protected $fillable = [
        'audit_program_id',
        'procedure_code',
        'sequence_number',
        'objective',
        'procedure_description',
        'expected_evidence',
        'working_paper_reference',
        'assigned_to',
        'target_date',
        'status',
        'reviewer_result',
        'reviewer_comments',
        'reviewed_by',
        'reviewed_at',
        'completed_at',
        'completed_by',
        'waived_at',
        'waived_by',
        'waiver_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'target_date' => 'date',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'waived_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AuditProgram::class, 'audit_program_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function workingPapers(): HasMany
    {
        return $this->hasMany(WorkingPaper::class, 'audit_program_procedure_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function waiverApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by')->withTrashed();
    }
}

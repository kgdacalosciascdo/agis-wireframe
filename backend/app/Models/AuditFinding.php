<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents one revision of a supported audit finding and its dialogue and recommendations.
 */
class AuditFinding extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'VALIDATED',
        'COMMUNICATED',
        'AWAITING_MANAGEMENT_RESPONSE',
        'UNDER_DIALOGUE',
        'FINALIZED',
    ];

    protected $fillable = [
        'finding_family_uuid',
        'revision_number',
        'supersedes_finding_id',
        'is_current_revision',
        'audit_engagement_id',
        'source_issue_id',
        'finding_code',
        'title',
        'criteria',
        'condition',
        'cause',
        'effect',
        'no_recommendation_reason',
        'risk_rating_id',
        'responsible_office_id',
        'status',
        'authored_by',
        'reviewer_id',
        'submitted_at',
        'validated_at',
        'validated_by',
        'communicated_at',
        'communicated_by',
        'management_response_due_date',
        'communicated_snapshot',
        'non_response_reason',
        'non_response_recorded_at',
        'non_response_recorded_by',
        'finalized_at',
        'finalized_by',
        'finalized_snapshot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'is_current_revision' => 'boolean',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'communicated_at' => 'datetime',
            'management_response_due_date' => 'date',
            'communicated_snapshot' => 'array',
            'non_response_recorded_at' => 'datetime',
            'finalized_at' => 'datetime',
            'finalized_snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $finding): void {
            if ($finding->getOriginal('status') === 'FINALIZED'
                || ! $finding->getOriginal('is_current_revision')) {
                throw new LogicException('Finalized findings are immutable.');
            }
        });
        static::deleting(function (self $finding): void {
            if ($finding->status === 'FINALIZED') {
                throw new LogicException('Finalized findings cannot be deleted.');
            }
        });
    }

    /**
     * Limits findings to assigned auditors or the responsible auditee office.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AemsAccessService::class)->visibleFindings($query, $user);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function sourceIssue(): BelongsTo
    {
        return $this->belongsTo(AuditIssue::class, 'source_issue_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function riskRating(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_rating_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by')->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function communicator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'communicated_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'audit_finding_id');
    }

    public function managementResponses(): HasMany
    {
        return $this->hasMany(ManagementResponse::class, 'audit_finding_id')
            ->orderBy('version_number');
    }

    public function workingPaperVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkingPaperVersion::class,
            'audit_finding_working_paper',
            'audit_finding_id',
            'working_paper_version_id',
        )->withTimestamps();
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'audit_finding_evidence',
            'audit_finding_id',
            'audit_evidence_id',
        )->withTimestamps();
    }

    public function exitConferences(): BelongsToMany
    {
        return $this->belongsToMany(
            ExitConference::class,
            'exit_conference_findings',
            'audit_finding_id',
            'exit_conference_id',
        )->withPivot([
            'sequence_number',
            'discussion_status',
            'agreement_status',
            'discussion_notes',
            'agreement_details',
            'disagreement_details',
            'revised_target_date',
        ])->withTimestamps();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_finding_id')->withTrashed();
    }
}

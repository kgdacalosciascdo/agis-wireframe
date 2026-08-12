<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A scoped, separately versioned request for audit evidence. */
class AemsEvidenceRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'SENT', 'PARTIALLY_RECEIVED',
        'RECEIVED', 'ASSESSED', 'CLOSED',
    ];

    protected $table = 'aems_evidence_requests';

    protected $fillable = [
        'request_family_uuid', 'audit_engagement_id', 'request_code', 'title',
        'purpose', 'requested_from_office_id', 'requested_from_user_id',
        'due_date', 'status', 'current_version_number', 'prepared_by',
        'submitted_by', 'submitted_at', 'sent_by', 'sent_at',
        'partially_received_at', 'received_at', 'assessed_at', 'closed_at',
        'closed_by', 'closure_reason', 'lock_version', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'sent_at' => 'datetime',
            'partially_received_at' => 'datetime',
            'received_at' => 'datetime',
            'assessed_at' => 'datetime',
            'closed_at' => 'datetime',
            'lock_version' => 'integer',
            'current_version_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('engagement', fn (Builder $engagement): Builder =>
            app(AemsAccessService::class)->visibleEngagements($engagement, $user));
    }

    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function requestedFromOffice(): BelongsTo { return $this->belongsTo(Office::class, 'requested_from_office_id')->withTrashed(); }
    public function requestedFromUser(): BelongsTo { return $this->belongsTo(User::class, 'requested_from_user_id')->withTrashed(); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by')->withTrashed(); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sent_by')->withTrashed(); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by')->withTrashed(); }
    public function versions(): HasMany { return $this->hasMany(AemsEvidenceRequestVersion::class, 'evidence_request_id')->orderByDesc('version_number'); }
    public function latestVersion(): HasOne { return $this->hasOne(AemsEvidenceRequestVersion::class, 'evidence_request_id')->latestOfMany('version_number'); }
    public function evidenceLinks(): HasMany { return $this->hasMany(AemsEvidenceRequestEvidence::class, 'evidence_request_id'); }
}

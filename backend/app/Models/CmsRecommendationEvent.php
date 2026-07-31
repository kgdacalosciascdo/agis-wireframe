<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only business history for a CMS recommendation case. */
class CmsRecommendationEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const EVENT_INTAKE_CREATED = 'INTAKE_CREATED';

    public const EVENT_COMPLIANCE_MONITOR_ASSIGNED = 'COMPLIANCE_MONITOR_ASSIGNED';

    public const EVENT_COMPLIANCE_MONITOR_REPLACED = 'COMPLIANCE_MONITOR_REPLACED';

    public const EVENT_COMPLIANCE_MONITOR_ASSIGNMENT_ENDED = 'COMPLIANCE_MONITOR_ASSIGNMENT_ENDED';

    public const EVENT_ACTION_PLAN_CREATED = 'ACTION_PLAN_CREATED';

    public const EVENT_ACTION_PLAN_UPDATED = 'ACTION_PLAN_UPDATED';

    public const EVENT_ACTION_PLAN_SUBMITTED = 'ACTION_PLAN_SUBMITTED';

    public const EVENT_ACTION_PLAN_REVIEW_STARTED = 'ACTION_PLAN_REVIEW_STARTED';

    public const EVENT_ACTION_PLAN_RETURNED = 'ACTION_PLAN_RETURNED';

    public const EVENT_ACTION_PLAN_REVISION_CREATED = 'ACTION_PLAN_REVISION_CREATED';

    public const EVENT_ACTION_PLAN_ACCEPTED = 'ACTION_PLAN_ACCEPTED';

    protected $fillable = [
        'cms_recommendation_case_id',
        'cms_recommendation_id',
        'idempotency_key',
        'event_code',
        'source_module',
        'actor_id',
        'previous_status',
        'new_status',
        'event_metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('CMS recommendation events are append-only.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('CMS recommendation events cannot be deleted.'),
        );
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(
            CmsRecommendationCase::class,
            'cms_recommendation_case_id',
        );
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendation::class, 'cms_recommendation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}

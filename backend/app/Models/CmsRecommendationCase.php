<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Operational root initialized from one immutable CMS recommendation intake.
 */
class CmsRecommendationCase extends Model
{
    use HasFactory;

    public const STATUS_TRANSFERRED = 'TRANSFERRED';

    protected $fillable = [
        'cms_recommendation_id',
        'status_code',
        'effective_target_implementation_date',
        'lead_responsible_office_id',
        'opened_at',
        'created_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'effective_target_implementation_date' => 'date',
            'opened_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(
            fn (): never => throw new LogicException('CMS recommendation cases cannot be deleted.'),
        );
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendation::class, 'cms_recommendation_id');
    }

    public function leadResponsibleOffice(): BelongsTo
    {
        return $this->belongsTo(
            Office::class,
            'lead_responsible_office_id',
        )->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CmsRecommendationEvent::class)
            ->orderBy('created_at');
    }
}

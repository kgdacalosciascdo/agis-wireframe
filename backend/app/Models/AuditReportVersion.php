<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Represents one immutable generated draft or final audit-report file and content snapshot.
 */
class AuditReportVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_report_id',
        'version_number',
        'report_stage',
        'content_snapshot',
        'document_version_id',
        'checksum_sha256',
        'pdf_file_name',
        'file_size',
        'is_locked',
        'locked_at',
        'locked_by',
        'change_reason',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'content_snapshot' => 'array',
            'file_size' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $lockFields = ['is_locked', 'locked_at', 'locked_by'];
            if ($version->getOriginal('is_locked')
                || array_diff(array_keys($version->getDirty()), $lockFields)) {
                throw new LogicException('Audit-report version content is immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Audit-report versions cannot be deleted.'));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'audit_report_id')->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'audit_report_findings',
            'audit_report_version_id',
            'audit_finding_id',
        )->withPivot(['sequence_number', 'is_included'])->withTimestamps();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReportRecipient::class);
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(AuditReportReviewComment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by')->withTrashed();
    }
}

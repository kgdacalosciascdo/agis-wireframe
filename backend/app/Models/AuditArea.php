<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditArea extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'audit_area_office')->withTimestamps();
    }

    public function focuses(): HasMany
    {
        return $this->hasMany(AuditFocus::class)->orderBy('display_order');
    }
}

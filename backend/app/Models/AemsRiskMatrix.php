<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AemsRiskMatrix extends Model { use HasFactory; protected $fillable = ['planning_package_version_id','matrix_code','title','methodology','risk_appetite','overall_conclusion']; public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class,'planning_package_version_id'); } public function items(): HasMany { return $this->hasMany(AemsRiskMatrixItem::class,'risk_matrix_id')->orderBy('sequence'); } }

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AemsProcessFlowDocument extends Model { use HasFactory; protected $fillable = ['planning_package_version_id','flow_code','title','description','process_owner_office_id','document_version_id','source_type','source_reference','sequence']; public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class,'planning_package_version_id'); } public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); } public function processOwnerOffice(): BelongsTo { return $this->belongsTo(Office::class,'process_owner_office_id')->withTrashed(); } }

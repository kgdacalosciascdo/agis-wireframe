<?php

namespace App\Http\Resources;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Office */
class OfficeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'acronym' => $this->acronym,
            'sector' => $this->sector,
            'contactNumber' => $this->contact_number,
            'headName' => $this->head?->name,
            'auditAreas' => $this->auditAreas->map(fn ($area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'description' => $area->description,
            ])->values(),
            'description' => $this->description,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

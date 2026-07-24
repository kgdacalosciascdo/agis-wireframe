<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['office', 'role.permissions']);

        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'email' => $this->email,
            'name' => $this->name,
            'firstName' => $this->first_name,
            'middleName' => $this->middle_name,
            'lastName' => $this->last_name,
            'extension' => $this->name_extension,
            'initials' => $this->initials,
            'role' => $this->role?->name,
            'roleCode' => $this->role?->code,
            'office' => $this->office?->name,
            'officeCode' => $this->office?->code,
            'position' => $this->position,
            'employmentType' => $this->employment_type,
            'contactNumber' => $this->contact_number,
            'birthDate' => $this->birth_date?->toDateString(),
            'isOfficeHead' => $this->is_office_head,
            'permissions' => $this->role?->permissions->pluck('code')->sort()->values()->all() ?? [],
        ];
    }
}

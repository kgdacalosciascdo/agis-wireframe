<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MasterListItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IapSupport
{
    public function masterItem(int $id, string $listCode): MasterListItem
    {
        $item = MasterListItem::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('masterList', fn ($query) => $query
                ->where('code', $listCode)
                ->where('is_active', true))
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'masterListItem' => ["The selected value is not an active {$listCode} item."],
            ]);
        }

        return $item;
    }

    public function masterItemByCode(string $listCode, string $itemCode): MasterListItem
    {
        $item = MasterListItem::query()
            ->where('code', $itemCode)
            ->where('is_active', true)
            ->whereHas('masterList', fn ($query) => $query
                ->where('code', $listCode)
                ->where('is_active', true))
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'masterListItem' => [
                    "The {$itemCode} value is not an active {$listCode} item.",
                ],
            ]);
        }

        return $item;
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function audit(
        Request $request,
        string $action,
        Model $subject,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }
}

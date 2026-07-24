<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DemoAccountController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(config('demo.enabled'), 404);

        return response()->json([
            'success' => true,
            'data' => collect(config('demo.accounts'))
                ->map(fn (array $account): array => [
                    'id' => $account['id'],
                    'employeeId' => $account['employeeId'],
                    'password' => $account['password'],
                    'name' => $account['name'],
                    'initials' => $account['initials'],
                    'role' => $account['role'],
                    'roleCode' => $account['roleCode'],
                    'office' => $account['office'],
                ])
                ->values()
                ->all(),
        ]);
    }
}

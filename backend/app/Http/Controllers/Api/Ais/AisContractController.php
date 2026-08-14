<?php

namespace App\Http\Controllers\Api\Ais;

use App\Http\Controllers\Controller;
use App\Services\Ais\AisAuditService;
use App\Services\Ais\AisContractService;
use App\Support\AisResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisContractController extends Controller
{
    public function __construct(private readonly AisContractService $contract, private readonly AisAuditService $audit) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.contract.viewed', 'Viewed the AIS governance and hardening contract.');

        return AisResponse::json(['success' => true, 'data' => $this->contract->contract($request->user())], cacheable: true);
    }

    public function hardening(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.hardening.viewed', 'Viewed the AIS deployment hardening controls.');

        return AisResponse::json(['success' => true, 'data' => $this->contract->hardening($request->user())], cacheable: true);
    }
}

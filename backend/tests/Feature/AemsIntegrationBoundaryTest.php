<?php

namespace Tests\Feature;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Contracts\Aems\IapEngagementGateway;
use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\DatabaseCmsRecommendationGateway;
use App\Integrations\Aems\DatabaseIapEngagementGateway;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Integrations\Aems\ConfigurableResourcePlanningGateway;
use App\Models\User;
use App\Services\AemsIntegrationStatusService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AemsIntegrationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aems_module_boundaries_resolve_replaceable_providers(): void
    {
        $this->assertInstanceOf(
            DatabaseIapEngagementGateway::class,
            app(IapEngagementGateway::class),
        );
        $this->assertInstanceOf(
            DatabaseCmsRecommendationGateway::class,
            app(CmsRecommendationGateway::class),
        );
        $this->assertInstanceOf(
            ConfigurableResourcePlanningGateway::class,
            app(ResourcePlanningGateway::class),
        );
        $this->assertInstanceOf(
            ArmisResourcePlanningGateway::class,
            app(ArmisResourcePlanningGateway::class),
        );

        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $status = app(AemsIntegrationStatusService::class)->status($management);
        $this->assertTrue($status['core']['available']);
        $this->assertContains('document_versioning', $status['core']['capabilities']);
        $this->assertContains('roles_permissions_scopes', $status['core']['capabilities']);
        $this->assertSame('READ_APPROVED_SNAPSHOT', $status['iap']['ownership']);
        $this->assertSame('CREATE_ONCE', $status['cms']['ownership']);
        $this->assertSame('IMMUTABLE_INTAKE', $status['cms']['mode']);
        $this->assertSame(0, $status['cms']['transferredRecommendations']);
        $this->assertSame(0, $status['cms']['operationalCases']);
        $this->assertTrue($status['cms']['caseCoverageComplete']);
        $this->assertFalse($status['armis']['authoritative']);
        $this->assertSame('IAP_INTERIM_FALLBACK', $status['armis']['mode']);
        $this->assertSame('AEMS_UNTIL_ARMIS_AUTHORITY_GATE', $status['armis']['actualPersonDaysOwner']);
        $this->assertSame('ARMIS', $status['armis']['futureAuthoritativeProvider']);
    }
}

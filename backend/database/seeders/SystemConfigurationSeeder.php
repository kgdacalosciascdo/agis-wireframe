<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            ['system_name', 'System Name', 'Audit Governance Information System', 'string', 'general', 'Full application name displayed to users.'],
            ['system_short_name', 'System Short Name', 'AGIS', 'string', 'general', 'Short application name used in navigation and reports.'],
            ['organization_name', 'Organization Name', 'City Government of Cagayan de Oro', 'string', 'general', 'Organization responsible for the platform.'],
            ['pagination_size', 'Default Pagination Size', 25, 'integer', 'display', 'Default number of records shown on each registry page.'],
            ['date_format', 'Date Format', 'MMMM d, yyyy', 'string', 'display', 'Preferred human-readable date format.'],
            ['timezone', 'Timezone', 'Asia/Manila', 'string', 'regional', 'Timezone used for timestamps and scheduled activities.'],
            ['session_timeout_minutes', 'Session Timeout', 30, 'integer', 'security', 'Minutes of inactivity before a session expires.'],
            ['password_min_length', 'Minimum Password Length', 8, 'integer', 'security', 'Minimum length for non-demo account passwords.'],
            ['failed_login_limit', 'Failed Login Limit', 5, 'integer', 'security', 'Failed attempts allowed before temporary account lockout.'],
            ['account_lock_minutes', 'Account Lock Duration', 15, 'integer', 'security', 'Minutes an account remains locked after repeated failed logins.'],
        ];

        foreach ($configurations as [$key, $name, $value, $type, $group, $description]) {
            SystemConfiguration::query()->updateOrCreate(
                ['key' => $key],
                compact('name', 'value', 'type', 'group', 'description'),
            );
        }
    }
}

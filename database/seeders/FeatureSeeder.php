<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Users', 'code' => 'users', 'type' => 'limited', 'description' => 'Tenant user seats'],
            ['name' => 'Storage', 'code' => 'storage_mb', 'type' => 'limited', 'description' => 'Tenant storage in MB'],
            ['name' => 'Workspaces', 'code' => 'workspaces', 'type' => 'limited', 'description' => 'Customer support workspaces'],
            ['name' => 'Monthly conversations', 'code' => 'monthly_conversations', 'type' => 'limited', 'description' => 'Customer conversations processed per month'],
            ['name' => 'Contacts', 'code' => 'contacts', 'type' => 'limited', 'description' => 'Stored customer contacts'],
            ['name' => 'Companies', 'code' => 'companies', 'type' => 'limited', 'description' => 'Stored customer companies'],
            ['name' => 'Connected channels', 'code' => 'channels', 'type' => 'limited', 'description' => 'Email, web chat, and messaging channels'],
            ['name' => 'Tickets', 'code' => 'tickets', 'type' => 'boolean', 'description' => 'Structured ticket management'],
            ['name' => 'Tasks', 'code' => 'tasks', 'type' => 'boolean', 'description' => 'Support task management'],
            ['name' => 'SLA policies', 'code' => 'sla_policies', 'type' => 'limited', 'description' => 'Service-level policies and escalation rules'],
            ['name' => 'Automation rules', 'code' => 'automation_rules', 'type' => 'limited', 'description' => 'Deterministic workflow automation rules'],
            ['name' => 'Knowledge articles', 'code' => 'knowledge_articles', 'type' => 'limited', 'description' => 'Internal and public knowledge content'],
            ['name' => 'Help center', 'code' => 'help_center', 'type' => 'boolean', 'description' => 'Public customer help center'],
            ['name' => 'Customer forms', 'code' => 'forms', 'type' => 'limited', 'description' => 'Published support and lead forms'],
            ['name' => 'CSAT surveys', 'code' => 'csat', 'type' => 'boolean', 'description' => 'Customer satisfaction surveys and reporting'],
            ['name' => 'Advanced reports', 'code' => 'reports', 'type' => 'boolean', 'description' => 'Operational dashboards and reports'],
            ['name' => 'Quality reviews', 'code' => 'quality_reviews', 'type' => 'boolean', 'description' => 'Conversation quality review workflows'],
            ['name' => 'Workforce tools', 'code' => 'workforce', 'type' => 'boolean', 'description' => 'Schedules, capacity, and workforce operations'],
            ['name' => 'Exports', 'code' => 'exports', 'type' => 'boolean', 'description' => 'Data export tools'],
            ['name' => 'API access', 'code' => 'api_access', 'type' => 'boolean', 'description' => 'Developer API credentials and access'],
            ['name' => 'API requests', 'code' => 'api_requests_monthly', 'type' => 'limited', 'description' => 'API requests per month'],
            ['name' => 'Outbound webhooks', 'code' => 'webhooks', 'type' => 'limited', 'description' => 'Configured outbound webhooks'],
            ['name' => 'Connections', 'code' => 'connections', 'type' => 'limited', 'description' => 'External data and application connections'],
            ['name' => 'Custom Domain', 'code' => 'custom_domain', 'type' => 'boolean', 'description' => 'Verified custom domains'],
            ['name' => 'Custom branding', 'code' => 'custom_branding', 'type' => 'boolean', 'description' => 'Workspace logo, color, and identity controls'],
            ['name' => 'Audit retention', 'code' => 'audit_retention_days', 'type' => 'limited', 'description' => 'Days of audit and activity history retained'],
            ['name' => 'Priority support', 'code' => 'priority_support', 'type' => 'boolean', 'description' => 'Priority platform support handling'],
        ])->each(fn (array $feature) => Feature::updateOrCreate(['code' => $feature['code']], $feature));
    }
}

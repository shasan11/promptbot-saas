<?php

namespace App\Console\Commands;

class TenantProvisionCommand extends TenantCreateCommand
{
    protected $signature = 'tenant:provision
        {--company= : Company name}
        {--slug= : Tenant slug}
        {--owner-name= : Owner name}
        {--owner-email= : Owner email}
        {--owner-password= : Owner password}
        {--plan= : Plan id}
        {--mode= : manual, cpanel or mysql}
        {--db-host=127.0.0.1}
        {--db-port=3306}
        {--db-name=}
        {--db-username=}
        {--db-password=}';

    protected $description = 'Provision a tenant.';
}

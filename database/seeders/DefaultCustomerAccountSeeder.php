<?php

namespace Database\Seeders;

use App\Services\Platform\DefaultCustomerAccountService;
use Illuminate\Database\Seeder;

class DefaultCustomerAccountSeeder extends Seeder
{
    public function run(DefaultCustomerAccountService $defaultAccount): void
    {
        $defaultAccount->get();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersResourceTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class BillingResourceController extends Controller
{
    use RendersResourceTable;

    public function __invoke(Request $request, string $resource): Response
    {
        $map = [
            'payments' => ['Payments', 'payments', ['provider', 'amount', 'currency', 'status', 'paid_at']],
            'invoices' => ['Invoices', 'invoices', ['number', 'status', 'subtotal', 'tax_total', 'total', 'currency', 'due_on']],
            'refunds' => ['Refunds', 'refunds', ['amount', 'currency', 'status', 'reason', 'refunded_at']],
            'coupons' => ['Coupons', 'coupons', ['code', 'name', 'type', 'value', 'is_active', 'expires_at']],
            'taxes' => ['Taxes', 'tax_rates', ['name', 'country', 'rate', 'is_active']],
            'currencies' => ['Currencies', 'currencies', ['code', 'name', 'symbol', 'is_active']],
            'gateways' => ['Payment Gateways', 'gateways', ['name', 'provider', 'mode', 'is_active']],
        ];

        abort_unless(isset($map[$resource]), 404);

        [$title, $table, $keys] = $map[$resource];

        return $this->tablePage($request, $title, $table, $this->columns($keys), [
            'description' => 'Server-side records with audit-safe operational actions.',
        ]);
    }

    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => str($key)->headline()->toString(), 'searchable' => true])->all();
    }
}

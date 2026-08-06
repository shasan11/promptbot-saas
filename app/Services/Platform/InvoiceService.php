<?php

namespace App\Services\Platform;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $items = collect($data['items']);
            $subtotal = $items->sum(fn (array $item) => $item['quantity'] * $item['unit_amount']);
            $taxTotal = round((float) ($data['tax_total'] ?? 0), 2);

            $invoice = Invoice::create([
                'tenant_id' => $data['tenant_id'],
                'number' => $this->nextNumber(),
                'status' => $data['status'],
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
                'currency' => strtoupper($data['currency']),
                'issued_on' => $data['issued_on'],
                'due_on' => $data['due_on'] ?? null,
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'total' => $item['quantity'] * $item['unit_amount'],
                ]);
            }

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): void
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
    }

    public function reopen(Invoice $invoice): void
    {
        if ($invoice->status !== 'void') {
            $invoice->update(['status' => 'open', 'paid_at' => null]);
        }
    }

    public function void(Invoice $invoice): void
    {
        $invoice->update(['status' => 'void', 'voided_at' => now()]);
    }

    private function nextNumber(): string
    {
        $configured = (string) $this->settings->get('payment', 'invoice_prefix', 'INV');
        $prefix = Str::upper((string) preg_replace('/[^A-Za-z0-9-]/', '', $configured));
        $prefix = $prefix !== '' ? $prefix : 'INV';
        $count = Invoice::query()->lockForUpdate()->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}

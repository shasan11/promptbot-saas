<?php

namespace App\Services\Platform;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Manual, admin-issued invoicing. Invoices are immutable once created (no
 * edit endpoint exists) — correcting a mistake means voiding it and issuing
 * a new one, which keeps the financial trail honest.
 */
class InvoiceService
{
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

    public function void(Invoice $invoice): void
    {
        $invoice->update(['status' => 'void', 'voided_at' => now()]);
    }

    /**
     * Sequential invoice number. The lock keeps concurrent admin submissions
     * from colliding; acceptable for a low-volume manual invoicing workflow.
     */
    private function nextNumber(): string
    {
        $count = Invoice::query()->lockForUpdate()->count();

        return 'INV-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Services\Platform;

use App\Models\Invoice;

class InvoicePdfService
{
    public function make(Invoice $invoice): string
    {
        $lines = [config('platform.legal_company_name') ?: config('app.name', 'PromptBot'), 'Invoice '.$invoice->number, 'Status: '.strtoupper($invoice->status),
            'Issued: '.($invoice->issued_on?->format('Y-m-d') ?? 'Draft').'   Due: '.($invoice->due_on?->format('Y-m-d') ?? '-'),
            'Bill to: '.(data_get($invoice->billing_snapshot, 'billing_name') ?: $invoice->customerAccount?->name), ''];
        foreach ($invoice->items as $item) {
            $lines[] = $item->description.'  '.$item->quantity.' x '.number_format((float) $item->unit_amount, 2).'  = '.number_format((float) $item->total, 2).' '.$invoice->currency;
        }
        $lines[] = '';
        $lines[] = 'Subtotal: '.number_format((float) $invoice->subtotal, 2).' '.$invoice->currency;
        $lines[] = 'Discount: '.number_format((float) $invoice->discount_total, 2).' '.$invoice->currency;
        $lines[] = 'Tax: '.number_format((float) $invoice->tax_total, 2).' '.$invoice->currency;
        $lines[] = 'Total: '.number_format((float) $invoice->total, 2).' '.$invoice->currency;

        $content = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
        foreach ($lines as $line) {
            $content .= '('.$this->escape($line).") Tj\nT*\n";
        }
        $content .= "ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream", '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $index => $object) { $offsets[] = strlen($pdf); $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }
        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function escape(string $value): string { return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value); }
}

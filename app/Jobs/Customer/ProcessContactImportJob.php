<?php

namespace App\Jobs\Customer;

use App\Jobs\Concerns\TenantAware;
use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomerImport;
use App\Services\Customer\CustomerTimelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $timeout = 900;
    public int $tries = 2;

    public function __construct(private readonly int $importId) { $this->captureTenant(); }

    public function handle(CustomerTimelineService $timeline): void
    {
        $import = CustomerImport::findOrFail($this->importId);
        $import->update(['status' => 'processing']);
        $file = new SplFileObject($import->storage_path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $headers = array_map(fn ($value) => Str::snake(trim((string) $value)), $file->fgetcsv() ?: []);
        $allowed = ['first_name', 'last_name', 'display_name', 'email', 'secondary_email', 'phone', 'secondary_phone', 'country', 'timezone', 'preferred_language', 'status', 'external_id', 'company'];
        if ($headers === [] || array_diff($headers, $allowed) !== []) throw new \RuntimeException('CSV contains unsupported or missing headers.');

        $total = $created = $updated = $failed = 0; $failures = [];
        foreach ($file as $offset => $values) {
            if ($values === false || $values === [null]) continue;
            $total++;
            try {
                $row = array_combine($headers, array_pad($values, count($headers), null));
                validator($row, ['email' => ['nullable', 'email:rfc'], 'status' => ['nullable', 'in:active,inactive,blocked,vip'], 'country' => ['nullable', 'size:2']])->validate();
                if (! array_filter([$row['first_name'] ?? null, $row['last_name'] ?? null, $row['display_name'] ?? null, $row['email'] ?? null, $row['phone'] ?? null])) throw new \RuntimeException('A name, email, or phone is required.');
                DB::transaction(function () use ($row, $import, $timeline, &$created, &$updated): void {
                    $companyId = empty($row['company']) ? null : Company::firstOrCreate(['name' => trim($row['company'])], ['public_uuid' => Str::uuid(), 'status' => 'active', 'created_by' => $import->created_by])->id;
                    $contact = Contact::query()->when(! empty($row['external_id']), fn ($q) => $q->where('external_id', $row['external_id']))
                        ->when(empty($row['external_id']) && ! empty($row['email']), fn ($q) => $q->where('email', Str::lower(trim($row['email']))))->first();
                    $data = collect($row)->only(['first_name', 'last_name', 'display_name', 'email', 'secondary_email', 'phone', 'secondary_phone', 'country', 'timezone', 'preferred_language', 'status', 'external_id'])->map(fn ($value) => is_string($value) ? trim($value) : $value)->filter(fn ($value) => $value !== null && $value !== '')->all();
                    $data['display_name'] ??= trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($data['email'] ?? $data['phone']);
                    $data['status'] ??= 'active'; $data['source'] = 'csv_import'; $data['company_id'] = $companyId;
                    if ($contact) { $contact->update($data); $updated++; } else { $contact = Contact::create(array_merge($data, ['created_by' => $import->created_by])); $created++; }
                    $timeline->record($contact->wasRecentlyCreated ? 'contact.imported' : 'contact.import_updated', "Contact {$contact->display_name} was imported.", $contact, related: $contact, metadata: ['import_id' => $import->public_uuid]);
                });
            } catch (Throwable $exception) {
                $failed++; if (count($failures) < 500) $failures[] = ['row' => $offset + 1, 'message' => $exception->getMessage()];
            }
            if ($total % 100 === 0) $import->update(['processed_rows' => $total, 'created_rows' => $created, 'updated_rows' => $updated, 'failed_rows' => $failed]);
        }
        $import->update(['status' => $failed > 0 ? 'completed_with_errors' : 'completed', 'total_rows' => $total, 'processed_rows' => $total, 'created_rows' => $created, 'updated_rows' => $updated, 'failed_rows' => $failed, 'failure_report' => $failures, 'completed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        CustomerImport::find($this->importId)?->update(['status' => 'failed', 'failure_report' => [['row' => null, 'message' => $exception->getMessage()]], 'completed_at' => now()]);
    }
}

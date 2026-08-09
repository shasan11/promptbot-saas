<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\Customer\ProcessContactImportJob;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomerImport;
use App\Services\Tenancy\TenantStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('customers.import'), 403);
        return Inertia::render('Tenant/Admin/Customers/Imports/Index', ['imports' => CustomerImport::query()->with('creator:id,name')->latest()->paginate(20)]);
    }

    public function store(Request $request, TenantStorageService $storage): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('customers.import'), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $directory = $storage->path('customer-imports'); File::ensureDirectoryExists($directory);
        $originalFilename = $request->file('file')->getClientOriginalName();
        $filename = Str::uuid().'.csv'; $request->file('file')->move($directory, $filename);
        $import = CustomerImport::create(['resource_type' => 'contact', 'original_filename' => $originalFilename, 'storage_path' => $directory.DIRECTORY_SEPARATOR.$filename, 'created_by' => $request->user('tenant')->id]);
        ProcessContactImportJob::dispatch($import->id);
        return back()->with('status', 'Import queued for processing.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user('tenant')->can('customers.export'), 403);
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['first_name', 'last_name', 'display_name', 'email', 'secondary_email', 'phone', 'secondary_phone', 'country', 'timezone', 'preferred_language', 'status', 'external_id', 'company']);
            Contact::query()->with('company:id,name')->orderBy('id')->chunkById(500, function ($contacts) use ($output): void {
                foreach ($contacts as $contact) fputcsv($output, [$contact->first_name, $contact->last_name, $contact->display_name, $contact->email, $contact->secondary_email, $contact->phone, $contact->secondary_phone, $contact->country, $contact->timezone, $contact->preferred_language, $contact->status, $contact->external_id, $contact->company?->name]);
            });
            fclose($output);
        }, 'promptbot-contacts-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}

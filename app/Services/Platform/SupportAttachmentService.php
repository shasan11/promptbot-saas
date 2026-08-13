<?php

namespace App\Services\Platform;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SupportAttachmentService
{
    public const RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,txt,csv', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,text/plain,text/csv,application/csv', 'max:10240'];

    public function store(SupportTicket $ticket, UploadedFile $file): array
    {
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'bin');
        $path = $file->storeAs('support-attachments/'.$ticket->getKey(), Str::uuid().'.'.$extension, 'local');
        abort_unless($path, 500, 'The support attachment could not be stored.');

        return [
            'attachment_path' => $path,
            'attachment_name' => str(basename($file->getClientOriginalName()))->limit(255, '')->toString(),
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    public function createMessage(SupportTicket $ticket, array $attributes, ?UploadedFile $file = null): SupportTicketMessage
    {
        $attachment = $file ? $this->store($ticket, $file) : [];
        try {
            return $ticket->messages()->create([...$attributes, ...$attachment]);
        } catch (Throwable $exception) {
            $this->delete($attachment['attachment_path'] ?? null);
            throw $exception;
        }
    }

    public function delete(?string $path): void
    {
        if ($path) Storage::disk('local')->delete($path);
    }
}

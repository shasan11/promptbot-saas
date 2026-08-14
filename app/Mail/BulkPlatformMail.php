<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulkPlatformMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('mail.platform-template', ['body' => $this->htmlBody]);
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ErrorLogNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $errorData) {}

    public function build(): self
    {
        return $this->subject('TMS Error Alert')
            ->view('emails.error-log-notification');
    }
}

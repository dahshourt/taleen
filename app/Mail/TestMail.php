<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $templateContent;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($templateContent)
    {
        $this->templateContent = $templateContent;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->templateContent['subject'])
            ->view('mail.test_mail')
            ->with(['body' => $this->templateContent['body']]);

    }
}

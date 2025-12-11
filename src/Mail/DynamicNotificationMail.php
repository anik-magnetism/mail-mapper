<?php

namespace AnikNinja\MailMapper\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $bodyContent;
    public $meta;

    public function __construct(string $subject, string $body, array $meta = [])
    {
        $this->subjectLine = $subject;
        $this->bodyContent = $body;
        $this->meta = $meta;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('mail.dynamic_notification')
            ->with(['bodyContent' => $this->bodyContent, 'meta' => $this->meta]);
    }
}

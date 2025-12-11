<?php

namespace AnikNinja\MailMapper\Jobs;

use AnikNinja\MailMapper\Mail\DynamicNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Part\TextPart;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $emailData;

    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $to = $this->emailData['to'] ?? [];
            $cc = $this->emailData['cc'] ?? [];
            $subject = $this->emailData['subject'] ?? 'Notification';
            $body = $this->emailData['body'] ?? '';
            $meta = $this->emailData['meta'] ?? [];
            $attachments = $this->emailData['attachments'] ?? [];
            $useRaw = !empty($this->emailData['use_raw']) && $this->emailData['use_raw'] === true;

            $fromAddress = config('mail.from.address', 'no-reply@bbts.net');
            $fromName = config('mail.from.name', 'No Reply');

            // Log attachments info for debugging (filename, size, mime)
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $att) {
                    $filename = $att['filename'] ?? null;
                    $size = isset($att['content']) ? strlen($att['content']) : null;
                    $mime = $att['mime'] ?? null;
                    Log::info('SendEmailNotificationJob: attachment prepared', [
                        'filename' => $filename,
                        'size_bytes' => $size,
                        'mime' => $mime,
                    ]);
                }
            }

            // --- Decide Sending Strategy ---
            if ($useRaw) {
                // Raw/Direct Mail (safe for strict SMTP servers)
                Mail::send([], [], function ($message) use ($to, $cc, $subject, $body, $fromAddress, $fromName, $attachments) {
                    $message->from($fromAddress, $fromName)
                        ->to($to)
                        ->cc($cc)
                        ->subject($subject)
                        ->setBody(new TextPart($body, 'utf-8', 'html'));

                    // Attach any in-memory attachments (array of ['filename'=>..., 'content'=>..., 'mime'=>...])
                    if (!empty($attachments) && is_array($attachments)) {
                        foreach ($attachments as $att) {
                            if (!empty($att['content']) && !empty($att['filename'])) {
                                $mime = $att['mime'] ?? null;
                                if ($mime) {
                                    $message->attachData($att['content'], $att['filename'], ['mime' => $mime]);
                                } else {
                                    $message->attachData($att['content'], $att['filename']);
                                }
                            }
                        }
                    }
                });
            } else {
                try {
                    $mailable = new DynamicNotificationMail($subject, $body, $meta);
                    $mailable->from($fromAddress, $fromName);

                    // Attach any in-memory attachments to the mailable
                    if (!empty($attachments) && is_array($attachments)) {
                        foreach ($attachments as $att) {
                            if (!empty($att['content']) && !empty($att['filename'])) {
                                $mime = $att['mime'] ?? null;
                                if ($mime) {
                                    $mailable->attachData($att['content'], $att['filename'], ['mime' => $mime]);
                                } else {
                                    $mailable->attachData($att['content'], $att['filename']);
                                }
                            }
                        }
                    }

                    Mail::mailer('smtp')
                        ->to($to)
                        ->cc($cc)
                        ->send($mailable);
                } catch (\Throwable $mailError) {
                    Log::warning('Mailable send failed, retrying with raw mode: ' . $mailError->getMessage());
                    Mail::send([], [], function ($message) use ($to, $cc, $subject, $body, $fromAddress, $fromName, $attachments) {
                        $message->from($fromAddress, $fromName)
                            ->to($to)
                            ->cc($cc)
                            ->subject($subject)
                            ->setBody(new TextPart($body, 'utf-8', 'html'));

                        if (!empty($attachments) && is_array($attachments)) {
                            foreach ($attachments as $att) {
                                if (!empty($att['content']) && !empty($att['filename'])) {
                                    $mime = $att['mime'] ?? null;
                                    if ($mime) {
                                        $message->attachData($att['content'], $att['filename'], ['mime' => $mime]);
                                    } else {
                                        $message->attachData($att['content'], $att['filename']);
                                    }
                                }
                            }
                        }
                    });
                }
            }

            Log::info('Notification email sent.', [
                'mode' => $useRaw ? 'raw' : 'mailable',
                'to' => $to,
                'cc' => $cc,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send notification email: ' . $e->getMessage(), [
                'data' => $this->emailData,
            ]);
        }
    }
}

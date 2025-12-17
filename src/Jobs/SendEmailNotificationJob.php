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

            $fromAddress = config('mail-mapper.default_from.address', 'no-reply@bbts.net');
            $fromName = config('mail-mapper.default_from.name', 'No Reply');

            // Log attachments info for debugging (filename, size, mime)
            if (!empty($attachments) && is_array($attachments) && config('mail-mapper.enable_logging')) {
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

                    // Attach attachments: prefer path-based attach, fallback to in-memory content
                    if (!empty($attachments) && is_array($attachments)) {
                        foreach ($attachments as $att) {
                            if (!empty($att['path']) && file_exists($att['path'])) {
                                $options = [];
                                if (!empty($att['filename'])) {
                                    $options['as'] = $att['filename'];
                                }
                                if (!empty($att['mime'])) {
                                    $options['mime'] = $att['mime'];
                                }
                                $message->attach($att['path'], $options);
                                continue;
                            }

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
                            // Prefer attaching by path when available (avoids large memory usage)
                            if (!empty($att['path']) && file_exists($att['path'])) {
                                $options = [];
                                if (!empty($att['filename'])) {
                                    $options['as'] = $att['filename'];
                                }
                                if (!empty($att['mime'])) {
                                    $options['mime'] = $att['mime'];
                                }
                                $mailable->attach($att['path'], $options);
                                continue;
                            }

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
                    // Fallback to raw send if configured
                    /**
                     * Checks the 'mail-mapper.use_raw_fallback' configuration value.
                     * If the configuration is set to false, the caught mail error is re-thrown,
                     * preventing the use of a raw fallback mechanism for sending emails.
                     * If the configuration is true (or not set, defaults to true), the fallback is allowed.
                     *
                     * @throws \Exception Re-throws the original mail error if fallback is disabled.
                     */
                    if (!config('mail-mapper.use_raw_fallback', true)) {
                        throw $mailError; // re-throw if no fallback
                    }
                    Log::warning('Mailable send failed, retrying with raw mode: ' . $mailError->getMessage());
                    Mail::send([], [], function ($message) use ($to, $cc, $subject, $body, $fromAddress, $fromName, $attachments) {
                        $message->from($fromAddress, $fromName)
                            ->to($to)
                            ->cc($cc)
                            ->subject($subject)
                            ->setBody(new TextPart($body, 'utf-8', 'html'));

                        if (!empty($attachments) && is_array($attachments)) {
                            foreach ($attachments as $att) {
                                if (!empty($att['path']) && file_exists($att['path'])) {
                                    $options = [];
                                    if (!empty($att['filename'])) {
                                        $options['as'] = $att['filename'];
                                    }
                                    if (!empty($att['mime'])) {
                                        $options['mime'] = $att['mime'];
                                    }
                                    $message->attach($att['path'], $options);
                                    continue;
                                }

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

            if (config('mail-mapper.enable_logging')) {
                Log::info('Notification email sent.', [
                    'mode' => $useRaw ? 'raw' : 'mailable',
                    'to' => $to,
                    'cc' => $cc,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send notification email: ' . $e->getMessage(), [
                'data' => $this->emailData,
            ]);
        }
    }
}

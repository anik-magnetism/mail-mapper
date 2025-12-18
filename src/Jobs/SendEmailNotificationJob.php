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
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Class SendEmailNotificationJob
 *
 * Handles the asynchronous sending of dynamic notification emails with support for:
 * - Raw and Mailable-based sending strategies
 * - Attachments (file paths, in-memory, URLs, UploadedFile)
 * - Logging and error handling
 * - Fallback to raw sending if Mailable fails (configurable)
 *
 * Usage:
 *   dispatch(new SendEmailNotificationJob($emailData));
 *
 * $emailData array structure:
 *   - 'to'         => array of recipient emails
 *   - 'cc'         => array of CC emails
 *   - 'subject'    => string
 *   - 'body'       => string (HTML)
 *   - 'meta'       => array (optional, for template context)
 *   - 'attachments'=> array (optional, see below)
 *   - 'use_raw'    => bool (optional, force raw mode)
 *
 * Attachments can be:
 *   - ['path' => '/path/to/file', 'filename' => '...', 'mime' => '...']
 *   - ['content' => '...', 'filename' => '...', 'mime' => '...']
 *   - ['url' => 'https://...', 'filename' => '...']
 *   - UploadedFile instances (normalized before job)
 */
class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The email data payload.
     * @var array
     */
    public $emailData;

    /**
     * Create a new job instance.
     *
     * @param array $emailData  Email data and options (see class doc)
     * @return void
     */
    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    /**
     * Execute the job.
     *
     * Handles:
     * - Downloading URL attachments to temp files
     * - Logging attachment info if enabled
     * - Choosing between raw and Mailable sending
     * - Attaching files by path or in-memory content
     * - Fallback to raw mode if Mailable fails (configurable)
     * - Cleans up temp files after sending
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

            // Fetch any URL attachments to temporary files so they can be attached
            $tempFiles = [];
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $idx => $att) {
                    if (!empty($att['url']) && empty($att['path']) && empty($att['content'])) {
                        try {
                            $url = $att['url'];
                            $resp = Http::withOptions(['verify' => false])->get($url);
                            if ($resp->successful()) {
                                $content = $resp->body();
                                $filename = $att['filename'] ?? basename(parse_url($url, PHP_URL_PATH));
                                $tmp = tempnam(sys_get_temp_dir(), 'mailmap_');
                                $tmp2 = $tmp . '_' . $filename;
                                // rename temp file to preserve extension
                                if (rename($tmp, $tmp2)) {
                                    file_put_contents($tmp2, $content);
                                } else {
                                    file_put_contents($tmp, $content);
                                    $tmp2 = $tmp;
                                }
                                $attachments[$idx]['path'] = $tmp2;
                                $attachments[$idx]['filename'] = $filename;
                                if (function_exists('finfo_open')) {
                                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                    $attachments[$idx]['mime'] = finfo_file($finfo, $tmp2);
                                    finfo_close($finfo);
                                }
                                $tempFiles[] = $tmp2;
                            }
                        } catch (\Throwable $e) {
                            // ignore single attachment failure
                            continue;
                        }
                    }
                }
            }

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

            // cleanup any temporary files we created for url attachments
            if (!empty($tempFiles)) {
                foreach ($tempFiles as $f) {
                    try {
                        @unlink($f);
                    } catch (\Throwable $e) {
                        // ignore cleanup errors
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send notification email: ' . $e->getMessage(), [
                'data' => $this->emailData,
            ]);
        }
    }
}

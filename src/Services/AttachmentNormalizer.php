<?php

namespace AnikNinja\MailMapper\Services;

/**
 * Class AttachmentNormalizer
 *
 * Provides a static method to normalize various attachment formats into a consistent array structure
 * for use with email sending jobs. Supports in-memory content, file paths, URLs, and uploaded files.
 *
 * Supported input formats:
 * - Array with 'filename' and 'content' (in-memory, e.g. ['filename'=>'a.pdf','content'=>...])
 * - UploadedFile instances (Laravel/Symfony)
 * - String file paths (absolute or relative, will be resolved)
 * - String URLs (http/https, will be passed as 'url' for later download)
 *
 * Output format:
 * Each attachment is an array with at least:
 *   - 'filename' (string)
 *   - 'content' (string, for in-memory) OR 'path' (string, for file) OR 'url' (string, for remote)
 *   - 'mime' (string|null)
 *
 * Invalid or unresolvable attachments are skipped.
 */
class AttachmentNormalizer
{
    /**
     * Normalize a list of attachments into a consistent array format.
     *
     * @param array $attachments
     * @return array
     */
    public static function normalize(array $attachments): array
    {
        $out = [];

        /**
         * Attempt to resolve a file path from a raw string.
         * Tries direct, realpath, normalized slashes, and common Laravel paths.
         */
        $resolvePath = function (string $rawPath) {
            $raw = trim($rawPath, "\"' \r\n\t");
            if ($raw === '') {
                return null;
            }
            if (file_exists($raw)) {
                return $raw;
            }
            $real = @realpath($raw);
            if ($real && file_exists($real)) {
                return $real;
            }
            $norm = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $raw);
            if (file_exists($norm)) {
                return $norm;
            }
            $alt1 = str_replace('\\', '/', $raw);
            if (file_exists($alt1)) {
                return $alt1;
            }
            $alt2 = str_replace('/', '\\', $raw);
            if (file_exists($alt2)) {
                return $alt2;
            }
            if (function_exists('public_path')) {
                $p = public_path(ltrim($raw, '\\/'));
                if (file_exists($p)) {
                    return $p;
                }
            }
            if (function_exists('base_path')) {
                $p2 = base_path(ltrim($raw, '\\/'));
                if (file_exists($p2)) {
                    return $p2;
                }
            }
            $filename = basename($raw);
            $searchDirs = [];
            if (function_exists('public_path')) {
                $searchDirs[] = public_path();
            }
            if (function_exists('base_path')) {
                $searchDirs[] = base_path('public');
                $searchDirs[] = base_path();
            }
            foreach ($searchDirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                try {
                    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                    foreach ($it as $file) {
                        if ($file->isFile() && $file->getFilename() === $filename) {
                            return $file->getPathname();
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return null;
        };

        foreach ($attachments as $att) {
            // Accept forgiving formats: single-item numeric array containing a string (e.g. [ 'path/to/file' ])
            if (is_array($att) && count($att) === 1 && isset($att[0]) && is_string($att[0])) {
                $att = $att[0];
            }
            try {
                // In-memory content (already normalized)
                if (is_array($att) && isset($att['content']) && isset($att['filename'])) {
                    $out[] = [
                        'filename' => $att['filename'],
                        'content' => $att['content'],
                        'mime' => $att['mime'] ?? null,
                    ];
                    continue;
                }
                // UploadedFile (Laravel/Symfony) - preserve path
                if (function_exists('is_a') && (is_object($att) && (is_a($att, \Illuminate\Http\UploadedFile::class) || is_a($att, \Symfony\Component\HttpFoundation\File\UploadedFile::class)))) {
                    $path = $att->getRealPath();
                    if ($path && file_exists($path)) {
                        $out[] = [
                            'path' => $path,
                            'filename' => $att->getClientOriginalName() ?: basename($path),
                            'mime' => $att->getClientMimeType() ?? null,
                        ];
                    }
                    continue;
                }
                // String: URL or file path
                if (is_string($att)) {
                    // URL
                    if (filter_var($att, FILTER_VALIDATE_URL)) {
                        $out[] = [
                            'url' => $att,
                            'filename' => basename(parse_url($att, PHP_URL_PATH)),
                            'mime' => null,
                        ];
                        continue;
                    }
                    // Local file path (based on common Laravel paths)
                    $resolved = $resolvePath($att);
                    if ($resolved) {
                        $out[] = [
                            'path' => $resolved,
                            'filename' => basename($resolved),
                            'mime' => function_exists('mime_content_type') ? mime_content_type($resolved) : null,
                        ];
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                // skip malformed attachment entries
                continue;
            }
        }
        return $out;
    }
}

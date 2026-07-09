<?php

declare(strict_types=1);

namespace Connectors;

class GoogleDriveImporter
{
    private const FILES_ENDPOINT = 'https://www.googleapis.com/drive/v3/files';
    private const MAX_BYTES = 30 * 1024 * 1024; // matches both upload pipelines

    /**
     * Export formats for Google-native files, per import target.
     * Voice documents accept pdf/txt/md; chat accepts pdf + spreadsheets.
     */
    private const EXPORT_MAP = [
        'voice' => [
            'application/vnd.google-apps.document' => ['application/pdf', 'pdf'],
            'application/vnd.google-apps.spreadsheet' => ['application/pdf', 'pdf'],
            'application/vnd.google-apps.presentation' => ['application/pdf', 'pdf'],
        ],
        'chat' => [
            'application/vnd.google-apps.document' => ['application/pdf', 'pdf'],
            'application/vnd.google-apps.spreadsheet' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
            'application/vnd.google-apps.presentation' => ['application/pdf', 'pdf'],
        ],
    ];

    /** Direct-download mime allowlist per import target. */
    private const MIME_ALLOWLIST = [
        'voice' => [
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
        ],
        'chat' => [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/csv' => 'csv',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ],
    ];

    private GoogleTokenService $tokenService;

    public function __construct(?GoogleTokenService $tokenService = null)
    {
        $this->tokenService = $tokenService ?? new GoogleTokenService();
    }

    /**
     * Downloads (or exports) a Drive file into a temp file, enforcing the
     * target's mime/size rules.
     *
     * @param string $target 'voice' or 'chat'
     * @return array{tmp_path: string, name: string, mime: string, extension: string, size: int, exported: bool, metadata: array}
     */
    public function fetchToTemp(int $accountId, string $fileId, string $target): array
    {
        if (!isset(self::MIME_ALLOWLIST[$target])) {
            throw new \InvalidArgumentException('Unknown import target: ' . $target);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/', $fileId)) {
            throw new \InvalidArgumentException('Invalid Drive file id');
        }

        $accessToken = $this->tokenService->freshAccessToken($accountId)['access_token'];
        $metadata = $this->fetchMetadata($accessToken, $fileId);

        $sourceMime = (string)($metadata['mimeType'] ?? '');
        $name = (string)($metadata['name'] ?? 'drive-file');
        $declaredSize = isset($metadata['size']) ? (int)$metadata['size'] : null;

        if ($declaredSize !== null && $declaredSize > self::MAX_BYTES) {
            throw new ConnectorImportException('file_too_large', 'The file exceeds the 30MB limit');
        }

        $exportMap = self::EXPORT_MAP[$target];
        if (isset($exportMap[$sourceMime])) {
            [$exportMime, $extension] = $exportMap[$sourceMime];
            $url = self::FILES_ENDPOINT . '/' . rawurlencode($fileId) . '/export?' . http_build_query(['mimeType' => $exportMime]);
            $mime = $exportMime;
            $exported = true;
            if (!str_ends_with(strtolower($name), '.' . $extension)) {
                $name .= '.' . $extension;
            }
        } elseif (isset(self::MIME_ALLOWLIST[$target][$sourceMime])) {
            $extension = self::MIME_ALLOWLIST[$target][$sourceMime];
            $url = self::FILES_ENDPOINT . '/' . rawurlencode($fileId) . '?alt=media';
            $mime = $sourceMime;
            $exported = false;
        } else {
            throw new ConnectorImportException(
                'unsupported_type',
                'This file type is not supported here (' . ($sourceMime ?: 'unknown') . ')'
            );
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'claara_drive_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Could not create a temporary file');
        }

        try {
            $size = $this->downloadToFile($url, $accessToken, $tmpPath);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            throw $e;
        }

        if ($size === 0) {
            @unlink($tmpPath);
            throw new ConnectorImportException('empty_file', 'The downloaded file is empty');
        }

        return [
            'tmp_path' => $tmpPath,
            'name' => $name,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $size,
            'exported' => $exported,
            'metadata' => $metadata,
        ];
    }

    private function fetchMetadata(string $accessToken, string $fileId): array
    {
        $url = self::FILES_ENDPOINT . '/' . rawurlencode($fileId)
            . '?' . http_build_query(['fields' => 'id,name,mimeType,size,version,md5Checksum,webViewLink']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status === 404) {
            throw new ConnectorImportException('not_found', 'File not found in Google Drive (was it shared with Claara via the picker?)');
        }
        $data = $response !== false ? json_decode((string)$response, true) : null;
        if ($status !== 200 || !is_array($data)) {
            throw new \RuntimeException('Drive metadata request failed with HTTP ' . $status);
        }
        return $data;
    }

    private function downloadToFile(string $url, string $accessToken, string $destPath): int
    {
        $fh = fopen($destPath, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Could not open temporary file for writing');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            // Abort mid-transfer if the payload exceeds the cap.
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $downloadTotal, $downloaded) {
                return ($downloaded > self::MAX_BYTES || $downloadTotal > self::MAX_BYTES) ? 1 : 0;
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $aborted = curl_errno($ch) === CURLE_ABORTED_BY_CALLBACK;
        curl_close($ch);
        fclose($fh);

        if ($aborted) {
            throw new ConnectorImportException('file_too_large', 'The file exceeds the 30MB limit');
        }
        if ($ok === false || $status !== 200) {
            throw new \RuntimeException('Drive download failed with HTTP ' . $status);
        }

        return (int)filesize($destPath);
    }
}

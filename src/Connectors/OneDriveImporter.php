<?php

declare(strict_types=1);

namespace Connectors;

class OneDriveImporter
{
    private const ITEMS_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/drive/items';
    private const MAX_BYTES = 30 * 1024 * 1024; // matches both upload pipelines

    /** Extensions downloadable as-is, per import target (ext => mime). */
    private const DIRECT_ALLOWLIST = [
        'voice' => [
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
        ],
        'chat' => [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ];

    /**
     * Extensions Graph can convert to PDF (subset of the documented list that
     * makes sense per target). Voice also converts spreadsheets to PDF.
     */
    private const PDF_CONVERT = [
        'voice' => ['doc', 'docx', 'dot', 'dotx', 'rtf', 'htm', 'html', 'odt', 'odp', 'ods', 'pps', 'ppsx', 'ppt', 'pptx', 'xls', 'xlsm', 'xlsx', 'eml', 'msg', 'tif', 'tiff'],
        'chat' => ['doc', 'docx', 'dot', 'dotx', 'rtf', 'htm', 'html', 'odt', 'odp', 'pps', 'ppsx', 'ppt', 'pptx', 'eml', 'msg', 'tif', 'tiff'],
    ];

    private ConnectorTokenService $tokenService;

    public function __construct(?ConnectorTokenService $tokenService = null)
    {
        $this->tokenService = $tokenService ?? new ConnectorTokenService(new MicrosoftOneDriveProvider());
    }

    /**
     * Downloads (or converts to PDF) a OneDrive item into a temp file,
     * enforcing the target's extension/size rules.
     *
     * @param string $target 'voice' or 'chat'
     * @return array{tmp_path: string, name: string, mime: string, extension: string, size: int, exported: bool, metadata: array}
     */
    public function fetchToTemp(int $accountId, string $itemId, string $target): array
    {
        if (!isset(self::DIRECT_ALLOWLIST[$target])) {
            throw new \InvalidArgumentException('Unknown import target: ' . $target);
        }
        if (!preg_match('/^[A-Za-z0-9!._-]{5,250}$/', $itemId)) {
            throw new \InvalidArgumentException('Invalid OneDrive item id');
        }

        $accessToken = $this->tokenService->freshAccessToken($accountId)['access_token'];
        $metadata = $this->fetchMetadata($accessToken, $itemId);

        if (isset($metadata['folder'])) {
            throw new ConnectorImportException('unsupported_type', 'Folders cannot be imported; pick a file');
        }

        $name = (string)($metadata['name'] ?? 'onedrive-file');
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $declaredSize = isset($metadata['size']) ? (int)$metadata['size'] : null;
        if ($declaredSize !== null && $declaredSize > self::MAX_BYTES) {
            throw new ConnectorImportException('file_too_large', 'The file exceeds the 30MB limit');
        }

        $base = self::ITEMS_ENDPOINT . '/' . rawurlencode($itemId) . '/content';

        if (isset(self::DIRECT_ALLOWLIST[$target][$ext])) {
            $url = $base;
            $mime = self::DIRECT_ALLOWLIST[$target][$ext];
            $extension = $ext;
            $exported = false;
        } elseif (in_array($ext, self::PDF_CONVERT[$target], true)) {
            $url = $base . '?format=pdf';
            $mime = 'application/pdf';
            $extension = 'pdf';
            $exported = true;
            $name .= '.pdf';
        } else {
            throw new ConnectorImportException(
                'unsupported_type',
                'This file type is not supported here (.' . ($ext ?: '?') . ')'
            );
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'claara_onedrive_');
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

    private function fetchMetadata(string $accessToken, string $itemId): array
    {
        $url = self::ITEMS_ENDPOINT . '/' . rawurlencode($itemId)
            . '?' . http_build_query(['$select' => 'id,name,size,file,folder,webUrl,eTag,cTag']);

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
            throw new ConnectorImportException('not_found', 'File not found in OneDrive');
        }
        $data = $response !== false ? json_decode((string)$response, true) : null;
        if ($status !== 200 || !is_array($data)) {
            throw new \RuntimeException('OneDrive metadata request failed with HTTP ' . $status);
        }
        return $data;
    }

    private function downloadToFile(string $url, string $accessToken, string $destPath): int
    {
        $fh = fopen($destPath, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Could not open temporary file for writing');
        }

        // Graph answers 302 → preauthenticated URL. cURL follows it and (by
        // default) drops the Authorization header on the cross-host redirect,
        // which is exactly what the preauthenticated URL expects.
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
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
            throw new \RuntimeException('OneDrive download failed with HTTP ' . $status);
        }

        return (int)filesize($destPath);
    }
}

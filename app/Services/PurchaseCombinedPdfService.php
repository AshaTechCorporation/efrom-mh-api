<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

class PurchaseCombinedPdfService
{
    public function mergePdfSources(array $sources): string
    {
        if (empty($sources)) {
            throw new RuntimeException('No PDF sources were provided.');
        }

        $tempDir = storage_path('app/purchase-combined-pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $tempFiles = [];
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'tempDir' => $tempDir,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $mpdf->SetDisplayMode('fullpage');

        try {
            foreach (array_values($sources) as $index => $source) {
                $path = $this->materializeSource($source, $index, $tempDir, $tempFiles);
                $pageCount = $mpdf->setSourceFile($path);

                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $templateId = $mpdf->importPage($pageNumber);
                    $mpdf->AddPage();
                    $mpdf->useTemplate($templateId, 0, 0, null, null, true);
                }
            }

            return $mpdf->Output('', Destination::STRING_RETURN);
        } finally {
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }
    }

    public function attachmentPdfPaths($attachments): array
    {
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : [$attachments];
        }

        if (!is_array($attachments)) {
            return [];
        }

        $paths = [];
        foreach ($attachments as $attachment) {
            $rawPath = $this->attachmentPath($attachment);
            if ($rawPath === '') {
                continue;
            }

            if (!$this->isPdfPath($rawPath)) {
                throw new RuntimeException('Only PDF attachments can be merged: ' . $rawPath);
            }

            $resolvedPath = $this->resolveLocalPath($rawPath);
            if (!$resolvedPath) {
                throw new RuntimeException('Attachment file was not found: ' . $rawPath);
            }

            $paths[] = $resolvedPath;
        }

        return array_values(array_unique($paths));
    }

    private function materializeSource(array $source, int $index, string $tempDir, array &$tempFiles): string
    {
        if (!empty($source['path'])) {
            $path = (string) $source['path'];
            if (!is_file($path)) {
                throw new RuntimeException('PDF source file was not found: ' . $path);
            }

            return $path;
        }

        if (!array_key_exists('content', $source)) {
            throw new RuntimeException('PDF source content is missing.');
        }

        $name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string) ($source['name'] ?? ('source-' . $index)));
        $path = $tempDir . '/' . uniqid('purchase-source-' . $index . '-', true) . '-' . $name . '.pdf';
        file_put_contents($path, (string) $source['content']);
        $tempFiles[] = $path;

        return $path;
    }

    private function attachmentPath($attachment): string
    {
        if (is_string($attachment)) {
            return trim($attachment);
        }

        if (is_object($attachment)) {
            $attachment = (array) $attachment;
        }

        if (is_array($attachment)) {
            foreach (['file_path', 'path', 'file_url', 'url', 'fileName', 'file_name', 'name'] as $key) {
                if (isset($attachment[$key]) && trim((string) $attachment[$key]) !== '') {
                    return trim((string) $attachment[$key]);
                }
            }
        }

        return '';
    }

    private function isPdfPath(string $path): bool
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        return strtolower(pathinfo((string) ($parsedPath ?: $path), PATHINFO_EXTENSION)) === 'pdf';
    }

    private function resolveLocalPath(string $path): ?string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $cleanPath = urldecode((string) ($parsedPath ?: $path));

        if (is_file($cleanPath)) {
            return $cleanPath;
        }

        $relativePath = ltrim($cleanPath, '/\\');
        $candidates = [
            public_path($relativePath),
            base_path($relativePath),
            storage_path('app/' . $relativePath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

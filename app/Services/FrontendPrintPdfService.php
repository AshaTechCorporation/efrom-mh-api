<?php

namespace App\Services;

use RuntimeException;

class FrontendPrintPdfService
{
    public function renderPurchaseOrderPdf($id): string
    {
        return $this->renderFrontendRouteToPdf('/print/purchase-order/' . rawurlencode((string) $id));
    }

    public function renderPurchaseRequisitionPdf($id): string
    {
        return $this->renderFrontendRouteToPdf('/print/purchase-requisition/' . rawurlencode((string) $id));
    }

    private function renderFrontendRouteToPdf(string $path): string
    {
        $chrome = $this->findChromeBinary();
        if (!$chrome) {
            throw new RuntimeException(
                'Chrome/Chromium is required to render frontend print PDF. ' .
                'Install chromium in the API container or set CHROME_BINARY.'
            );
        }

        $tempDir = storage_path('app/frontend-print-pdf');
        if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true)) {
            throw new RuntimeException('Unable to create frontend print PDF temporary directory.');
        }

        $outputPath = $tempDir . '/' . uniqid('frontend-print-', true) . '.pdf';
        $userDataDir = $tempDir . '/' . uniqid('chrome-profile-', true);
        if (!is_dir($userDataDir) && !mkdir($userDataDir, 0775, true)) {
            throw new RuntimeException('Unable to create Chrome temporary profile directory.');
        }

        $url = $this->frontendUrl($path);
        $waitMs = max(1000, (int) config('services.frontend_print.render_wait_ms', 15000));

        try {
            $this->runCommand([
                $chrome,
                '--headless=new',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-sandbox',
                '--hide-scrollbars',
                '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=' . $waitMs,
                '--print-to-pdf=' . $outputPath,
                '--print-to-pdf-no-header',
                '--no-pdf-header-footer',
                '--user-data-dir=' . $userDataDir,
                $url,
            ]);

            if (!is_file($outputPath)) {
                throw new RuntimeException('Chrome did not produce a frontend print PDF.');
            }

            $content = file_get_contents($outputPath);
            if (!is_string($content) || substr($content, 0, 4) !== '%PDF') {
                throw new RuntimeException('Frontend print renderer returned invalid PDF content.');
            }

            return $content;
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
            $this->deleteDirectory($userDataDir);
        }
    }

    private function frontendUrl(string $path): string
    {
        $baseUrl = config('services.frontend_print.base_url');

        if (!$baseUrl) {
            throw new RuntimeException('FRONTEND_PRINT_BASE_URL or FRONTEND_URL must be configured.');
        }

        return rtrim((string) $baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function findChromeBinary(): ?string
    {
        $configuredPath = config('services.frontend_print.chrome_binary');
        if ($configuredPath && is_executable($configuredPath)) {
            return $configuredPath;
        }

        foreach ([
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable'] as $binary) {
            $path = $this->findExecutableOnPath($binary);
            if ($path) {
                return $path;
            }
        }

        return null;
    }

    private function findExecutableOnPath(string $binary): ?string
    {
        $output = [];
        $exitCode = 1;
        @exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        $path = trim($output[0]);
        return is_executable($path) ? $path : null;
    }

    private function runCommand(array $command): void
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is required to render frontend print PDFs.');
        }

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start frontend print renderer.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = trim($stderr ?: $stdout);
            throw new RuntimeException('Frontend print renderer failed: ' . ($message ?: 'exit code ' . $exitCode));
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (!is_array($items)) {
            @rmdir($directory);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}

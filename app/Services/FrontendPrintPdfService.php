<?php

namespace App\Services;

use RuntimeException;

class FrontendPrintPdfService
{
    private const MAX_CHROME_RUNTIME_ROOT_LENGTH = 40;

    public function renderPurchaseOrderPdf($id, array $query = []): string
    {
        return $this->renderFrontendRouteToPdf('/print/purchase-order/' . rawurlencode((string) $id), $query);
    }

    public function renderPurchaseRequisitionPdf($id, array $query = []): string
    {
        return $this->renderFrontendRouteToPdf('/print/purchase-requisition/' . rawurlencode((string) $id), $query);
    }

    private function renderFrontendRouteToPdf(string $path, array $query = []): string
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
        $chromeRuntimeRoot = $this->chromeRuntimeRoot();
        $profileToken = $this->shortToken();
        $userDataDir = $chromeRuntimeRoot . '/efp-u-' . $profileToken;
        $chromeCacheDir = $chromeRuntimeRoot . '/efp-c-' . $profileToken;
        $chromeTmpDir = $chromeRuntimeRoot . '/efp-t-' . $profileToken;
        $this->ensureDirectory($userDataDir);
        $this->ensureDirectory($chromeCacheDir);
        $this->ensureDirectory($chromeTmpDir);
        @chmod($userDataDir, 0700);
        @chmod($chromeTmpDir, 0700);

        $url = $this->frontendUrl($path, $query);
        $waitMs = max(1000, (int) config('services.frontend_print.render_wait_ms', 15000));

        try {
            $this->runCommand([
                $chrome,
                '--headless=new',
                '--disable-background-networking',
                '--disable-breakpad',
                '--disable-component-update',
                '--disable-crash-reporter',
                '--disable-default-apps',
                '--disable-extensions',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-sync',
                '--no-sandbox',
                '--no-first-run',
                '--no-default-browser-check',
                '--password-store=basic',
                '--use-mock-keychain',
                '--disable-features=AutofillServerCommunication,BackForwardCache,Crashpad,MediaRouter,OptimizationHints,Translate',
                '--hide-scrollbars',
                '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=' . $waitMs,
                '--print-to-pdf=' . $outputPath,
                '--print-to-pdf-no-header',
                '--no-pdf-header-footer',
                '--user-data-dir=' . $userDataDir,
                $url,
            ], $outputPath, max(30, (int) ceil($waitMs / 1000) + 20), [
                'HOME' => $userDataDir,
                'TMPDIR' => $chromeTmpDir,
                'TMP' => $chromeTmpDir,
                'TEMP' => $chromeTmpDir,
                'XDG_CONFIG_HOME' => $userDataDir,
                'XDG_CACHE_HOME' => $chromeCacheDir,
                'XDG_RUNTIME_DIR' => $chromeTmpDir,
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
            $this->deleteDirectory($chromeCacheDir);
            $this->deleteDirectory($chromeTmpDir);
        }
    }

    private function frontendUrl(string $path, array $query = []): string
    {
        $baseUrl = config('services.frontend_print.base_url');

        if (!$baseUrl) {
            throw new RuntimeException('FRONTEND_PRINT_BASE_URL or FRONTEND_URL must be configured.');
        }

        $url = rtrim((string) $baseUrl, '/') . '/' . ltrim($path, '/');
        $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));

        return $queryString !== '' ? $url . '?' . $queryString : $url;
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

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            throw new RuntimeException('Unable to create frontend print temporary directory: ' . $directory);
        }
    }

    private function chromeRuntimeRoot(): string
    {
        $candidates = array_filter([
            config('services.frontend_print.chrome_runtime_dir'),
            '/tmp',
            sys_get_temp_dir(),
        ], static fn ($candidate) => is_string($candidate) && trim($candidate) !== '');

        foreach ($candidates as $candidate) {
            $directory = rtrim((string) $candidate, DIRECTORY_SEPARATOR);
            if (!$this->isShortEnoughForChromeSockets($directory)) {
                continue;
            }

            if ($this->ensureWritableDirectory($directory)) {
                return $directory;
            }
        }

        throw new RuntimeException('Unable to find a short writable Chrome runtime directory. Set FRONTEND_PRINT_CHROME_RUNTIME_DIR to a short path such as /tmp.');
    }

    private function isShortEnoughForChromeSockets(string $directory): bool
    {
        return DIRECTORY_SEPARATOR !== '/' || strlen($directory) <= self::MAX_CHROME_RUNTIME_ROOT_LENGTH;
    }

    private function ensureWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            return false;
        }

        @chmod($directory, 0777);

        return is_writable($directory);
    }

    private function shortToken(): string
    {
        try {
            return bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            return substr(str_replace('.', '', uniqid('', true)), -8);
        }
    }

    private function runCommand(
        array $command,
        ?string $expectedPdfPath = null,
        int $timeoutSeconds = 60,
        array $environment = []
    ): void
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is required to render frontend print PDFs.');
        }

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $this->processEnvironment($environment));

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start frontend print renderer.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);
        $lastPdfSize = -1;
        $lastPdfSizeChangedAt = $startedAt;
        $terminatedAfterPdf = false;

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if ($expectedPdfPath && is_file($expectedPdfPath)) {
                clearstatcache(true, $expectedPdfPath);
                $currentPdfSize = (int) filesize($expectedPdfPath);

                if ($currentPdfSize !== $lastPdfSize) {
                    $lastPdfSize = $currentPdfSize;
                    $lastPdfSizeChangedAt = microtime(true);
                } elseif (
                    $currentPdfSize > 4
                    && microtime(true) - $lastPdfSizeChangedAt >= 0.75
                    && $this->isPdfFile($expectedPdfPath)
                ) {
                    $terminatedAfterPdf = true;
                    proc_terminate($process);
                    break;
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (microtime(true) - $startedAt > $timeoutSeconds) {
                proc_terminate($process);
                $message = trim($stderr ?: $stdout);
                throw new RuntimeException('Frontend print renderer timed out: ' . ($message ?: $timeoutSeconds . ' seconds'));
            }

            usleep(100000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($terminatedAfterPdf) {
            return;
        }

        if ($exitCode !== 0) {
            if ($expectedPdfPath && is_file($expectedPdfPath) && $this->isPdfFile($expectedPdfPath)) {
                return;
            }

            $message = trim($stderr ?: $stdout);
            throw new RuntimeException('Frontend print renderer failed: ' . ($message ?: 'exit code ' . $exitCode));
        }
    }

    private function processEnvironment(array $overrides): array
    {
        $environment = [];

        foreach ($_ENV as $key => $value) {
            $this->addProcessEnvironmentValue($environment, $key, $value);
        }

        foreach (['PATH', 'SystemRoot', 'WINDIR'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $this->addProcessEnvironmentValue($environment, $key, $value);
            }
        }

        foreach ($overrides as $key => $value) {
            $this->addProcessEnvironmentValue($environment, $key, $value);
        }

        return $environment;
    }

    private function addProcessEnvironmentValue(array &$environment, $key, $value): void
    {
        if (!is_string($key) && !is_int($key)) {
            return;
        }

        if ($value === null) {
            $environment[(string) $key] = '';
            return;
        }

        if (is_scalar($value)) {
            $environment[(string) $key] = (string) $value;
        }
    }

    private function isPdfFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === '%PDF';
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

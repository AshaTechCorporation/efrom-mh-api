<?php

namespace Tests\Unit;

use App\Exceptions\PdfMergeUserException;
use App\Services\PurchaseCombinedPdfService;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tests\TestCase;

class PurchaseCombinedPdfServiceTest extends TestCase
{
    public function test_merge_pdf_sources_combines_pages(): void
    {
        $tempDir = storage_path('framework/testing/purchase-combined-pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $first = $this->createPdfFile($tempDir, 'first.pdf', 'First document');
        $second = $this->createPdfFile($tempDir, 'second.pdf', 'Second document');
        $merged = $tempDir . '/merged.pdf';

        try {
            $service = new PurchaseCombinedPdfService();
            $content = $service->mergePdfSources([
                ['path' => $first],
                ['path' => $second],
            ]);

            $this->assertStringStartsWith('%PDF', $content);
            file_put_contents($merged, $content);

            $reader = new Mpdf(['tempDir' => $tempDir]);
            $this->assertSame(2, $reader->setSourceFile($merged));
        } finally {
            foreach ([$first, $second, $merged] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function test_attachment_pdf_paths_rejects_non_pdf_paths(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ไม่ใช่ PDF');

        (new PurchaseCombinedPdfService())->attachmentPdfPaths([
            '/uploads/purchase-orders/quotation.docx',
        ]);
    }

    public function test_encrypted_pdf_sources_report_file_name(): void
    {
        $tempDir = storage_path('framework/testing/purchase-combined-pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $encrypted = $this->createEncryptedPdfStub($tempDir, 'protected-quote.pdf');

        try {
            (new PurchaseCombinedPdfService())->mergePdfSources([
                ['path' => $encrypted],
            ]);
        } catch (PdfMergeUserException $e) {
            $this->assertStringContainsString('protected-quote.pdf', $e->getMessage());
            $this->assertStringContainsString('ถูกเข้ารหัส', $e->getMessage());
            return;
        } finally {
            if (is_file($encrypted)) {
                @unlink($encrypted);
            }
        }

        $this->fail('Encrypted PDF should throw a user-facing PDF merge exception.');
    }

    public function test_merge_pdf_sources_normalizes_compressed_xref_pdfs(): void
    {
        if (!$this->isPdftocairoAvailable()) {
            $this->markTestSkipped('pdftocairo is required to normalize compressed xref PDF fixtures.');
        }

        $fixture = public_path('uploads/files/PR_2022-08-18_SSD And RAM.pdf');
        if (!is_file($fixture)) {
            $this->markTestSkipped('Compressed xref PDF fixture is not available in this environment.');
        }

        $tempDir = storage_path('framework/testing/purchase-combined-pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        $merged = $tempDir . '/compressed-xref-merged.pdf';

        try {
            $content = (new PurchaseCombinedPdfService())->mergePdfSources([
                ['path' => $fixture],
            ]);

            $this->assertStringStartsWith('%PDF', $content);
            file_put_contents($merged, $content);

            $reader = new Mpdf(['tempDir' => $tempDir]);
            $this->assertSame(1, $reader->setSourceFile($merged));
        } finally {
            if (is_file($merged)) {
                @unlink($merged);
            }
        }
    }

    public function test_rendered_image_normalization_handles_multiple_pages(): void
    {
        if (!$this->isPdftocairoAvailable()) {
            $this->markTestSkipped('pdftocairo is required to render the compressed xref PDF fixture.');
        }

        $fixture = public_path('uploads/files/PR_2022-08-18_SSD And RAM.pdf');
        if (!is_file($fixture)) {
            $this->markTestSkipped('Compressed xref PDF fixture is not available in this environment.');
        }

        $tempDir = storage_path('framework/testing/purchase-combined-pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $prefix = $tempDir . '/rendered-page';
        $target = $tempDir . '/rendered-two-pages.pdf';
        $renderedPage = $prefix . '-1.png';

        $this->runPdftocairo($fixture, $prefix);
        if (!is_file($renderedPage)) {
            $this->markTestSkipped('Unable to render compressed xref PDF fixture.');
        }

        try {
            $service = new PurchaseCombinedPdfService();
            $method = new \ReflectionMethod($service, 'createPdfFromRenderedImages');
            $method->setAccessible(true);
            $method->invoke($service, [$renderedPage, $renderedPage], $target, $tempDir);

            $this->assertFileExists($target);

            $reader = new Mpdf(['tempDir' => $tempDir]);
            $this->assertSame(2, $reader->setSourceFile($target));
        } finally {
            foreach (glob($prefix . '-*.png') ?: [] as $imagePath) {
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }

            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    private function createPdfFile(string $tempDir, string $fileName, string $body): string
    {
        $path = $tempDir . '/' . $fileName;
        $mpdf = new Mpdf(['tempDir' => $tempDir]);
        $mpdf->WriteHTML('<h1>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</h1>');
        file_put_contents($path, $mpdf->Output('', Destination::STRING_RETURN));

        return $path;
    }

    private function createEncryptedPdfStub(string $tempDir, string $fileName): string
    {
        $path = $tempDir . '/' . $fileName;
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n",
            "3 0 obj\n<< /Filter /Standard /V 1 /R 2 /O <> /U <> /P -4 >>\nendobj\n",
        ];

        $content = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($content);
            $content .= $object;
        }

        $xrefOffset = strlen($content);
        $content .= "xref\n0 4\n";
        $content .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $content .= sprintf("%010d 00000 n \n", $offset);
        }

        $content .= "trailer\n<< /Size 4 /Root 1 0 R /Encrypt 3 0 R >>\n";
        $content .= "startxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents($path, $content);

        return $path;
    }

    private function isPdftocairoAvailable(): bool
    {
        foreach ([
            env('PDFTOCAIRO_BINARY'),
            '/opt/homebrew/bin/pdftocairo',
            '/usr/local/bin/pdftocairo',
            '/usr/bin/pdftocairo',
        ] as $candidate) {
            if ($candidate && is_executable($candidate)) {
                return true;
            }
        }

        $output = [];
        $exitCode = 1;
        @exec('command -v pdftocairo 2>/dev/null', $output, $exitCode);

        return $exitCode === 0 && !empty($output[0]) && is_executable(trim($output[0]));
    }

    private function runPdftocairo(string $source, string $outputPrefix): void
    {
        $binary = $this->pdftocairoBinary();
        $process = proc_open([
            $binary,
            '-q',
            '-png',
            '-r',
            '144',
            $source,
            $outputPrefix,
        ], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            $this->fail('Unable to start pdftocairo.');
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, 'pdftocairo failed to render the fixture.');
    }

    private function pdftocairoBinary(): string
    {
        foreach ([
            env('PDFTOCAIRO_BINARY'),
            '/opt/homebrew/bin/pdftocairo',
            '/usr/local/bin/pdftocairo',
            '/usr/bin/pdftocairo',
        ] as $candidate) {
            if ($candidate && is_executable($candidate)) {
                return $candidate;
            }
        }

        $output = [];
        $exitCode = 1;
        @exec('command -v pdftocairo 2>/dev/null', $output, $exitCode);

        if ($exitCode === 0 && !empty($output[0]) && is_executable(trim($output[0]))) {
            return trim($output[0]);
        }

        $this->fail('pdftocairo is not available.');
    }
}

<?php

namespace Tests\Unit;

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
        $this->expectExceptionMessage('Only PDF attachments can be merged');

        (new PurchaseCombinedPdfService())->attachmentPdfPaths([
            '/uploads/purchase-orders/quotation.docx',
        ]);
    }

    private function createPdfFile(string $tempDir, string $fileName, string $body): string
    {
        $path = $tempDir . '/' . $fileName;
        $mpdf = new Mpdf(['tempDir' => $tempDir]);
        $mpdf->WriteHTML('<h1>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</h1>');
        file_put_contents($path, $mpdf->Output('', Destination::STRING_RETURN));

        return $path;
    }
}

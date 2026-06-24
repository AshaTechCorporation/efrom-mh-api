<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PurchaseDocumentAttachmentTest extends TestCase
{
    public function test_upload_multiple_files_rejects_non_pdf_when_pdf_policy_is_requested(): void
    {
        $response = $this->post('/api/upload_multiple_files', [
            'path' => 'uploads/tests/',
            'original' => 'Y',
            'allowed_extensions' => ['pdf'],
            'files' => [
                UploadedFile::fake()->create('quotation.docx', 1, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', '422');
    }

    public function test_purchase_requisition_rejects_non_pdf_attachments(): void
    {
        $response = $this->postJson('/api/purchase_requisitions', [
            'status' => 'draft',
            'attachments' => ['/uploads/purchase-requisitions/quotation.jpg'],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', '422')
            ->assertJsonFragment([
                'message' => 'Purchase Requisition attachments must be PDF files only: /uploads/purchase-requisitions/quotation.jpg',
            ]);
    }

    public function test_purchase_order_rejects_non_pdf_attachments(): void
    {
        $response = $this->postJson('/api/purchase_order', [
            'status' => 'draft',
            'attachments' => ['/uploads/purchase-orders/quotation.png'],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', '422')
            ->assertJsonFragment([
                'message' => 'Purchase Order attachments must be PDF files only: /uploads/purchase-orders/quotation.png',
            ]);
    }
}

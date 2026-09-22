<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentPdfAccessRoutesTest extends TestCase
{
    public function test_pdf_routes_reject_requests_without_a_bearer_token(): void
    {
        foreach (['purchase_order', 'purchase_requisitions', 'expenses_claims', 'allowance_after_10pm'] as $resource) {
            foreach (['print', 'combined-pdf', 'download-combined'] as $action) {
                $this->getJson("/api/{$resource}/1/{$action}")
                    ->assertUnauthorized()
                    ->assertJsonPath('message', 'Token Not Found');
            }
        }
    }

    public function test_resources_without_index_methods_reject_get_instead_of_server_error(): void
    {
        foreach ([
            'purchase_order',
            'purchase_requisitions',
            'charitable_contributions',
            'gift_hospitalities',
            'gift_hospitality_offerings',
        ] as $resource) {
            $this->getJson("/api/{$resource}")->assertStatus(405);
        }
    }
}

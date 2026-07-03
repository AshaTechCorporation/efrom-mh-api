<?php

namespace Tests\Unit;

use Tests\TestCase;

class PurchaseRequisitionPrintTemplateTest extends TestCase
{
    public function testReasonsForPurchasePrintFieldWrapsLongText(): void
    {
        $longReason = 'With reference to PO No. 10654, Item No. 2 has been cancelled, and this purchase request has been resubmitted to facilitate the change to an alternative brand and model with improved specifications and better cost-effectiveness.';

        $html = view('pdf.purchase-requisition', $this->viewData($longReason))->render();

        $this->assertStringContainsString(htmlspecialchars($longReason, ENT_QUOTES, 'UTF-8', false), $html);
        $this->assertMatchesRegularExpression('/\.reason-value\s*\{[^}]*white-space:\s*pre-wrap;/s', $html);
        $this->assertMatchesRegularExpression('/\.reason-value\s*\{[^}]*overflow-wrap:\s*anywhere;/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\.reason-value\s*\{[^}]*text-overflow/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\.reason-value\s*\{[^}]*overflow:\s*hidden/s', $html);
    }

    private function viewData(string $reason): array
    {
        return [
            'logoPath' => null,
            'currencyLabel' => 'Baht',
            'header' => [
                'prNo' => 'PR-2026-0001',
                'to' => 'Procurement',
                'date' => '03/07/2026',
                'requestedBy' => 'Noppon Chunak',
                'recommendedBy' => '',
                'deadline' => '',
                'receivedFrom' => '',
                'reasonsForPurchase' => $reason,
            ],
            'items' => [
                [
                    'item' => '1',
                    'description' => 'Office supplies',
                    'quantity' => '1',
                    'unitPrice' => '100.00',
                    'amount' => '100.00',
                ],
            ],
            'totals' => [
                'discount' => '0.00',
                'discountValue' => 0,
                'subTotal' => '100.00',
                'vat' => '7.00',
                'grandTotal' => '107.00',
            ],
            'terms' => [
                'paymentTerm' => '30 days',
                'otherConditions' => '',
            ],
            'approval' => [
                'requestedBy' => '',
                'requestedDate' => '',
                'verifiedByIS' => '',
                'verifiedByISDate' => '',
                'verifiedBy' => '',
                'verifiedByDate' => '',
                'approvedBy' => '',
                'approvedByDate' => '',
                'showApprovedBy2' => false,
                'approvedBy2' => '',
                'approvedBy2Date' => '',
                'acknowledgedBy' => '',
                'acknowledgedDate' => '',
                'needAssetCodeRegistration' => '',
                'actionByAdmin' => '',
                'actionByAdminDate' => '',
            ],
        ];
    }
}

<?php

namespace Tests\Unit;

use App\Http\Controllers\PurchaseOrderController;
use App\Models\PurchaseOrder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PurchaseOrderWorkflowResetTest extends TestCase
{
    public function testExplicitResetStartsSubmittedWorkflowFromNewSparePartVerifier(): void
    {
        $item = $this->purchaseOrderWithNewWorkflow();
        $snapshot = $this->oldWorkflowSnapshot();

        $this->applyUpdateWorkflow($item, $snapshot, false, false, true);

        $this->assertSame('RUS001', $item->verified_by);
        $this->assertSame('pending', $item->verified_by_status);
        $this->assertNull($item->verified_by_date);
        $this->assertSame('APP002', $item->approved_by);
        $this->assertNull($item->approved_by_status);
        $this->assertNull($item->signed_by_status);
        $this->assertNull($item->acknowledged_by_status);
    }

    public function testSubmittedUpdateWithoutExplicitResetPreservesExistingWorkflow(): void
    {
        $item = $this->purchaseOrderWithNewWorkflow();
        $snapshot = $this->oldWorkflowSnapshot();

        $this->applyUpdateWorkflow($item, $snapshot, false, false, false);

        $this->assertNull($item->verified_by);
        $this->assertNull($item->verified_by_status);
        $this->assertSame('APP001', $item->approved_by);
        $this->assertSame('pending', $item->approved_by_status);
        $this->assertSame('SIGN001', $item->signed_by);
        $this->assertSame('ACK001', $item->acknowledged_by);
    }

    private function purchaseOrderWithNewWorkflow(): PurchaseOrder
    {
        $item = new PurchaseOrder();
        $item->status = 'SUBMITTED';
        $item->purchase_request_by = 'REQ001';
        $item->purchase_request_by_status = 'approve';
        $item->verified_by = 'RUS001';
        $item->verified_by_status = 'pending';
        $item->verified_by_date = '2026-07-01 09:00:00';
        $item->approved_by = 'APP002';
        $item->approved_by_status = null;
        $item->circ = 'CIRC002';
        $item->circ_status = null;
        $item->signed_by = 'SIGN002';
        $item->signed_by_status = null;
        $item->acknowledged_by = 'ACK002';
        $item->acknowledged_by_status = null;

        return $item;
    }

    private function oldWorkflowSnapshot(): array
    {
        return [
            'status' => 'SUBMITTED',
            'purchase_request_by' => 'REQ001',
            'purchase_request_by_status' => 'approve',
            'purchase_request_by_date' => '2026-06-30 09:00:00',
            'verified_by' => null,
            'verified_by_status' => null,
            'verified_by_date' => null,
            'approved_by' => 'APP001',
            'approved_by_status' => 'pending',
            'approved_by_date' => null,
            'circ' => 'CIRC001',
            'circ_status' => null,
            'circ_date' => null,
            'signed_by' => 'SIGN001',
            'signed_by_status' => null,
            'signed_by_date' => null,
            'acknowledged_by' => 'ACK001',
            'acknowledged_by_status' => null,
            'acknowledged_by_date' => null,
        ];
    }

    private function applyUpdateWorkflow(
        PurchaseOrder $item,
        array $snapshot,
        bool $wasDraft,
        bool $isDraft,
        bool $resetWorkflow
    ): void {
        $reflection = new ReflectionClass(new PurchaseOrderController());
        $method = $reflection->getMethod('applyPurchaseOrderUpdateWorkflow');
        $method->setAccessible(true);
        $method->invoke(
            $reflection->newInstance(),
            $item,
            $snapshot,
            $wasDraft,
            $isDraft,
            $resetWorkflow
        );
    }
}

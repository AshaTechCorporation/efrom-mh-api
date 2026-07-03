<?php

namespace Tests\Unit;

use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PurchaseItemOptionalFieldTest extends TestCase
{
    public function testDraftRowsKeepManualPurchaseOrderItemWithoutDescription(): void
    {
        $this->assertFalse($this->invokeSkip(
            new PurchaseOrderController(),
            'shouldSkipPurchaseOrderItem',
            ['item' => 'A-01', 'description' => '', 'unit_price' => 0, 'amount' => 0],
            true
        ));
    }

    public function testDraftRowsKeepManualPurchaseRequisitionItemWithoutDescription(): void
    {
        $this->assertFalse($this->invokeSkip(
            new PurchaseRequisitionsController(),
            'shouldSkipPurchaseRequisitionItem',
            ['item' => 'A-01', 'description' => '', 'unit_price' => 0, 'amount' => 0],
            true
        ));
    }

    public function testDraftPlaceholderRowsAreSkipped(): void
    {
        $emptyRow = ['item' => '', 'description' => '', 'unit_price' => 0, 'amount' => 0];

        $this->assertTrue($this->invokeSkip(
            new PurchaseOrderController(),
            'shouldSkipPurchaseOrderItem',
            $emptyRow,
            true
        ));

        $this->assertTrue($this->invokeSkip(
            new PurchaseRequisitionsController(),
            'shouldSkipPurchaseRequisitionItem',
            $emptyRow,
            true
        ));
    }

    public function testSubmittedRowsCanOmitItemWhenDescriptionExists(): void
    {
        $row = ['item' => '', 'description' => 'Office supplies', 'unit_price' => 100, 'amount' => 100];

        $this->assertFalse($this->invokeSkip(
            new PurchaseOrderController(),
            'shouldSkipPurchaseOrderItem',
            $row,
            false
        ));

        $this->assertFalse($this->invokeSkip(
            new PurchaseRequisitionsController(),
            'shouldSkipPurchaseRequisitionItem',
            $row,
            false
        ));
    }

    public function testZeroStringIsTreatedAsManualItemValue(): void
    {
        $row = ['item' => '0', 'description' => '', 'unit_price' => 0, 'amount' => 0];

        $this->assertFalse($this->invokeSkip(
            new PurchaseOrderController(),
            'shouldSkipPurchaseOrderItem',
            $row,
            true
        ));

        $this->assertFalse($this->invokeSkip(
            new PurchaseRequisitionsController(),
            'shouldSkipPurchaseRequisitionItem',
            $row,
            true
        ));
    }

    private function invokeSkip(object $controller, string $method, array $row, bool $isDraft): bool
    {
        $reflection = new ReflectionClass($controller);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invoke($controller, $row, $isDraft);
    }
}

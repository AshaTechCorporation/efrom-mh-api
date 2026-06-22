<?php

namespace Tests\Unit;

use App\Services\PurchaseDocumentNumberService;
use PHPUnit\Framework\TestCase;

class PurchaseDocumentNumberServiceTest extends TestCase
{
    public function testFormatPadsSequenceByYear()
    {
        $service = new PurchaseDocumentNumberService();

        $this->assertSame('PR20260001', $service->format('PR', 2026, 1));
        $this->assertSame('PO20260123', $service->format('po', 2026, 123));
    }

    public function testYearFromDateAcceptsDateAndYear()
    {
        $service = new PurchaseDocumentNumberService();

        $this->assertSame(2027, $service->yearFromDate('2027-01-15'));
        $this->assertSame(2028, $service->yearFromDate('2028'));
    }

    public function testFormattedNumberValidation()
    {
        $service = new PurchaseDocumentNumberService();

        $this->assertTrue($service->isFormattedNumber('PR20260001', 'PR', 2026));
        $this->assertFalse($service->isFormattedNumber('PR20260001', 'PR', 2027));
        $this->assertFalse($service->isFormattedNumber('12', 'PO', 2026));
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\PurchaseRequisitionsController;
use App\Models\PurchaseRequisitions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class PurchaseRequisitionSubjectValidationTest extends TestCase
{
    /**
     * @dataProvider missingSubmittedSubjectProvider
     */
    public function testSubmittedPurchaseRequisitionRequiresSubject($subject): void
    {
        $result = $this->validateRequest([
            'to' => 'Procurement Team',
            'subject' => $subject,
            'date' => '2026-08-18',
            'items' => [['description' => 'Office supplies']],
        ], false);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('กรุณาระบุ subject', $result->getData(true)['message']);
    }

    public static function missingSubmittedSubjectProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    public function testSubmittedPurchaseRequisitionAcceptsSubject(): void
    {
        $result = $this->validateRequest([
            'to' => 'Procurement Team',
            'subject' => 'Office supplies',
            'date' => '2026-08-18',
            'items' => [['description' => 'Office supplies']],
        ], false);

        $this->assertNull($result);
    }

    /**
     * @dataProvider shortSubmittedSubjectProvider
     */
    public function testSubmittedPurchaseRequisitionRequiresMoreThanFiveSubjectCharacters(string $subject): void
    {
        $result = $this->validateRequest([
            'to' => 'Procurement Team',
            'subject' => $subject,
            'date' => '2026-08-18',
            'items' => [['description' => 'Office supplies']],
        ], false);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('422', $result->getData(true)['code']);
        $this->assertSame('Subject must be more than 5 characters', $result->getData(true)['message']);
    }

    public static function shortSubmittedSubjectProvider(): array
    {
        return [
            'one character' => ['A'],
            'five characters' => ['ABCDE'],
            'trimmed to five characters' => ['  ABCDE  '],
        ];
    }

    public function testDraftPurchaseRequisitionCanKeepSubjectEmpty(): void
    {
        $result = $this->validateRequest([
            'subject' => '',
        ], true);

        $this->assertNull($result);
    }

    /**
     * @dataProvider shortSubmittedSubjectProvider
     */
    public function testStoredDraftCannotBeSubmittedWithShortSubject(string $subject): void
    {
        $method = new ReflectionMethod(
            PurchaseRequisitionsController::class,
            'validateStoredPurchaseRequisitionForSubmit'
        );
        $method->setAccessible(true);

        $storedDraft = new PurchaseRequisitions();
        $storedDraft->setAttribute('to', 'Procurement Team');
        $storedDraft->setAttribute('subject', $subject);

        $result = $method->invoke(
            new PurchaseRequisitionsController(),
            $storedDraft
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('422', $result->getData(true)['code']);
        $this->assertSame('Subject must be more than 5 characters', $result->getData(true)['message']);
    }

    private function validateRequest(array $payload, bool $isDraft)
    {
        $method = new ReflectionMethod(
            PurchaseRequisitionsController::class,
            'validatePurchaseRequisitionRequest'
        );
        $method->setAccessible(true);

        return $method->invoke(
            new PurchaseRequisitionsController(),
            Request::create('/api/purchase_requisitions', 'POST', $payload),
            $isDraft
        );
    }
}

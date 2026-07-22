<?php

namespace Tests\Feature;

use App\Exports\PurchaseOrderExport;
use App\Http\Controllers\PurchaseOrderController;
use App\Models\PurchaseOrder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PurchaseOrderExportEmployeeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable()->unique();
            $table->string('initial')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('employees')->insert([
            'id' => 71,
            'code' => 'RUS001',
            'initial' => 'RUS.',
            'firstname' => 'Rus',
            'lastname' => 'Reviewer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function testExportEmployeeLookupResolvesCodesAndIdsToNames(): void
    {
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->verified_by = 'RUS001';
        $purchaseOrder->approved_by = '71';

        $controller = new PurchaseOrderController();
        $reflection = new ReflectionClass($controller);
        $lookupMethod = $reflection->getMethod('purchaseOrderExportEmployeeLookup');
        $lookupMethod->setAccessible(true);
        $nameMethod = $reflection->getMethod('purchaseOrderExportEmployeeName');
        $nameMethod->setAccessible(true);

        $lookup = $lookupMethod->invoke($controller, collect([$purchaseOrder]));

        $this->assertSame('RUS, Rus Reviewer', $nameMethod->invoke($controller, 'RUS001', $lookup));
        $this->assertSame('RUS, Rus Reviewer', $nameMethod->invoke($controller, 71, $lookup));
        $this->assertSame('Employee not found', $nameMethod->invoke($controller, 'UNKNOWN', $lookup));
        $this->assertSame('', $nameMethod->invoke($controller, null, $lookup));
    }

    public function testPurchaseOrderExportHeadingsIncludeEveryWorkflowPerson(): void
    {
        $headings = (new PurchaseOrderExport([]))->headings();

        $this->assertContains('Purchase Request By', $headings);
        $this->assertContains('Spare Part Verified By', $headings);
        $this->assertContains('Approved By', $headings);
        $this->assertContains('CIRC', $headings);
        $this->assertContains('Signed By', $headings);
        $this->assertContains('Acknowledged By', $headings);
        $this->assertContains('Created By', $headings);
    }
}

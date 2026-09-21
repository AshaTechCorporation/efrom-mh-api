<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderListQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createPurchaseOrderTable();
    }

    public function test_purchase_order_columns_sort_the_fields_shown_by_the_table(): void
    {
        $this->insertPurchaseOrder([
            'po_no' => 'PO-002',
            'subject' => 'Zulu Subject',
            'company' => 'Beta Company',
            'to' => 'Zulu Recipient',
            'requisition_date' => '2026-02-01',
            'quotation_no' => 'QT-200',
            'grand_total' => 200,
            'approved_by' => 'APPROVER',
            'approved_by_status' => 'approve',
        ]);
        $this->insertPurchaseOrder([
            'po_no' => 'PO-001',
            'subject' => 'Alpha Subject',
            'company' => 'Alpha Company',
            'to' => 'Alpha Recipient',
            'requisition_date' => '2026-01-01',
            'quotation_no' => 'QT-100',
            'grand_total' => 100,
            'approved_by' => 'APPROVER',
            'approved_by_status' => 'pending',
        ]);

        $expectations = [
            0 => ['po_no', 'PO-001'],
            1 => ['po_no', 'PO-002'],
            2 => ['subject', 'Alpha Subject'],
            3 => ['company', 'Alpha Company'],
            4 => ['to', 'Alpha Recipient'],
            5 => ['requisition_date', '2026-01-01'],
            6 => ['quotation_no', 'QT-100'],
            7 => ['grand_total', 100],
        ];

        foreach ($expectations as $column => [$field, $expected]) {
            $this->postJson('/api/purchase_order_page', [
                'order' => [['column' => $column, 'dir' => 'asc']],
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => false],
                'filters' => ['tab' => 'all', 'status' => '', 'company' => ''],
            ])->assertOk()
                ->assertJsonPath('data.data.0.' . $field, $expected);
        }
    }

    public function test_my_tab_search_and_sort_are_applied_before_each_page_slice(): void
    {
        $this->insertPurchaseOrder([
            'po_no' => 'PO-001',
            'subject' => 'Needle First',
            'company' => 'Alpha Company',
            'create_by' => 'TESTER',
        ]);
        $this->insertPurchaseOrder([
            'po_no' => 'PO-002',
            'subject' => 'Needle Second',
            'company' => 'Zulu Company',
            'purchase_request_by' => 'TESTER',
        ]);
        $this->insertPurchaseOrder([
            'po_no' => 'PO-003',
            'subject' => 'Needle Other',
            'company' => 'Middle Company',
            'create_by' => 'OTHER',
        ]);

        $request = [
            'employee_code' => 'TESTER',
            'order' => [['column' => 3, 'dir' => 'asc']],
            'start' => 1,
            'length' => 1,
            'search' => ['value' => 'Needle', 'regex' => false],
            'filters' => ['tab' => 'my', 'status' => '', 'company' => ''],
        ];

        $this->postJson('/api/purchase_order_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.po_no', 'PO-002')
            ->assertJsonPath('data.data.0.company', 'Zulu Company');
    }

    public function test_pending_tab_only_counts_the_current_reached_step_for_the_actor(): void
    {
        $this->insertPurchaseOrder([
            'po_no' => 'PO-READY',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'approve',
            'approved_by' => 'TESTER',
            'approved_by_status' => 'pending',
        ]);
        $this->insertPurchaseOrder([
            'po_no' => 'PO-BLOCKED',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'pending',
            'approved_by' => 'TESTER',
            'approved_by_status' => 'pending',
        ]);

        $this->postJson('/api/purchase_order_page', [
            'employee_code' => 'TESTER',
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => false],
            'filters' => ['tab' => 'pending', 'status' => '', 'company' => ''],
        ])->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.po_no', 'PO-READY');
    }

    public function test_action_tab_search_sort_pagination_and_company_options_share_server_results(): void
    {
        $approvedStepId = $this->insertPurchaseOrder([
            'po_no' => 'PO-APPROVED-STEP',
            'subject' => 'Target Alpha',
            'company' => 'Alpha Company',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'approve',
            'approved_by' => 'TESTER',
            'approved_by_status' => 'pending',
        ]);
        $verifiedStepId = $this->insertPurchaseOrder([
            'po_no' => 'PO-VERIFIED-STEP',
            'subject' => 'Target Charlie',
            'company' => 'Zulu Company',
            'verified_by' => 'TESTER',
            'verified_by_status' => 'pending',
        ]);
        $this->insertPurchaseOrder([
            'po_no' => 'PO-BLOCKED',
            'subject' => 'Target Hidden',
            'company' => 'Middle Company',
            'verified_by' => 'FIRST',
            'verified_by_status' => 'pending',
            'approved_by' => 'TESTER',
            'approved_by_status' => 'pending',
        ]);

        $request = [
            'employee_code' => 'TESTER',
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 0,
            'length' => 1,
            'search' => ['value' => 'Target', 'regex' => false],
            'filters' => ['tab' => 'action', 'status' => '', 'company' => ''],
        ];

        $firstPage = $this->postJson('/api/purchase_order_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $approvedStepId);
        $this->assertSame(
            ['Alpha Company', 'Middle Company', 'Zulu Company'],
            $firstPage->json('data.company_options')
        );

        $request['start'] = 1;
        $this->postJson('/api/purchase_order_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.id', $verifiedStepId);

        $request['start'] = 0;
        $request['length'] = 10;
        $request['search']['value'] = '';
        $request['order'][0]['column'] = 5;
        $this->postJson('/api/purchase_order_page', $request)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.id', $approvedStepId);
    }

    private function insertPurchaseOrder(array $overrides = []): int
    {
        return DB::table('purchase_orders')->insertGetId(array_merge([
            'po_no' => 'PO-DEFAULT',
            'status' => 'submitted',
            'requisition_date' => '2026-01-01',
            'to' => 'Recipient',
            'company' => 'Company',
            'from' => 'Sender',
            'subject' => 'Subject',
            'quotation_no' => 'QT-DEFAULT',
            'grand_total' => 0,
            'currency_code' => 'THB',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createPurchaseOrderTable(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_requisition_id')->nullable();
            foreach ([
                'po_no', 'status', 'po_date', 'requisition_date', 'to', 'company', 'from', 'cc',
                'subject', 'quotation_no', 'delivery_date', 'payment_term', 'currency_code', 'attachments',
                'purchase_request_by', 'purchase_request_by_date', 'purchase_request_by_status',
                'verified_by', 'verified_by_date', 'verified_by_status', 'approved_by', 'approved_by_date',
                'approved_by_status', 'circ', 'circ_date', 'circ_status', 'signed_by', 'signed_by_date',
                'signed_by_status', 'acknowledged_by', 'acknowledged_by_date', 'acknowledged_by_status',
                'comment_all', 'create_by', 'update_by',
            ] as $column) {
                $table->text($column)->nullable();
            }
            foreach (['sub_total', 'vat_value', 'discount', 'grand_total'] as $column) {
                $table->decimal($column, 15, 2)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AllowanceAfter10pm;
use App\Models\AllowanceAfter10pmItem;
use App\Models\ProjectDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AllowanceAfter10pmController extends Controller
{
    private function normalizeAttachments($attachments)
    {
        if (is_array($attachments)) {
            return $attachments;
        }

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $trimmed = trim($attachments);
            if ($trimmed !== '') {
                return [$trimmed];
            }
        }

        return [];
    }

    public function index()
    {
        return $this->getList();
    }

    public function create()
    {
        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', []);
    }

    public function edit($id)
    {
        return $this->show($id);
    }

    public function getList()
    {
        $items = AllowanceAfter10pm::with('items')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        if (!empty($items)) {
            for ($i = 0; $i < count($items); $i++) {
                $items[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPage(Request $request)
    {
        $length = (int) ($request->length ?? 10);
        if ($length <= 0) {
            $length = 10;
        }
        $start = (int) ($request->start ?? 0);
        $page = (int) floor($start / $length) + 1;
        $order = $request->order ?? [];
        $search = $request->search ?? ['value' => null];

        $col = [
            'id',
            'claimant_name',
            'discipline',
            'request_date',
            'total_baht',
            'attachments',
            'status',
            'tl_by',
            'tl_by_status',
            'tl_by_date',
            'di_by',
            'di_by_status',
            'di_by_date',
            'account_by',
            'account_by_status',
            'account_by_date',
            'notified_user',
            'notified_user_status',
            'notified_user_date',
            'create_by',
            'update_by',
            'created_at',
            'updated_at',
        ];

        $orderby = [
            '',
            'claimant_name',
            'discipline',
            'request_date',
            'total_baht',
            'status',
            'created_at',
            'updated_at',
        ];

        $query = AllowanceAfter10pm::with('items')->select($col);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tl_by_status')) {
            $query->where('tl_by_status', $request->tl_by_status);
        }
        if ($request->filled('di_by_status')) {
            $query->where('di_by_status', $request->di_by_status);
        }
        if ($request->filled('account_by_status')) {
            $query->where('account_by_status', $request->account_by_status);
        }

        if (!empty($search['value'])) {
            $query->where(function ($q) use ($search, $col) {
                foreach ($col as $c) {
                    $q->orWhere($c, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $orderColumn = $order[0]['column'] ?? null;
        $orderDir = $order[0]['dir'] ?? 'desc';
        if ($orderColumn !== null && ($orderby[$orderColumn] ?? false)) {
            $query->orderBy($orderby[$orderColumn], $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $data = $query->paginate($length, ['*'], 'page', $page);

        if ($data->isNotEmpty()) {
            $No = (($page - 1) * $length);
            for ($i = 0; $i < count($data); $i++) {
                $No = $No + 1;
                $data[$i]->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    public function show($id)
    {
        $item = AllowanceAfter10pm::with('items')->find($id);
        if (!$item) {
            return $this->returnErrorData('ไม่พบรายการที่ระบุ', 404);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $item);
    }

    public function attachmentDataUrl(Request $request)
    {
        $path = trim((string) $request->query('path', ''));
        if ($path === '') {
            return $this->returnErrorData('กรุณาระบุไฟล์แนบ', 404);
        }

        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        }

        $path = rawurldecode($path);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (strpos($path, '..') !== false) {
            return $this->returnErrorData('Invalid attachment path', 400);
        }

        $publicRoot = realpath(public_path());
        $realPath = realpath(public_path($path));

        if (!$publicRoot || !$realPath || strpos($realPath, $publicRoot) !== 0 || !File::exists($realPath)) {
            return $this->returnErrorData('ไม่พบไฟล์แนบ', 404);
        }

        $mimeType = File::mimeType($realPath) ?: 'application/octet-stream';
        $contents = File::get($realPath);

        return $this->returnSuccess('เรียกดูไฟล์แนบสำเร็จ', [
            'file_name' => basename($realPath),
            'mime_type' => $mimeType,
            'data_url' => 'data:' . $mimeType . ';base64,' . base64_encode($contents),
        ]);
    }

    public function store(Request $request)
    {
        $validation = $this->validateAllowanceRequest($request);
        if ($validation) {
            return $validation;
        }

        DB::beginTransaction();

        try {
            $actor = $this->getActorCode($request);
            $allowance = new AllowanceAfter10pm();
            $this->fillAllowance($allowance, $request, $actor, true);
            $allowance->save();

            $total = $this->replaceItems($allowance, $request->items ?? [], $actor);
            $allowance->total_baht = $total;
            $this->finalizeNotificationIfReady($allowance);
            $allowance->status = $this->resolveOverallStatus($allowance);
            $allowance->save();

            DB::commit();

            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $allowance->load('items'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validation = $this->validateAllowanceRequest($request, $id);
        if ($validation) {
            return $validation;
        }

        DB::beginTransaction();

        try {
            $allowance = AllowanceAfter10pm::with('items')->find($id);
            if (!$allowance) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการแก้ไข', 404);
            }

            $actor = $this->getActorCode($request);
            $this->fillAllowance($allowance, $request, $actor, false);
            $allowance->save();

            $total = $this->replaceItems($allowance, $request->items ?? [], $actor);
            $allowance->total_baht = $total;
            $this->finalizeNotificationIfReady($allowance);
            $allowance->status = $this->resolveOverallStatus($allowance);
            $allowance->save();

            DB::commit();

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $allowance->load('items'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $allowance = AllowanceAfter10pm::find($id);
            if (!$allowance) {
                return $this->returnErrorData('ไม่พบข้อมูลในระบบ', 404);
            }

            AllowanceAfter10pmItem::where('allowance_after_10pm_id', $allowance->id)->delete();
            $allowance->delete();

            $actor = $this->getActorCode($request);
            $this->Log($actor, 'ผู้ใช้งาน ' . $actor . ' ได้ทำการลบ Allowance After 10.00 PM #' . $allowance->id, 'ลบ Allowance After 10.00 PM');

            DB::commit();

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }

    private function validateAllowanceRequest(Request $request, $id = null)
    {
        if (empty($request->claimant_name)) {
            return $this->returnErrorData('กรุณาระบุ Name (claimant_name)', 404);
        }
        if (empty($request->discipline)) {
            return $this->returnErrorData('กรุณาระบุ Discipline (discipline)', 404);
        }
        if (!in_array($request->discipline, ['C&S', 'M&E', 'Admin', 'Site'], true)) {
            return $this->returnErrorData('Discipline ต้องเป็น C&S, M&E, Admin หรือ Site', 404);
        }
        if (empty($request->request_date)) {
            return $this->returnErrorData('กรุณาระบุ Date (request_date)', 404);
        }
        if (empty($request->tl_by)) {
            return $this->returnErrorData('กรุณาระบุ TL (tl_by)', 404);
        }
        if (empty($request->di_by)) {
            return $this->returnErrorData('กรุณาระบุ DI (di_by)', 404);
        }
        if (empty($request->account_by)) {
            return $this->returnErrorData('กรุณาระบุ Account (account_by)', 404);
        }

        $items = $request->items ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->returnErrorData('กรุณาระบุ items อย่างน้อย 1 รายการ', 404);
        }

        foreach ($items as $index => $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            $rowNo = $index + 1;
            if (empty($row['work_date'])) {
                return $this->returnErrorData("กรุณาระบุวันที่ในรายการที่ {$rowNo}", 404);
            }
            if (empty($row['project_detail_id'])) {
                return $this->returnErrorData("กรุณาระบุ Project ในรายการที่ {$rowNo}", 404);
            }
            if (!ProjectDetail::where('id', $row['project_detail_id'])->exists()) {
                return $this->returnErrorData("ไม่พบ Project ในรายการที่ {$rowNo}", 404);
            }
            if (empty($row['description'])) {
                return $this->returnErrorData("กรุณาระบุ Description of Work ในรายการที่ {$rowNo}", 404);
            }
            if (empty($row['time_from'])) {
                return $this->returnErrorData("กรุณาระบุเวลา From ในรายการที่ {$rowNo}", 404);
            }
            if (empty($row['time_to'])) {
                return $this->returnErrorData("กรุณาระบุเวลา To ในรายการที่ {$rowNo}", 404);
            }
            if (!isset($row['baht']) || !is_numeric($row['baht']) || (float) $row['baht'] <= 0) {
                return $this->returnErrorData("กรุณาระบุ Baht มากกว่า 0 ในรายการที่ {$rowNo}", 404);
            }
        }

        return null;
    }

    private function fillAllowance(AllowanceAfter10pm $allowance, Request $request, string $actor, bool $isCreate): void
    {
        $allowance->claimant_name = $request->claimant_name;
        $allowance->discipline = $request->discipline;
        $allowance->request_date = $request->request_date;
        $allowance->attachments = $this->normalizeAttachments($request->input('attachments', $allowance->attachments ?? []));

        $allowance->tl_by = $request->tl_by;
        $allowance->tl_by_status = $request->tl_by_status ?? ($allowance->tl_by_status ?? 'pending');
        $allowance->tl_by_date = $this->normalizeDateTimeInput($request->tl_by_date ?? $allowance->tl_by_date);

        $allowance->di_by = $request->di_by;
        $allowance->di_by_status = $request->di_by_status ?? ($allowance->di_by_status ?? 'pending');
        $allowance->di_by_date = $this->normalizeDateTimeInput($request->di_by_date ?? $allowance->di_by_date);

        $allowance->account_by = $request->account_by;
        $allowance->account_by_status = $request->account_by_status ?? ($allowance->account_by_status ?? 'pending');
        $allowance->account_by_date = $this->normalizeDateTimeInput($request->account_by_date ?? $allowance->account_by_date);

        $allowance->notified_user = $request->notified_user ?: ($allowance->notified_user ?: ($allowance->create_by ?: $actor));
        $allowance->notified_user_status = $request->notified_user_status ?? ($allowance->notified_user_status ?? 'pending');
        $allowance->notified_user_date = $this->normalizeDateTimeInput($request->notified_user_date ?? $allowance->notified_user_date);

        $allowance->status = $request->status ?? ($allowance->status ?? 'submitted');

        if ($isCreate) {
            $allowance->create_by = $actor;
        } else {
            $allowance->update_by = $actor;
        }
    }

    private function replaceItems(AllowanceAfter10pm $allowance, array $items, string $actor): float
    {
        AllowanceAfter10pmItem::where('allowance_after_10pm_id', $allowance->id)->delete();

        $total = 0.0;
        foreach ($items as $index => $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            $project = ProjectDetail::find($row['project_detail_id']);
            $amount = (float) $row['baht'];
            $total += $amount;

            $item = new AllowanceAfter10pmItem();
            $item->allowance_after_10pm_id = $allowance->id;
            $item->seq = $row['seq'] ?? ($index + 1);
            $item->work_date = $row['work_date'];
            $item->project_detail_id = $row['project_detail_id'];
            $item->project_code = $project->code ?? ($row['project_code'] ?? null);
            $item->project_name = $project->name ?? ($row['project_name'] ?? null);
            $item->description = $row['description'];
            $item->time_from = $row['time_from'];
            $item->time_to = $row['time_to'];
            $item->baht = $amount;
            $item->create_by = $actor;
            $item->save();
        }

        return $total;
    }

    private function finalizeNotificationIfReady(AllowanceAfter10pm $allowance): void
    {
        $tl = $this->normalizeWorkflowStatus($allowance->tl_by_status);
        $di = $this->normalizeWorkflowStatus($allowance->di_by_status);
        $account = $this->normalizeWorkflowStatus($allowance->account_by_status);

        if ($tl === 'approve' && $di === 'approve' && $account === 'approve') {
            $allowance->notified_user = $allowance->notified_user ?: $allowance->create_by;
            if ($this->normalizeWorkflowStatus($allowance->notified_user_status) !== 'notified') {
                $allowance->notified_user_status = 'notified';
            }
            if (!$allowance->notified_user_date) {
                $allowance->notified_user_date = now();
            }
        }
    }

    private function resolveOverallStatus(AllowanceAfter10pm $allowance): string
    {
        $tl = $this->normalizeWorkflowStatus($allowance->tl_by_status);
        $di = $this->normalizeWorkflowStatus($allowance->di_by_status);
        $account = $this->normalizeWorkflowStatus($allowance->account_by_status);

        if ($tl === 'reject' || $di === 'reject' || $account === 'reject') {
            return 'rejected';
        }
        if ($tl === 'approve' && $di === 'approve' && $account === 'approve') {
            return 'notified';
        }
        if ($tl === 'approve' && $di === 'approve') {
            return 'di_approved';
        }
        if ($tl === 'approve') {
            return 'tl_approved';
        }

        return 'submitted';
    }

    private function normalizeWorkflowStatus($status): string
    {
        $value = strtolower(trim((string) ($status ?? 'pending')));
        if ($value === 'approved') {
            return 'approve';
        }
        if ($value === 'rejected') {
            return 'reject';
        }
        if ($value === '') {
            return 'pending';
        }

        return $value;
    }

    private function getActorCode(Request $request): string
    {
        $loginBy = $request->login_by ?? null;
        if (is_object($loginBy)) {
            return $loginBy->employee_code ?? $loginBy->id ?? 'admin';
        }
        if (is_array($loginBy)) {
            return $loginBy['employee_code'] ?? $loginBy['id'] ?? 'admin';
        }
        return $request->login_id ?? 'admin';
    }
}

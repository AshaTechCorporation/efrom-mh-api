<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class JsonPayloadCrudController extends Controller
{
    protected string $modelClass;

    public function index(Request $request)
    {
        return $this->getList($request);
    }

    public function getList(Request $request)
    {
        try {
            $items = $this->applyFilters($this->newQuery(), $request)
                ->orderBy('id', 'desc')
                ->get()
                ->values()
                ->map(function ($item, $index) {
                    $row = $this->transformItem($item);
                    $row['No'] = $index + 1;

                    return $row;
                });

            return $this->returnSuccess('success', $items);
        } catch (\Throwable $e) {
            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    public function getPage(Request $request)
    {
        try {
            $draw = (int) ($request->draw ?? 1);
            $start = (int) ($request->start ?? 0);
            $length = (int) ($request->length ?? 10);

            $query = $this->applyFilters($this->newQuery(), $request, true);
            $recordsTotal = $this->newQuery()->count();
            $recordsFiltered = (clone $query)->count();

            $this->applyOrdering($query, $request);

            $items = $query->skip($start)
                ->take($length)
                ->get()
                ->values()
                ->map(function ($item, $index) use ($start) {
                    $row = $this->transformItem($item);
                    $row['No'] = $start + $index + 1;

                    return $row;
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'draw' => (int) ($request->draw ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = $this->newQuery()->where('id', $id)->first();

            if (! $item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            return $this->returnSuccess('success', $this->transformItem($item));
        } catch (\Throwable $e) {
            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $class = $this->modelClass;
            $item = new $class();

            $this->fillItem($item, $request, true);

            $item->save();

            DB::commit();

            return $this->returnSuccess('บันทึกข้อมูลสำเร็จ', $this->transformItem($item));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $item = $this->newQuery()->where('id', $id)->first();

            if (! $item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $this->fillItem($item, $request);

            $item->save();

            DB::commit();

            return $this->returnUpdateReturnData('อัปเดตข้อมูลสำเร็จ', $this->transformItem($item));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $item = $this->newQuery()->where('id', $id)->first();

            if (! $item) {
                return $this->returnErrorData('ไม่พบข้อมูล', 404);
            }

            $item->update_by = $this->resolveActorId($request);
            $item->save();
            $item->delete();

            DB::commit();

            return $this->returnSuccess('ลบข้อมูลสำเร็จ', []);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->returnErrorData($e->getMessage(), 500);
        }
    }

    protected function newQuery()
    {
        $class = $this->modelClass;

        return $class::query()->whereNull('deleted_at');
    }

    protected function applyFilters($query, Request $request, bool $dataTables = false)
    {
        $exactFilters = [
            'project_id' => 'project_id',
            'form_type' => 'form_type',
            'discipline' => 'discipline',
            'review_method' => 'review_method',
            'status' => 'status',
        ];

        foreach ($exactFilters as $requestKey => $column) {
            $value = $request->input($requestKey);
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        $likeFilters = [
            'project_name' => 'project_name',
            'project_number' => 'project_number',
            'prepared_by' => 'prepared_by',
        ];

        foreach ($likeFilters as $requestKey => $column) {
            $value = $request->input($requestKey);
            if ($value !== null && trim((string) $value) !== '') {
                $query->where($column, 'like', '%' . trim((string) $value) . '%');
            }
        }

        $searchValue = $dataTables ? $request->input('search.value') : $request->input('search');

        if ($searchValue !== null && trim((string) $searchValue) !== '') {
            $keyword = trim((string) $searchValue);

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('form_type', 'like', '%' . $keyword . '%')
                    ->orWhere('project_id', 'like', '%' . $keyword . '%')
                    ->orWhere('project_name', 'like', '%' . $keyword . '%')
                    ->orWhere('project_number', 'like', '%' . $keyword . '%')
                    ->orWhere('prepared_by', 'like', '%' . $keyword . '%')
                    ->orWhere('discipline', 'like', '%' . $keyword . '%')
                    ->orWhere('review_method', 'like', '%' . $keyword . '%')
                    ->orWhere('status', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    protected function applyOrdering($query, Request $request): void
    {
        $orderColumn = $request->input('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc'));
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';

        $columnsMap = [
            0 => 'id',
            1 => 'form_type',
            2 => 'project_name',
            3 => 'project_number',
            4 => 'prepared_by',
            5 => 'discipline',
            6 => 'review_method',
            7 => 'status',
            8 => 'created_at',
        ];

        if ($orderColumn !== null && isset($columnsMap[(int) $orderColumn])) {
            $query->orderBy($columnsMap[(int) $orderColumn], $orderDir);

            return;
        }

        $query->orderBy('id', 'desc');
    }

    protected function fillItem(Model $item, Request $request, bool $isNew = false): void
    {
        $payload = $request->except(['login_by', 'login_id']);
        unset($payload['_method']);

        $actorId = $this->resolveActorId($request);

        $item->form_type = $this->getPayloadValue($payload, ['formType', 'form_type']);
        $item->project_id = $this->getPayloadValue($payload, ['projectId', 'project_id']);
        $item->project_name = $this->getPayloadValue($payload, ['projectName', 'project_name']);
        $item->project_number = $this->getPayloadValue($payload, ['projectNumber', 'project_number']);
        $item->prepared_by = $this->getPayloadValue($payload, ['preparedBy', 'prepared_by']);
        $item->discipline = $this->getPayloadValue($payload, ['discipline']);
        $item->document_location = $this->getPayloadValue($payload, ['documentLocation', 'document_location']);
        $item->review_method = $this->getPayloadValue($payload, ['reviewMethod', 'review_method']);
        $item->status = $this->getPayloadValue($payload, ['status']) ?? ($item->status ?? 'submitted');
        $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($isNew) {
            $item->create_by = $actorId;
        }

        $item->update_by = $actorId;
    }

    protected function transformItem(Model $item): array
    {
        $payload = json_decode($item->payload ?? '[]', true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return array_merge($payload, [
            'id' => $item->id,
            'form_type' => $item->form_type,
            'project_id' => $item->project_id,
            'project_name' => $item->project_name,
            'project_number' => $item->project_number,
            'prepared_by' => $item->prepared_by,
            'discipline' => $item->discipline,
            'document_location' => $item->document_location,
            'review_method' => $item->review_method,
            'status' => $item->status,
            'create_by' => $item->create_by,
            'update_by' => $item->update_by,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ]);
    }

    protected function getPayloadValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null) {
                return null;
            }

            if (is_string($value) && trim($value) === '') {
                return null;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}

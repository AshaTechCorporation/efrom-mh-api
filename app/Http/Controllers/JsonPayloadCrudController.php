<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class JsonPayloadCrudController extends Controller
{
    protected string $modelClass;

    protected array $coreFieldMap = [
        'form_type' => ['formType', 'form_type'],
        'project_id' => ['projectId', 'project_id'],
        'project_name' => ['projectName', 'project_name'],
        'project_number' => ['projectNumber', 'project_number'],
        'prepared_by' => ['preparedBy', 'prepared_by'],
        'department' => ['department'],
        'discipline' => ['discipline'],
        'document_location' => ['documentLocation', 'document_location'],
        'review_method' => ['reviewMethod', 'review_method'],
        'status' => ['status'],
    ];

    protected array $exactFilterMap = [
        'project_id' => 'project_id',
        'form_type' => 'form_type',
        'department' => 'department',
        'discipline' => 'discipline',
        'review_method' => 'review_method',
        'status' => 'status',
    ];

    protected array $likeFilterMap = [
        'project_name' => 'project_name',
        'project_number' => 'project_number',
        'prepared_by' => 'prepared_by',
    ];

    protected array $searchableColumns = [
        'form_type',
        'project_id',
        'project_name',
        'project_number',
        'prepared_by',
        'department',
        'discipline',
        'review_method',
        'status',
    ];

    protected array $orderColumns = [
        0 => 'id',
        1 => 'form_type',
        2 => 'project_name',
        3 => 'project_number',
        4 => 'prepared_by',
        5 => 'department',
        6 => 'discipline',
        7 => 'review_method',
        8 => 'status',
        9 => 'created_at',
    ];

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
        foreach ($this->exactFilterMap as $requestKey => $column) {
            $value = $request->input($requestKey);
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        foreach ($this->likeFilterMap as $requestKey => $column) {
            $value = $request->input($requestKey);
            if ($value !== null && trim((string) $value) !== '') {
                $query->where($column, 'like', '%' . trim((string) $value) . '%');
            }
        }

        $searchValue = $dataTables ? $request->input('search.value') : $request->input('search');

        if ($searchValue !== null && trim((string) $searchValue) !== '') {
            $keyword = trim((string) $searchValue);

            $query->where(function ($subQuery) use ($keyword) {
                foreach ($this->searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $subQuery->where($column, 'like', '%' . $keyword . '%');
                    } else {
                        $subQuery->orWhere($column, 'like', '%' . $keyword . '%');
                    }
                }
            });
        }

        return $query;
    }

    protected function applyOrdering($query, Request $request): void
    {
        $orderColumn = $request->input('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc'));
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';

        if ($orderColumn !== null && isset($this->orderColumns[(int) $orderColumn])) {
            $query->orderBy($this->orderColumns[(int) $orderColumn], $orderDir);

            return;
        }

        $query->orderBy('id', 'desc');
    }

    protected function fillItem(Model $item, Request $request, bool $isNew = false): void
    {
        $payload = $request->except(['login_by', 'login_id']);
        unset($payload['_method']);

        $actorId = $this->resolveActorId($request);

        foreach ($this->coreFieldMap as $column => $keys) {
            $item->{$column} = $this->getPayloadValue($payload, $keys);
        }

        if (array_key_exists('status', $this->coreFieldMap)) {
            $item->status = $this->getPayloadValue($payload, $this->coreFieldMap['status']) ?? ($item->status ?? 'submitted');
        }

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

        $meta = [
            'id' => $item->id,
            'create_by' => $item->create_by,
            'update_by' => $item->update_by,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];

        foreach (array_keys($this->coreFieldMap) as $column) {
            $meta[$column] = $item->{$column};
        }

        return array_merge($payload, $meta);
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

            if (is_array($value)) {
                if ($value === []) {
                    return null;
                }

                $allScalar = true;
                foreach ($value as $entry) {
                    if (! is_scalar($entry) && $entry !== null) {
                        $allScalar = false;
                        break;
                    }
                }

                if ($allScalar) {
                    return implode(', ', array_map(function ($entry) {
                        return (string) $entry;
                    }, array_filter($value, function ($entry) {
                        return $entry !== null && $entry !== '';
                    })));
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return null;
    }
}

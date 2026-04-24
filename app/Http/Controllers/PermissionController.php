<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionController extends Controller
{
    private function sanitizeMenus($menus): array
    {
        if (!is_iterable($menus)) {
            return [];
        }

        $normalizedMenus = [];

        foreach ($menus as $menu) {
            $menuId = (int) data_get($menu, 'menu_id');
            if ($menuId <= 0) {
                continue;
            }

            $normalizedMenus[$menuId] = ['menu_id' => $menuId] + $this->normalizeMenuActions($menu);
        }

        return array_values($normalizedMenus);
    }

    private function persistMenuPermissions(int $permissionId, array $menus, string $actorId): void
    {
        MenuPermission::withTrashed()
            ->where('permission_id', $permissionId)
            ->forceDelete();

        if (empty($menus)) {
            return;
        }

        $rows = [];
        foreach ($menus as $menu) {
            $rows[] = $this->buildMenuPermissionRow($menu, $permissionId, $actorId);
        }

        DB::table('menu_permissions')->insert($rows);
    }

    private function hasScopedPermissionColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $columns = ['create', 'view_own', 'view_all', 'edit_own', 'edit_all', 'delete_own', 'delete_all'];
        foreach ($columns as $column) {
            if (!Schema::hasColumn('menu_permissions', $column)) {
                $cached = false;
                return $cached;
            }
        }

        $cached = true;
        return $cached;
    }

    private function normalizeMenuActions($menu): array
    {
        $create = (int) data_get($menu, 'create', data_get($menu, 'save', 0));
        $viewOwn = (int) data_get($menu, 'view_own', 0);
        $viewAll = (int) data_get($menu, 'view_all', data_get($menu, 'view', 0));
        $editOwn = (int) data_get($menu, 'edit_own', 0);
        $editAll = (int) data_get($menu, 'edit_all', data_get($menu, 'edit', 0));
        $deleteOwn = (int) data_get($menu, 'delete_own', 0);
        $deleteAll = (int) data_get($menu, 'delete_all', data_get($menu, 'delete', 0));

        $view = (int) data_get($menu, 'view', ($viewOwn || $viewAll ? 1 : 0));
        $edit = (int) data_get($menu, 'edit', ($editOwn || $editAll ? 1 : 0));
        $save = (int) data_get($menu, 'save', $create);
        $delete = (int) data_get($menu, 'delete', ($deleteOwn || $deleteAll ? 1 : 0));

        return [
            'create' => $create,
            'view_own' => $viewOwn,
            'view_all' => $viewAll,
            'edit_own' => $editOwn,
            'edit_all' => $editAll,
            'delete_own' => $deleteOwn,
            'delete_all' => $deleteAll,
            'view' => $view,
            'edit' => $edit,
            'save' => $save,
            'delete' => $delete,
        ];
    }

    private function buildMenuPermissionRow($menu, int $permissionId, string $actorId): array
    {
        $actions = $this->normalizeMenuActions($menu);

        $row = [
            'permission_id' => $permissionId,
            'menu_id' => (int) data_get($menu, 'menu_id'),
            'view' => $actions['view'],
            'edit' => $actions['edit'],
            'save' => $actions['save'],
            'delete' => $actions['delete'],
            'create_by' => $actorId,
            'update_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->hasScopedPermissionColumns()) {
            $row['create'] = $actions['create'];
            $row['view_own'] = $actions['view_own'];
            $row['view_all'] = $actions['view_all'];
            $row['edit_own'] = $actions['edit_own'];
            $row['edit_all'] = $actions['edit_all'];
            $row['delete_own'] = $actions['delete_own'];
            $row['delete_all'] = $actions['delete_all'];
        }

        return $row;
    }

    private function menuPermissionResponse($row): array
    {
        $create = (int) ($row->create ?? ($row->save ?? 0));
        $viewOwn = (int) ($row->view_own ?? 0);
        $viewAll = (int) ($row->view_all ?? ($row->view ?? 0));
        $editOwn = (int) ($row->edit_own ?? 0);
        $editAll = (int) ($row->edit_all ?? ($row->edit ?? 0));
        $deleteOwn = (int) ($row->delete_own ?? 0);
        $deleteAll = (int) ($row->delete_all ?? ($row->delete ?? 0));

        return [
            'create' => $create,
            'view_own' => $viewOwn,
            'view_all' => $viewAll,
            'edit_own' => $editOwn,
            'edit_all' => $editAll,
            'delete_own' => $deleteOwn,
            'delete_all' => $deleteAll,
            'view' => (int) ($row->view ?? ($viewOwn || $viewAll ? 1 : 0)),
            'edit' => (int) ($row->edit ?? ($editOwn || $editAll ? 1 : 0)),
            'save' => (int) ($row->save ?? $create),
            'delete' => (int) ($row->delete ?? ($deleteOwn || $deleteAll ? 1 : 0)),
        ];
    }

    public function getList()
    {
        $items = Permission::orderBy('id', 'desc')->get()->toArray();
        if (!empty($items)) {
            for ($i = 0; $i < count($items); $i++) {
                $items[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $col = ['id', 'name', 'create_by', 'update_by', 'created_at', 'updated_at'];
        $orderby = ['', 'name', 'created_at'];

        $d = Permission::select($col);

        if (($orderby[$order[0]['column']] ?? false)) {
            $d->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        } else {
            $d->orderBy('id', 'desc');
        }

        if (($search['value'] ?? '') !== '') {
            $value = $search['value'];
            $d->where(function ($query) use ($value, $col) {
                foreach ($col as $c) {
                    $query->orWhere($c, 'like', '%' . $value . '%');
                }
            });
        }

        $items = $d->paginate($length, ['*'], 'page', $page);
        if ($items->isNotEmpty()) {
            $no = (($page - 1) * $length);
            for ($i = 0; $i < count($items); $i++) {
                $no++;
                $items[$i]->No = $no;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPermissonUser(Request $request)
    {
        $permissionId = $request->input('permission_id');
        if (empty($permissionId)) {
            return $this->returnErrorData('[permission_id] Data Not Found', 404);
        }

        $items = User::where('permission_id', $permissionId)->get()->toArray();
        if (!empty($items)) {
            for ($i = 0; $i < count($items); $i++) {
                $items[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    public function getPermissonMenu(Request $request)
    {
        $permissionId = $request->input('permission_id');
        if (empty($permissionId)) {
            return $this->returnErrorData('[permission_id] Data Not Found', 404);
        }

        $menus = Menu::with('main_menu')->orderBy('id', 'asc')->get();

        $permissionRows = MenuPermission::where('permission_id', $permissionId)->get()->keyBy('menu_id');

        $result = [];
        foreach ($menus as $menu) {
            $row = $permissionRows->get($menu->id);
            $actions = $row ? $this->menuPermissionResponse($row) : $this->menuPermissionResponse((object) []);
            $result[] = [
                'menu_id' => (int) $menu->id,
                'menu' => $menu,
                'create' => $actions['create'],
                'view_own' => $actions['view_own'],
                'view_all' => $actions['view_all'],
                'edit_own' => $actions['edit_own'],
                'edit_all' => $actions['edit_all'],
                'delete_own' => $actions['delete_own'],
                'delete_all' => $actions['delete_all'],
                'view' => $actions['view'],
                'edit' => $actions['edit'],
                'save' => $actions['save'],
                'delete' => $actions['delete'],
            ];
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $result);
    }

    public function index()
    {
        return $this->getList();
    }

    public function store(Request $request)
    {
        if (!isset($request->name)) {
            return $this->returnErrorData('[name] Data Not Found', 404);
        }
        $actorId = $this->resolveActorId($request);

        // Support both "menus" (new) and "menu" (legacy client payload).
        $menus = $this->sanitizeMenus($request->input('menus', $request->input('menu', [])));

        DB::beginTransaction();
        try {
            $permission = new Permission();
            $permission->name = $request->name;
            $permission->create_by = $actorId;
            $permission->update_by = $actorId;
            $permission->save();

            if (!empty($menus)) {
                $this->persistMenuPermissions((int) $permission->id, $menus, $actorId);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('Something went wrong Please try again', 404);
        }

        return $this->returnSuccess('Successful operation', $permission);
    }

    public function show($id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->returnErrorData('Data Not Found', 404);
        }

        return $this->returnSuccess('Successful', $permission);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->returnErrorData('Data Not Found', 404);
        }

        $actorId = $this->resolveActorId($request);

        // Support both "menus" (new) and "menu" (legacy client payload).
        $menusInput = $request->input('menus', $request->input('menu'));
        $menus = is_null($menusInput) ? null : $this->sanitizeMenus($menusInput);

        // Allow updating either name, menus, or both.
        if (!isset($request->name) && !is_array($menus)) {
            return $this->returnErrorData('[name] or [menus]/[menu] Data Not Found', 404);
        }

        DB::beginTransaction();
        try {
            if (isset($request->name)) {
                $permission->name = $request->name;
            }
            $permission->update_by = $actorId;
            $permission->save();

            if (is_array($menus)) {
                $this->persistMenuPermissions((int) $permission->id, $menus, $actorId);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('Something went wrong Please try again', 404);
        }

        return $this->returnUpdateReturnData('ดำเนินการสำเร็จ', $permission);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $permission = Permission::find($id);
            if (!$permission) {
                return $this->returnErrorData('Data Not Found', 404);
            }

            MenuPermission::withTrashed()
                ->where('permission_id', (int) $permission->id)
                ->forceDelete();

            $permission->delete();
            DB::commit();

            return $this->returnUpdate('ดำเนินการสำเร็จ');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }
    }
}

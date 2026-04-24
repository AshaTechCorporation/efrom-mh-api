<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\Menu;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuPermissionController extends Controller
{
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

    public function getList()
    {
        $items = MenuPermission::with(['menu', 'permission'])->orderBy('id', 'desc')->get()->toArray();
        if (!empty($items)) {
            for ($i = 0; $i < count($items); $i++) {
                $items[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   

        if (!isset($request->permission_id)) {
            return $this->returnErrorData('[permission_id] Data Not Found', 404);
        } else if (!isset($request->menus) && !isset($request->name)) {
            // menus: [{menu_id, view, edit, save, delete}] (preferred)
            // name: backward-compat (array of menu_id or menu name)
            return $this->returnErrorData('[menus] Data Not Found', 404);
        }

        $permissionId = (int) $request->permission_id;
        $menus = $request->menus;
        $actorId = $this->resolveActorId($request);

        // Backward compatibility: allow "name" as a list of menu_id or menu name strings.
        if (!isset($menus) && isset($request->name)) {
            $menus = [];
            $input = $request->name;
            if (is_array($input)) {
                foreach ($input as $value) {
                    if (is_numeric($value)) {
                        $menus[] = ['menu_id' => (int) $value, 'view' => 1, 'view_all' => 1];
                        continue;
                    }

                    $menu = Menu::where('name', $value)->first();
                    if ($menu) {
                        $menus[] = ['menu_id' => (int) $menu->id, 'view' => 1, 'view_all' => 1];
                    }
                }
            }
        }

        if (!is_array($menus) || empty($menus)) {
            return $this->returnErrorData('[menus] Data Not Found', 404);
        }

        DB::beginTransaction();

        try {

            $Permission = Permission::find($permissionId);
            if (!$Permission) {
                return $this->returnErrorData('Data Not Found', 404);
            }

            // Replace menu permissions for this role.
            MenuPermission::where('permission_id', $permissionId)->delete();

            $rows = [];
            foreach ($menus as $menu) {
                $menuId = (int) data_get($menu, 'menu_id');
                if ($menuId <= 0) {
                    continue;
                }

                $rows[] = $this->buildMenuPermissionRow($menu, $permissionId, $actorId);
            }

            if (!empty($rows)) {
                DB::table('menu_permissions')->insert($rows);
            }

            //log
            $useId = $actorId;
            $log_type = 'Setting Menu Permission';
            $log_description = 'User ' . $useId . ' has ' . $log_type . ' ' . $Permission->name;
            $this->Log($useId, $log_description, $log_type);
            //

            DB::commit();

            return $this->returnSuccess('Successful operation', []);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('Something went wrong Please try again', 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\MenuPermission  $menuPermission
     * @return \Illuminate\Http\Response
     */
    public function show(MenuPermission $menuPermission)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MenuPermission  $menuPermission
     * @return \Illuminate\Http\Response
     */
    public function edit(MenuPermission $menuPermission)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MenuPermission  $menuPermission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MenuPermission $menuPermission)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MenuPermission  $menuPermission
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {

            $Item = MenuPermission::find($id);
            $Item->delete();

            //log
            $userId = "admin";
            $type = 'ลบผู้ใช้งาน';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type;
            $this->Log($userId, $description, $type);
            //

            DB::commit();

            return $this->returnUpdate('ดำเนินการสำเร็จ');
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }
    }

    public function checkAll(Request $request)
    {
        $permissionId = $request->input('permission_id');
        if (empty($permissionId)) {
            return $this->returnErrorData('[permission_id] Data Not Found', 404);
        }
        $actorId = $this->resolveActorId($request);

        DB::beginTransaction();

        try {

            if ($request->check == true) {
                $menus = Menu::get()->toArray();

                // Replace with full access (view=1 by default; others 0)
                MenuPermission::where('permission_id', $permissionId)->delete();

                $rows = [];
                for ($i = 0; $i < count($menus); $i++) {
                    $rows[] = $this->buildMenuPermissionRow([
                        'menu_id' => (int) $menus[$i]['id'],
                        'view' => 1,
                        'view_all' => 1,
                        'edit' => 0,
                        'edit_all' => 0,
                        'save' => 0,
                        'create' => 0,
                        'delete' => 0,
                        'delete_all' => 0,
                    ], (int) $permissionId, $actorId);
                }

                if (!empty($rows)) {
                    DB::table('menu_permissions')->insert($rows);
                }
            } else {
                MenuPermission::where('permission_id', $permissionId)->delete();
            }




            $log_type = 'แก้ไข การทำรายการข่าววัด';
            $log_description = 'ผู้ใช้งาน admin ได้ทำการ เพิ่มสิทธิเมนู';
            $this->Log($actorId, $log_description, $log_type);

            DB::commit();

            return $this->returnSuccess('Successful operation', []);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('Something went wrong Please try again', 404);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\Menu;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuPermissionController extends Controller
{
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

        $loginBy = $request->login_by;

        if (!isset($request->permission_id)) {
            return $this->returnErrorData('[permission_id] Data Not Found', 404);
        } else if (!isset($request->name)) {
            return $this->returnErrorData('[name] Data Not Found', 404);
        } else if (!isset($loginBy)) {
            return $this->returnErrorData('[login_by] Data Not Found', 404);
        }

        $name = $request->name;

        $Name = [];

        for ($i = 0; $i < count($name); $i++) {
            $Name[$i]['name'] = $name[$i];
        }

        DB::beginTransaction();

        try {

            $Permission = Permission::find($request->permission_id);

            if ($Permission->menus->isEmpty()) {
                //add one to many
                $Permission->menus()->createMany($Name);
            } else {

                //delete
                $Permission->menus()->where('permission_id', $request->permission_id)->delete();

                //add one to many
                $Permission->menus()->createMany($Name);
            }

            //log
            $useId = $loginBy->user_id;
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
        if (!isset($request->user_id)) {
            return $this->returnErrorData('[user_id] Data Not Found', 404);
        }

        DB::beginTransaction();

        try {

            if ($request->check == true) {
                $Menus = Menu::get()->toarray();

                for ($i = 0; $i < count($Menus); $i++) {
                    $Item = new MenuPermission();
                    $Item->user_id = $request->user_id;
                    $Item->menu_id = $Menus[$i]['id'];

                    $Item->actions = "View";

                    $Item->create_by = "Admin";

                    $Item->save();
                }
            } else {
                $Item = MenuPermission::where("user_id", $request->user_id)->get();

                for ($i = 0; $i < count($Item); $i++) {
                    $Item[$i]->delete();
                }
            }




            $log_type = 'แก้ไข การทำรายการข่าววัด';
            $log_description = 'ผู้ใช้งาน admin ได้ทำการ เพิ่มสิทธิเมนู';
            $this->Log("admin", $log_description, $log_type);

            DB::commit();

            return $this->returnSuccess('Successful operation', []);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('Something went wrong Please try again', 404);
        }
    }
}

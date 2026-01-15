<?php

namespace App\Http\Controllers;

use App\Models\MainMenu;
use App\Models\Menu;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{

    public function getList()
    {
        $Item = Menu::get()->toarray();

        return $this->returnSuccess('Successful', $Item);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Menu = Menu::with('permission')->get();

        if ($Menu->isNotEmpty()) {

            for ($i = 0; $i < count($Menu); $i++) {
                $Menu[$i]['No'] = $i + 1;
                $Menu[$i]['main_menu'] = MainMenu::find($Menu[$i]['main_menu_id']);
            }
        }

        return $this->returnSuccess('Successful', $Menu);
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

         if (!isset($request->name)) {
            return $this->returnErrorData('[name] Data Not Found', 404);
        }

        $name = $request->name;
        $main_menu_id = $request->main_menu_id;


        $Name = [];

        for ($i = 0; $i < count($name); $i++) {
            $Name[$i]['name'] = $name[$i];
            $Name[$i]['main_menu_id'] = $main_menu_id[$i];
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
     * @param  \App\Models\Menu  $menu
     * @return \Illuminate\Http\Response
     */
    public function show(Menu $menu)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Menu  $menu
     * @return \Illuminate\Http\Response
     */
    public function edit(Menu $menu)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Menu  $menu
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Menu $menu)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Menu  $menu
     * @return \Illuminate\Http\Response
     */
    public function destroy(Menu $menu)
    {
        //
    }
}

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
        $Item = Menu::orderBy('main_menu_id')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toArray();

        return $this->returnSuccess('Successful', $Item);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Menu = Menu::with('main_menu')
            ->orderBy('main_menu_id')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($Menu->isNotEmpty()) {

            for ($i = 0; $i < count($Menu); $i++) {
                $Menu[$i]['No'] = $i + 1;
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

        if (!isset($request->name)) {
            return $this->returnErrorData('[name] Data Not Found', 404);
        }
        if (!isset($request->main_menu_id)) {
            return $this->returnErrorData('[main_menu_id] Data Not Found', 404);
        }
        $actorId = $this->resolveActorId($request);

        $name = $request->name;
        $main_menu_id = $request->main_menu_id;
        $parent_id = $request->parent_id;
        $sort_order = $request->sort_order;

        DB::beginTransaction();

        try {

            $rows = [];
            for ($i = 0; $i < count($name); $i++) {
                $rows[] = [
                    'name' => $name[$i],
                    'main_menu_id' => $main_menu_id[$i] ?? null,
                    'parent_id' => is_array($parent_id) ? ($parent_id[$i] ?? null) : null,
                    'sort_order' => is_array($sort_order) ? ($sort_order[$i] ?? null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($rows)) {
                DB::table('menus')->insert($rows);
            }

            //log
            $useId = $actorId;
            $log_type = 'Create Menu';
            $log_description = 'User ' . $useId . ' has ' . $log_type;
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

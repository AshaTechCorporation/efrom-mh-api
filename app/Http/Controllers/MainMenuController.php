<?php

namespace App\Http\Controllers;

use App\Models\MainMenu;
use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainMenuController extends Controller
{

    public function getList()
    {
        $Item = MainMenu::get()->toarray();

        if (!empty($Item)) {

            for ($i = 0; $i < count($Item); $i++) {
                $Item[$i]['No'] = $i + 1;
                $Item[$i]['Menus'] = Menu::where('main_menu_id', $Item[$i]['id'])->get();
            }
        }

        return $this->returnSuccess('Successful', $Item);
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

        DB::beginTransaction();

        try {

            $Item = new MainMenu();
            $Item->name = $request->name;

            $Item->save();

            //log
            $userId = "admin";
            $type = 'Add Main Menu';
            $description = 'User ' . $userId . ' has ' . $type . ' ' . $request->name;
            $this->Log($userId, $description, $type);
            //

            DB::commit();

            return $this->returnSuccess('Successful operation', []);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('Something went wrong Please try again ', 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\MainMenu  $mainMenu
     * @return \Illuminate\Http\Response
     */
    public function show(MainMenu $mainMenu)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MainMenu  $mainMenu
     * @return \Illuminate\Http\Response
     */
    public function edit(MainMenu $mainMenu)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MainMenu  $mainMenu
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MainMenu $mainMenu)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MainMenu  $mainMenu
     * @return \Illuminate\Http\Response
     */
    public function destroy(MainMenu $mainMenu)
    {
        //
    }
}

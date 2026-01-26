<?php

namespace App\Http\Controllers;

use App\Models\MainMenu;
use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainMenuController extends Controller
{
    private function buildMenuTree(array $menus, $parentId = null): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            $menuParentId = $menu['parent_id'] ?? null;
            if ((string) $menuParentId !== (string) $parentId) {
                continue;
            }

            $node = $menu;
            $node['children'] = $this->buildMenuTree($menus, $menu['id']);
            $tree[] = $node;
        }

        return $tree;
    }

    public function getList()
    {
        $Item = MainMenu::get()->toarray();

        if (!empty($Item)) {

            for ($i = 0; $i < count($Item); $i++) {
                $Item[$i]['No'] = $i + 1;
                $menus = Menu::where('main_menu_id', $Item[$i]['id'])
                    ->orderBy('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->toArray();
                // Keep backward compatible field (flat list)
                $Item[$i]['Menus'] = $menus;
                // New: nested tree for 2+ levels
                $Item[$i]['MenusTree'] = $this->buildMenuTree($menus, null);
            }
        }

        return $this->returnSuccess('Successful', $Item);
    }

    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $col = ['id', 'name', 'created_at', 'updated_at'];
        $orderby = ['', 'name', 'created_at'];

        $d = MainMenu::select($col);

        if (($orderby[$order[0]['column']] ?? false)) {
            $d->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        } else {
            $d->orderBy('id', 'desc');
        }

        if (($search['value'] ?? '') !== '' && ($search['value'] ?? null) !== null) {
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
                $menus = Menu::where('main_menu_id', $items[$i]->id)
                    ->orderBy('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->toArray();
                $items[$i]->Menus = $menus;
                $items[$i]->MenusTree = $this->buildMenuTree($menus, null);
            }
        }

        return $this->returnSuccess('Successful', $items);
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
        if (!isset($request->name)) {
            return $this->returnErrorData('[name] Data Not Found', 404);
        }
        $actorId = $this->resolveActorId($request);

        DB::beginTransaction();

        try {

            $Item = new MainMenu();
            $Item->name = $request->name;

            $Item->save();

            //log
            $userId = $actorId;
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

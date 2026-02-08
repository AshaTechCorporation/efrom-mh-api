<?php
namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    // =========== getPage (DataTables style) ===========
    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length  = $request->length ?? 10;
        $order   = $request->order;
        $search  = $request->search;
        $start   = $request->start ?? 0;
        $page    = $start / $length + 1;

        $col = array(
            'id',
            'code',
            'name',
            'detail',
            'is_active',
            'created_at',
            'updated_at',
        );

        $orderby = array(
            '',
            'code',
            'name',
            'is_active',
            'created_at',
        );

        $D = Discipline::select($col);

        // order by
        if (!empty($order) && ($orderby[$order[0]['column']] ?? false)) {
            $D->orderBy($orderby[$order[0]['column']], $order[0]['dir']);
        }

        // search all columns
        if (!empty($search['value'])) {
            $D->where(function ($query) use ($search, $col) {
                foreach ($col as $c) {
                    $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $d = $D->paginate($length, ['*'], 'page', $page);

        if ($d->isNotEmpty()) {
            $No = (($page - 1) * $length);
            for ($i = 0; $i < count($d); $i++) {
                $No        = $No + 1;
                $d[$i]->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    }

    // create a new discipline
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:disciplines,code',
            'name' => 'required|string',
        ]);

        $data = Discipline::create([
            'code'      => $request->code,
            'name'      => $request->name,
            'detail'    => $request->detail,
            'is_active' => 1,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'created successfully',
            'data'    => $data,
        ]);
    }

    // get a discipline by id
    public function show($id)
    {
        $data = Discipline::findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }

    // upate a discipline by id
    public function update($id, Request $request)
    {
        $data = Discipline::findOrFail($id);

        $request->validate([
            'code' => 'required|unique:disciplines,code,' . $id,
            'name' => 'required|string',
        ]);

        $data->update($request->only([
            'code',
            'name',
            'detail',
            'is_active',
        ]));

        return response()->json([
            'status'  => true,
            'message' => 'updated successfully',
            'data'    => $data,
        ]);
    }

    // delete a discipline by id
    public function destroy($id)
    {
        $data = Discipline::findOrFail($id);
        $data->update(['is_active' => 0]);

        return response()->json([
            'status'  => true,
            'message' => 'deleted successfully',
            'data'    => null,
        ]);
    }

    // get all discipline
    public function getAll()
    {
        $data = Discipline::where('is_active', 1)->get();

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }
}

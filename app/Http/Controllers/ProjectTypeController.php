<?php
namespace App\Http\Controllers;

use App\Models\ProjectType;
use Illuminate\Http\Request;

class ProjectTypeController extends Controller
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

        $D = ProjectType::select($col);

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

    // POST /project_type
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:project_types,code',
            'name' => 'required|string',
        ]);

        $data = ProjectType::create([
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

    // GET /project_type/{id}
    public function show($id)
    {
        $data = ProjectType::findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }

    // PUT /project_type/{id}
    public function update(Request $request, $id)
    {
        $data = ProjectType::findOrFail($id);

        $request->validate([
            'code' => 'required|unique:project_types,code,' . $id,
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

    // DELETE /project_type/{id} (soft delete)
    public function destroy($id)
    {
        $data = ProjectType::findOrFail($id);
        $data->update(['is_active' => 0]);

        return response()->json([
            'status'  => true,
            'message' => 'deleted successfully',
            'data'    => null,
        ]);
    }

    // GET /get_project_type (dropdown)
    public function getAll()
    {
        $data = ProjectType::where('is_active', 1)->get();

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }
}

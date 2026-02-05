<?php
namespace App\Http\Controllers;

use App\Models\ProjectType;
use Illuminate\Http\Request;

class ProjectTypeController extends Controller
{
    public function page(Request $request)
    {
        $columns = ['id', 'code', 'name', 'is_active', 'created_at'];

        $length = $request->length ?? 10;
        $start  = $request->start ?? 0;
        $page   = ($start / $length) + 1;

        $search = $request->search['value'] ?? null;
        $order  = $request->order[0] ?? null;

        $query = ProjectType::query();

        
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

       
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

       
        if ($order && isset($columns[$order['column']])) {
            $query->orderBy(
                $columns[$order['column']],
                $order['dir']
            );
        } else {
            $query->orderBy('id', 'desc');
        }

        $data = $query->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw'            => intval($request->draw),
            'recordsTotal'    => ProjectType::count(),
            'recordsFiltered' => $data->total(),
            'data'            => $data->items(),
        ]);
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

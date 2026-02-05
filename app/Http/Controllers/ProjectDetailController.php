<?php
namespace App\Http\Controllers;

use App\Models\ProjectDetail;
use Illuminate\Http\Request;

class ProjectDetailController extends Controller
{
    
    public function page(Request $request)
    {
        $columns = ['id', 'code', 'name', 'is_active', 'created_at'];

        $length = $request->length ?? 10;
        $start  = $request->start ?? 0;
        $page   = ($start / $length) + 1;

        $search = $request->search['value'] ?? null;
        $order  = $request->order[0] ?? null;

        $query = ProjectDetail::query();

        
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
            'recordsTotal'    => ProjectDetail::count(),
            'recordsFiltered' => $data->total(),
            'data'            => $data->items(),
        ]);
    }

    // create a new project detail
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:project_details,code',
            'name' => 'required|string',
        ]);

        $data = ProjectDetail::create([
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

    // get a project detail by id
    public function show($id)
    {
        $data = ProjectDetail::findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }

    // upate a project detail by id
    public function update($id, Request $request)
    {
        $data = ProjectDetail::findOrFail($id);

        $request->validate([
            'code' => 'required|unique:project_details,code,' . $id,
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

    // delete a project detail by id
    public function destroy($id)
    {
        $data = ProjectDetail::findOrFail($id);
        $data->update(['is_active' => 0]);

        return response()->json([
            'status'  => true,
            'message' => 'deleted successfully',
            'data'    => null,
        ]);
    }

    // get all project detail
    public function getAll()
    {
        $data = ProjectDetail::where('is_active', 1)->get();

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
        ]);
    }

}

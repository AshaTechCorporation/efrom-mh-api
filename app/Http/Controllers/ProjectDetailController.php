<?php
namespace App\Http\Controllers;

use App\Models\ProjectDetail;
use Illuminate\Http\Request;

class ProjectDetailController extends Controller
{
    // retrieve data with pagination, filtering and searching
    public function page(Request $request)
    {
        $query = ProjectDetail::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $data = $query->paginate(10);

        return response()->json([
            'status'  => true,
            'message' => 'success',
            'data'    => $data,
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

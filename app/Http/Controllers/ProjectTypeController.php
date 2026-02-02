<?php

namespace App\Http\Controllers;
use App\Models\ProjectType;
use Illuminate\Http\Request;

class ProjectTypeController extends Controller
{
    // GET /project_type_page
    public function page(Request $request)
    {
        $query = ProjectType::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $data = $query->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'success',
            'data' => $data
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
            'code' => $request->code,
            'name' => $request->name,
            'detail' => $request->detail,
            'is_active' => 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'created successfully',
            'data' => $data
        ]);
    }

    // GET /project_type/{id}
    public function show($id)
    {
        $data = ProjectType::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'success',
            'data' => $data
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
            'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'updated successfully',
            'data' => $data
        ]);
    }

    // DELETE /project_type/{id} (soft delete)
    public function destroy($id)
    {
        $data = ProjectType::findOrFail($id);
        $data->update(['is_active' => 0]);

        return response()->json([
            'status' => true,
            'message' => 'deleted successfully',
            'data' => null
        ]);
    }

    // GET /get_project_type (dropdown)
    public function getAll()
    {
        $data = ProjectType::where('is_active', 1)->get();

        return response()->json([
            'status' => true,
            'message' => 'success',
            'data' => $data
        ]);
    }
}
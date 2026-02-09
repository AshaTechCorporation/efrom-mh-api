<?php
namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{

    public function page(Request $request)
    {
        $columns = ['id', 'code', 'name', 'is_active', 'created_at'];

        $length = $request->length ?? 10;
        $start  = $request->start ?? 0;
        $page   = ($start / $length) + 1;

        $search = $request->search['value'] ?? null;
        $order  = $request->order[0] ?? null;

        $query = Discipline::query();

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
            'recordsTotal'    => Discipline::count(),
            'recordsFiltered' => $data->total(),
            'data'            => $data->items(),
        ]);
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
        try {
            $discipline = Discipline::find($id);

            if (! $discipline) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Discipline not found',
                    'data'    => null,
                ], 404);
            }

            // if ($discipline->project()->where('is_active', 1)->exists()) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'Cannot delete. Discipline is linked to active projects.',
            //         'data'    => null,
            //     ], 409);
            // }

            $discipline->update(['is_active' => 0]);

            return response()->json([
                'status'  => true,
                'message' => 'Deleted successfully',
                'data'    => null,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Internal Server Error',
                'error'   => $e->getMessage(),
            ], 500);
        }
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

<?php

namespace App\Http\Controllers;

use App\Exports\LogExport;
use App\Models\Log;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LogController extends Controller
{
    // public function LogPage(Request $request)
    // {

    //     $columns = $request->columns;
    //     $length = $request->length;
    //     $order = $request->order;
    //     $search = $request->search;
    //     $start = $request->start;
    //     $page = $start / $length + 1;

    //     $type = $request->type;

    //     $col = ['id', 'user_id', 'description', 'type', 'created_at', 'updated_at'];

    //     $orderby = array('', 'user_id', 'description', 'type', 'created_at');

    //     $d = Log::select($col)
    //         ->with('user');

    //     if ($type) {
    //         $d->where('type', $type);
    //     }

    //     if ($orderby[$order[0]['column']]) {
    //         $d->orderby($orderby[$order[0]['column']], $order[0]['dir']);
    //     }

    //     if ($search['value'] != '' && $search['value'] != null) {

    //         //search datatable
    //         $d->where(function ($query) use ($search, $col) {
    //             foreach ($col as &$c) {
    //                 $query->orWhere($c, 'like', '%' . $search['value'] . '%');
    //             }

    //         });
    //     }

    //     $d = $d->paginate($length, ['*'], 'page', $page);

    //     if ($d->isNotEmpty()) {

    //         //run no
    //         $No = (($page - 1) * $length);

    //         for ($i = 0; $i < count($d); $i++) {

    //             $No = $No + 1;
    //             $d[$i]->No = $No;

    //         }

    //     }

    //     return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
    // }

    // public function getLogType()
    // {

    //     $type = Log::select('type')->groupby('type')->get();
    //     return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $type);
    // }

    // public function ExportLog(Request $request)
    // {

    //     $type = $request->type;

    //     $log = Log::select('id', 'user_id', 'description', 'type', 'created_at', 'updated_at');
    //     if ($type) {
    //         $log->where('type', $type);
    //     }

    //     $data = $log->get()->toArray();

    //     if (!empty($data)) {

    //         for ($i = 0; $i < count($data); $i++) {

    //             $export_data[] = array(
    //                 'user_id' => trim($data[$i]['user_id']),
    //                 'description' => trim($data[$i]['description']),
    //                 'type' => trim($data[$i]['type']),
    //                 'created_at' => date('Y-m-d H:i:s', strtotime($data[$i]['created_at'])),
    //                 'updated_at' => date('Y-m-d H:i:s', strtotime($data[$i]['updated_at'])),
    //             );
    //         }

    //         $result = new LogExport($export_data);
    //         return Excel::download($result, 'ประวัติการใช้งาน.xlsx');

    //     } else {

    //         $export_data[] = array(
    //             'sub_agency_command_id' => null,
    //             'affiliation_id' => null,
    //             'name' => null,
    //             'sub_name' => null,
    //             'address' => null,
    //             'create_by' => null,
    //             'update_by' => null,
    //             'created_at' => null,
    //             'updated_at' => null,
    //         );

    //         $result = new LogExport($export_data);
    //         return Excel::download($result, 'ประวัติการใช้งาน.xlsx');
    //     }

    // }

}

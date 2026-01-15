<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{

    public function getList()
    {
        $Item = User::get()->toarray();

        if (!empty($Item)) {

            for ($i = 0; $i < count($Item); $i++) {
                $User[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    public function getListByPermission($id)
    {
        $Item = User::where('permission_id',$id)->get()->toarray();

        if (!empty($Item)) {

            for ($i = 0; $i < count($Item); $i++) {
                $User[$i]['No'] = $i + 1;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    public function getPage(Request $request)
    {
        $columns = $request->columns;
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $Status = $request->status;

        $col = array('id', 'permission_id', 'username','code', 'name', 'email', 'phone', 'image', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $orderby = array('', 'permission_id', 'image', 'code','name', 'email', 'phone', 'username', 'create_by', 'status');

        $D = User::select($col);

        if (isset($Status)) {
            $D->where('status', $Status);
        }

        if ($orderby[$order[0]['column']]) {
            $D->orderby($orderby[$order[0]['column']], $order[0]['dir']);
        }else{
            $D->orderby('id', 'DESC');
        }

        if ($search['value'] != '' && $search['value'] != null) {

            $D->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orWhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });

                //search with
                // $query = $this->withPermission($query, $search);
            });
        }

        $d = $D->paginate($length, ['*'], 'page', $page);

        if ($d->isNotEmpty()) {

            //run no
            $No = (($page - 1) * $length);

            for ($i = 0; $i < count($d); $i++) {

                $No = $No + 1;
                $d[$i]->No = $No;
                $d[$i]->permission = Permission::find($d[$i]->permission_id);
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $d);
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
        $loginBy = $request->login_by;

        if (!isset($request->username)) {
            return $this->returnErrorData('กรุณาระบุชื่อบัญชีผู้ใช้งานให้เรียบร้อย', 404);
        } else if (!isset($request->name)) {
            return $this->returnErrorData('กรุณาระบุชื่อผู้ใช้งานให้เรียบร้อย', 404);
        } else if (!isset($request->email)) {
            return $this->returnErrorData('กรุณาระบุอีเมล์ให้เรียบร้อย', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('กรุณาระบุชื่อรหัสผ่านให้เรียบร้อย', 404);
        } else
            //

            if (strlen($request->password) < 6) {
                return $this->returnErrorData('กรุณาระบุรหัสผ่านอย่างน้อย 6 หลัก', 404);
            }

        $checkUserId = User::where('username', $request->username)->first();
        if ($checkUserId) {
            return $this->returnErrorData('มีชื่อบัญชีผู้ใช้งาน ' . $request->username . ' ในระบบแล้ว', 404);
        }

        $checkEmail = User::where('email', $request->email)->first();
        if ($checkEmail) {
            return $this->returnErrorData('มีอีเมล์ ' . $request->email . ' ในระบบแล้ว', 404);
        }

        DB::beginTransaction();

        try {
            $Item = new User();
            $Item->permission_id = $request->permission_id;
            $Item->code = $request->code;
            $Item->username = $request->username;
            $Item->password = md5($request->password);
            $Item->name = $request->name;
            $Item->email = $request->email;
            $Item->phone = $request->phone;


            $Item->save();
            //

            //log
            $userId = "admin";
            $type = 'เพิ่มผู้ใช้งาน';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $request->username;
            $this->Log($userId, $description, $type);
            //

            DB::commit();

            return $this->returnSuccess('ดำเนินการสำเร็จ', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง '.$e, 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $Item = User::with('permission')
            ->where('id', $id)
            ->first();

        if ($Item->image) {
            $Item->image = url($Item->image);
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $loginBy = $request->login_by;

        if (!isset($request->username)) {
            return $this->returnErrorData('กรุณาระบุชื่อบัญชีผู้ใช้งานให้เรียบร้อย', 404);
        } else if (!isset($request->name)) {
            return $this->returnErrorData('กรุณาระบุชื่อผู้ใช้งานให้เรียบร้อย', 404);
        } else if (!isset($request->email)) {
            return $this->returnErrorData('กรุณาระบุอีเมล์ให้เรียบร้อย', 404);
        }

        $checkUser = User::where('username', $request->username)
            ->where('id', '!=', $id)
            ->first();
        if ($checkUser) {
            return $this->returnErrorData('มีชื่อบัญชีผู้ใช้งาน ' . $request->username . ' ในระบบแล้ว', 404);
        }

        $checkEmail = User::where('email', $request->email)
            ->where('id', '!=', $id)
            ->first();
        if ($checkEmail) {
            return $this->returnErrorData('มีอีเมล์ ' . $request->email . ' ในระบบแล้ว', 404);
        }

        DB::beginTransaction();

        try {
            $Item = User::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบผู้ใช้งานที่ต้องการแก้ไข', 404);
            }

            $Item->permission_id = $request->permission_id;
            $Item->code = $request->code;
            $Item->username = $request->username;
            $Item->name = $request->name;
            $Item->email = $request->email;
            $Item->phone = $request->phone;
            // อัปเดตรหัสผ่านหากส่งมา
            if (!empty($request->password)) {
                if (strlen($request->password) < 6) {
                    return $this->returnErrorData('กรุณาระบุรหัสผ่านอย่างน้อย 6 หลัก', 404);
                }
                $Item->password = md5($request->password);
            }

            // อัปโหลดรูปใหม่ถ้ามี
            if ($request->image && $request->image != null && $request->image != 'null') {
                $Item->image = $request->image;
            }

            $Item->save();

            // Log
            $userId = "admin";
            $type = 'แก้ไขผู้ใช้งาน';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการแก้ไข ' . $request->username;
            $this->Log($userId, $description, $type);

            DB::commit();

            return $this->returnSuccess('แก้ไขข้อมูลสำเร็จ', $Item);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 500);
        }
    }


    public function getProfileUser(Request $request)
    {

        $Item = User::where('id', $request->login_id)
            ->first();

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $Item);
    }

    public function updateProfileUser(Request $request)
    {
        $loginBy = $request->login_by;

        if (!isset($loginBy)) {
            return $this->returnErrorData('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง', 404);
        }

        $check = Permission::find($request->permission_id)->first();
        if ($check) {
            return $this->returnErrorData('ไม่พบสิทธิ์นี้ในระบบอยู่แล้ว', 404);
        }

        DB::beginTransaction();

        try {

            $Item = User::find($loginBy->id);

            $Item->name = $request->name;
            $Item->email = $request->email;
            $Item->phone = $request->phone;
            $Item->permission_id = $request->permission_id;

            $Item->update_by = $loginBy->username;
            $Item->updated_at = Carbon::now()->toDateTimeString();

            $Item->save();

            DB::commit();

            return $this->returnUpdate('ดำเนินการสำเร็จ');
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        // $loginBy = $request->login_by;

        // if (!isset($loginBy)) {
        //     return $this->returnErrorData('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง', 404);
        // }

        DB::beginTransaction();

        try {

            $Item = User::find($id);

            $Item->username = $Item->username . '_del_' . date('YmdHis');
            $Item->save();

            //log
            $userId = $Item->username;
            $type = 'ลบผู้ใช้งาน';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' ' . $Item->username;
            $this->Log($userId, $description, $type);
            //

            $Item->delete();

            DB::commit();

            return $this->returnUpdate('ดำเนินการสำเร็จ');
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }
    }

    public function createUserAdmin(Request $request)
    {
        if (!isset($request->username)) {
            return $this->returnErrorData('[username] ไม่มีข้อมูล', 404);
        } else if (!isset($request->name)) {
            return $this->returnErrorData('[fname] ไม่มีข้อมูล', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('[password] ไม่มีข้อมูล', 404);
        }

        $checkName = User::where(function ($query) use ($request) {
            $query->orwhere('email', $request->email)
                ->orWhere('username', $request->username);
        })
            ->first();

        if ($checkName) {
            return $this->returnErrorData('มีผู้ใช้งานนี้ในระบบแล้ว', 404);
        } else {

            DB::beginTransaction();

            try {

                //
                $Item = new User();
                $Item->username = $request->username;
                $Item->password = md5($request->password);
                $Item->name = $request->name;
                $Item->email = $request->email;
                $Item->phone = $request->phone;
                $Item->permission_id = $request->permission_id;

                $Item->status = "Yes";
                $Item->create_by = "admin";


                $Item->save();

                //log
                $userId = "admin";
                $type = 'เพิ่ม admin';
                $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' ' . $request->username;
                $this->Log($userId, $description, $type);
                //

                DB::commit();

                return $this->returnSuccess('ดำเนินการสำเร็จ', []);
            } catch (\Throwable $e) {

                DB::rollback();

                return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง '.$e, 404);
            }
        }
    }

    public function ResetPasswordUser(Request $request, $id)
    {
        $loginBy = $request->login_by;

        if (!isset($id)) {
            return $this->returnErrorData('ไม่พบข้อมูล id', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('กรุณาระบุรหัสผ่านให้เรียบร้อย', 404);
        } else if (!isset($request->new_password)) {
            return $this->returnErrorData('กรุณาระบุรหัสผ่านใหม่ให้เรียบร้อย', 404);
        } else if (!isset($request->confirm_new_password)) {
            return $this->returnErrorData('กรุณาระบุรหัสผ่านใหม่อีกครั้ง', 404);
        } else if (!isset($loginBy)) {
            return $this->returnErrorData('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง', 404);
        }

        if (strlen($request->new_password) < 6) {
            return $this->returnErrorData('กรุณาระบุรหัสผ่านอย่างน้อย 6 หลัก', 404);
        }

        if ($request->new_password != $request->confirm_new_password) {
            return $this->returnErrorData('รหัสผ่านไม่ตรงกัน', 404);
        }

        DB::beginTransaction();

        try {

            $Item = User::find($id);

            if ($Item->password == md5($request->password)) {

                $Item->password = md5($request->new_password);
                $Item->updated_at = Carbon::now()->toDateTimeString();
                $Item->save();

                DB::commit();

                return $this->returnUpdate('ดำเนินการสำเร็จ');
            } else {

                return $this->returnErrorData('รหัสผ่านไม่ถูกต้อง', 404);
            }
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }
    }

    public function ForgotPasswordUser(Request $request)
    {

        $email = $request->email;

        $Item = User::where('email', $email)->where('status', 'Yes')->first();

        if (!empty($Item)) {

            //random string
            $length = 8;
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            //

            $newPasword = md5($randomString);

            DB::beginTransaction();

            try {

                $Item->password = $newPasword;
                $Item->save();

                $title = 'รหัสผ่านใหม่';
                $text = 'รหัสผ่านใหม่ของคุณคือ  ' . $randomString;
                $type = 'Forgot Password';

                // //send line
                // if ($Item->line_token) {
                //     $this->sendLine($Item->line_token, $text);
                // }

                //send email
                if ($Item->email) {
                    $this->sendMail($Item->email, $text, $title, $type);
                }

                DB::commit();

                return $this->returnUpdate('ดำเนินการสำเร็จ');
            } catch (\Throwable $e) {

                DB::rollback();

                return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
            }
        } else {
            return $this->returnErrorData('ไม่พบอีเมล์ในระบบ ', 404);
        }
    }
}

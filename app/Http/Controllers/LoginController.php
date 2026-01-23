<?php

namespace App\Http\Controllers;

use App\Models\MenuPermission;
use App\Models\User;
use App\Models\Member;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Firebase\JWT\JWT;

class LoginController extends Controller
{
    public $key = "key";

    public function genToken($id, $name)
    {
        $payload = array(
            "iss" => "key",
            "aud" => $id,
            "lun" => $name,
            "iat" => Carbon::now()->timestamp,
            // "exp" => Carbon::now()->timestamp + 86400,
            "exp" => Carbon::now()->timestamp + 31556926,
            "nbf" => Carbon::now()->timestamp,
        );

        $token = JWT::encode($payload, $this->key);
        return $token;
    }

    public function checkLogin(Request $request)
    {
        $header = $request->header('Authorization');
        $token = str_replace('Bearer ', '', $header);

        try {

            if ($token == "") {
                return $this->returnError('Token Not Found', 401);
            }

            $payload = JWT::decode($token, $this->key, array('HS256'));
            $payload->exp = Carbon::now()->timestamp + 86400;
            $token = JWT::encode($payload, $this->key);

            return response()->json([
                'code' => '200',
                'status' => true,
                'message' => 'Active',
                'data' => [],
                'token' => $token,
            ], 200);
        } catch (\Firebase\JWT\ExpiredException $e) {

            list($header, $payload, $signature) = explode(".", $token);
            $payload = json_decode(base64_decode($payload));
            $payload->exp = Carbon::now()->timestamp + 86400;
            $token = JWT::encode($payload, $this->key);

            return response()->json([
                'code' => '200',
                'status' => true,
                'message' => 'Token is expire',
                'data' => [],
                'token' => $token,
            ], 200);

        } catch (Exception $e) {
            return $this->returnError('Can not verify identity', 401);
        }
    }

    public function login(Request $request)
    {
        if (!isset($request->username)) {
            return $this->returnErrorData('[username] ไม่มีข้อมูล', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('[password] ไม่มีข้อมูล', 404);
        }

        $user = User::where('username', $request->username)
            ->where('password', md5($request->password))
            ->first();

        if ($user) {

            $user->menus = MenuPermission::where('permission_id',$user->permission_id)->get();
            foreach ($user->menus as $key => $value) {
                $user->menus[$key]->menu_id = intval($user->menus[$key]->menu_id);
                $user->menus[$key]->view = intval($user->menus[$key]->view);
                $user->menus[$key]->edit = intval($user->menus[$key]->edit);
                $user->menus[$key]->save = intval($user->menus[$key]->save);
                $user->menus[$key]->delete = intval($user->menus[$key]->delete);
            }

            //log
            $username = $user->username;
            $log_type = 'เข้าสู่ระบบ';
            $log_description = 'ผู้ใช้งาน ' . $username . ' ได้ทำการ ' . $log_type;
            $this->Log($username, $log_description, $log_type);
            //

            return response()->json([
                'code' => '200',
                'status' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'data' => $user,
                'token' => $this->genToken($user->id, $user),
            ], 200);
        } else {
            return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง', 401);
        }

    }

    public function loginLdap(Request $request)
    {
        if (!isset($request->username)) {
            return $this->returnErrorData('[username] ไม่มีข้อมูล', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('[password] ไม่มีข้อมูล', 404);
        }

        if (!function_exists('ldap_connect')) {
            return response()->json([
                'code' => '500',
                'status' => false,
                'message' => 'LDAP extension is not enabled on this server.',
                'data' => [],
            ], 500);
        }


        $username = (string) $request->username;
        $password = (string) $request->password;

        $config = config('services.ldap', []);
        $ldapUrl = data_get($config, 'url');

        if (empty($ldapUrl)) {
            return response()->json([
                'code' => '500',
                'status' => false,
                'message' => 'LDAP is not configured (LDAP_URL).',
                'data' => [],
            ], 500);
        }

        $connection = @ldap_connect($ldapUrl);
        if (!$connection) {
            return response()->json([
                'code' => '500',
                'status' => false,
                'message' => 'Unable to connect to LDAP server.',
                'data' => [],
            ], 500);
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, (int) data_get($config, 'timeout', 5));

        if (data_get($config, 'start_tls', false)) {
            if (!@ldap_start_tls($connection)) {
                return response()->json([
                    'code' => '500',
                    'status' => false,
                    'message' => 'Failed to start TLS with LDAP server.',
                    'data' => [],
                ], 500);
            }
        }

        $userDn = null;
        $bindDn = data_get($config, 'bind_dn');
        $bindPassword = data_get($config, 'bind_password');

        if (!empty($bindDn)) {
            if (!@ldap_bind($connection, $bindDn, (string) $bindPassword)) {
                return response()->json([
                    'code' => '500',
                    'status' => false,
                    'message' => 'LDAP service bind failed (LDAP_BIND_DN / LDAP_BIND_PASSWORD).',
                    'data' => [],
                ], 500);
            }

            $baseDn = data_get($config, 'base_dn');
            if (empty($baseDn)) {
                return response()->json([
                    'code' => '500',
                    'status' => false,
                    'message' => 'LDAP base DN is not configured (LDAP_BASE_DN).',
                    'data' => [],
                ], 500);
            }

            $attribute = (string) data_get($config, 'user_attribute', 'sAMAccountName');
            $escaped = $this->ldapEscapeFilterValue($username);
            $filter = '(' . $attribute . '=' . $escaped . ')';

            $search = @ldap_search($connection, $baseDn, $filter, ['dn']);
            if (!$search) {
                return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
            }

            $entries = @ldap_get_entries($connection, $search);
            if (!is_array($entries) || empty($entries['count']) || empty($entries[0]['dn'])) {
                return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
            }

            $userDn = (string) $entries[0]['dn'];

            if (!@ldap_bind($connection, $userDn, $password)) {
                return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
            }
        } else {
            $template = data_get($config, 'user_dn_template');
            if (empty($template)) {
                return response()->json([
                    'code' => '500',
                    'status' => false,
                    'message' => 'LDAP user DN template is not configured (LDAP_USER_DN_TEMPLATE).',
                    'data' => [],
                ], 500);
            }

            $userDn = sprintf((string) $template, $username);
            if (!@ldap_bind($connection, $userDn, $password)) {
                return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
            }
        }

        $employee = DB::table('employees')
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$employee) {
            return $this->returnErrorData('ไม่พบข้อมูลพนักงานในระบบ', 404);
        }

        $logType = 'เข้าสู่ระบบ (LDAP)';
        $logDescription = 'ผู้ใช้งาน ' . $username . ' ได้ทำการ ' . $logType;
        $this->Log($username, $logDescription, $logType);

        return response()->json([
            'code' => '200',
            'status' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'data' => $employee,
            'token' => $this->genToken($employee->id, $employee),
        ], 200);
    }

    private function ldapEscapeFilterValue($value)
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        $value = (string) $value;
        $search = ['\\', '*', '(', ')', "\x00"];
        $replace = ['\\5c', '\\2a', '\\28', '\\29', '\\00'];
        return str_replace($search, $replace, $value);
    }

    public function loginMember(Request $request)
    {
        if (!isset($request->username)) {
            return $this->returnErrorData('[username] ไม่มีข้อมูล', 404);
        } else if (!isset($request->password)) {
            return $this->returnErrorData('[password] ไม่มีข้อมูล', 404);
        }

        $user = Member::where('username', $request->username)
            ->where('password', md5($request->password))
            ->first();

        if ($user) {

            //log
            $username = $user->username;
            $log_type = 'เข้าสู่ระบบ';
            $log_description = 'ผู้ใช้งาน ' . $username . ' ได้ทำการ ' . $log_type;
            $this->Log($username, $log_description, $log_type);
            //

            return response()->json([
                'code' => '200',
                'status' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'data' => $user,
                'token' => $this->genToken($user->id, $user),
            ], 200);
        } else {
            return $this->returnError('รหัสผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง', 401);
        }

    }

}

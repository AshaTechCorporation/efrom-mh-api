<?php

namespace App\Http\Controllers;

use App\Mail\SendMail;
use App\Models\Log;
use App\Models\Orders;
use App\Models\Signature;
use App\Models\Clients;
use App\Models\Factory;
use App\Models\OrderList;
use App\Models\Products;
use App\Models\CategoryProduct;
use App\Models\SubCategoryProduct;
use App\Models\User;
use App\Models\unit;
use App\Models\ProductUnit;
use App\Models\Promotion;
use App\Models\ProductImages;
use App\Models\ProductImagesPanorama;
use App\Models\ZoneMarket;
use App\Models\ZoneMarketList;
use App\Models\UserZoneMarket;
use App\Models\Commission;
use App\Models\CommissionStep;
use App\Models\Banner;
use App\Models\Text;
use App\Models\TextPosition;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\DB;
use Berkayk\OneSignal\OneSignalFacade;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Imports\ClientsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function returnSuccess($massage, $data)
    {

        return response()->json([
            'code' => strval(200),
            'status' => true,
            'message' => $massage,
            'data' => $data,
        ], 200);
    }

    public function returnUpdate($massage)
    {
        return response()->json([
            'code' => strval(201),
            'status' => true,
            'message' => $massage,
            'data' => [],
        ], 201);
    }

    public function returnUpdateReturnData($massage, $data)
    {
        return response()->json([
            'code' => strval(201),
            'status' => true,
            'message' => $massage,
            'data' => $data,
        ], 201);
    }

    public function returnErrorData($massage, $code)
    {
        return response()->json([
            'code' => strval($code),
            'status' => false,
            'message' => $massage,
            'data' => [],
        ], 404);
    }

    public function returnError($massage)
    {
        return response()->json([
            'code' => strval(401),
            'status' => false,
            'message' => $massage,
            'data' => [],
        ], 401);
    }

    public function Log($userId, $description, $type)
    {
        $Log = new Log();
        $Log->user_id = $userId;
        $Log->description = $description;
        $Log->type = $type;
        $Log->save();
    }

    public function sendMail($email, $data, $title, $type)
    {

        $mail = new SendMail($email, $data, $title, $type);
        Mail::to($email)->send($mail);
    }

    public function sendLine($line_token, $text)
    {

        $sToken = $line_token;
        $sMessage = $text;

        $chOne = curl_init();
        curl_setopt($chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
        curl_setopt($chOne, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($chOne, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($chOne, CURLOPT_POST, 1);
        curl_setopt($chOne, CURLOPT_POSTFIELDS, "message=" . $sMessage);
        $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $sToken . '');
        curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($chOne, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($chOne);

        curl_close($chOne);
    }

    // public function uploadImages(Request $request)
    // {

    //     $image = $request->image;
    //     $path = $request->path;

    //     $input['imagename'] = md5(rand(0, 999999) . $image->getClientOriginalName()) . '.' . $image->extension();
    //     $destinationPath = public_path('/thumbnail');
    //     if (!File::exists($destinationPath)) {
    //         File::makeDirectory($destinationPath, 0777, true);
    //     }

    //     $img = Image::make($image->path());
    //     $img->save($destinationPath . '/' . $input['imagename']);
    //     $destinationPath = public_path($path);
    //     $image->move($destinationPath, $input['imagename']);

    //     return $this->returnSuccess('ดำเนินการสำเร็จ', $path . $input['imagename']);
    // }

    public function uploadImages(Request $request)
    {
        $image = $request->image;
        $path = $request->path;
        $original = $request->original;

        // ✅ ตรวจสอบว่าจะใช้ชื่อไฟล์เดิมหรือสุ่มใหม่
        if ($original === 'Y') {
            $imageName = $image->getClientOriginalName();
        } else {
            $imageName = md5(rand(0, 999999) . $image->getClientOriginalName()) . '.' . $image->extension();
        }

        // สร้างโฟลเดอร์ thumbnail ถ้ายังไม่มี
        $thumbnailPath = public_path('/thumbnail');
        if (!File::exists($thumbnailPath)) {
            File::makeDirectory($thumbnailPath, 0777, true);
        }

        // บันทึกภาพ thumbnail
        $img = Image::make($image->path());
        $img->save($thumbnailPath . '/' . $imageName);

        // บันทึกภาพต้นฉบับตาม path ที่กำหนด
        $destinationPath = public_path($path);
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }
        $image->move($destinationPath, $imageName);

        return $this->returnSuccess('ดำเนินการสำเร็จ', $path . $imageName);
    }


    public function uploadSignature(Request $request)
    {

        $image = $request->image;
        $path = $request->path;
        $refno = $request->refno;
        $action = $request->action;

        $input['imagename'] = md5(rand(0, 999999) . $image->getClientOriginalName()) . '.' . $image->extension();
        $destinationPath = public_path('/thumbnail');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }

        $img = Image::make($image->path());
        $img->save($destinationPath . '/' . $input['imagename']);
        $destinationPath = public_path($path);
        $image->move($destinationPath, $input['imagename']);

        DB::beginTransaction();

        try {

            $Item = new Signature();
            $Item->refno = $refno;
            $Item->path = $path . $input['imagename'];
            $Item->action = $action;
            $Item->save();

            $ItemOrder = Orders::where('code', $refno)->first();
            if ($ItemOrder) {
                if ($Item->action == "Receive to Client") {
                    $ItemOrder->status = "ToClient";
                } else {
                    $ItemOrder->status = "Recived";
                }

                $ItemOrder->save();

                OneSignalFacade::sendNotificationToAll("แจ้งเตือนรายการนำเข้าโกดังสำเร็จรายการ : " . $refno);
            }

            //

            //log
            $userId = "admin";
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $userId . ' ได้ทำการ ' . $type . ' ' . $request->name;
            $this->Log($userId, $description, $type);
            //

            DB::commit();

            return $this->returnSuccess('ดำเนินการสำเร็จ', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ', 404);
        }

        // return $this->returnSuccess('ดำเนินการสำเร็จ', $path . $input['imagename']);
    }

    public function uploadImage($image, $path)
    {
        $input['imagename'] = md5(rand(0, 999999) . $image->getClientOriginalName()) . '.' . $image->extension();
        $destinationPath = public_path('/thumbnail');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }

        $img = Image::make($image->path());
        $img->save($destinationPath . '/' . $input['imagename']);
        $destinationPath = public_path($path);
        $image->move($destinationPath, $input['imagename']);

        return $path . $input['imagename'];
    }

    public function uploadFile(Request $request)
    {

        $file = $request->file;
        $path = $request->path;

        $input['filename'] = time() . '.' . $file->extension();

        $destinationPath = public_path('/file_thumbnail');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }

        $destinationPath = public_path($path);
        $file->move($destinationPath, $input['filename']);

        return $path . $input['filename'];
    }

    // public function uploadFile($file, $path)
    // {
    //     $input['filename'] = time() . '.' . $file->extension();
    //     $destinationPath = public_path('/file_thumbnail');
    //     if (!File::exists($destinationPath)) {
    //         File::makeDirectory($destinationPath, 0777, true);
    //     }

    //     $destinationPath = public_path($path);
    //     $file->move($destinationPath, $input['filename']);

    //     return $path . $input['filename'];
    // }

    public function getDropDownYear()
    {
        $Year = intval(((date('Y')) + 1) + 543);

        $data = [];

        for ($i = 0; $i < 10; $i++) {

            $Year = $Year - 1;
            $data[$i]['year'] = $Year;
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    public function getDropDownProvince()
    {

        $province = array("กระบี่", "กรุงเทพมหานคร", "กาญจนบุรี", "กาฬสินธุ์", "กำแพงเพชร", "ขอนแก่น", "จันทบุรี", "ฉะเชิงเทรา", "ชลบุรี", "ชัยนาท", "ชัยภูมิ", "ชุมพร", "เชียงราย", "เชียงใหม่", "ตรัง", "ตราด", "ตาก", "นครนายก", "นครปฐม", "นครพนม", "นครราชสีมา", "นครศรีธรรมราช", "นครสวรรค์", "นนทบุรี", "นราธิวาส", "น่าน", "บุรีรัมย์", "บึงกาฬ", "ปทุมธานี", "ประจวบคีรีขันธ์", "ปราจีนบุรี", "ปัตตานี", "พะเยา", "พังงา", "พัทลุง", "พิจิตร", "พิษณุโลก", "เพชรบุรี", "เพชรบูรณ์", "แพร่", "ภูเก็ต", "มหาสารคาม", "มุกดาหาร", "แม่ฮ่องสอน", "ยโสธร", "ยะลา", "ร้อยเอ็ด", "ระนอง", "ระยอง", "ราชบุรี", "ลพบุรี", "ลำปาง", "ลำพูน", "เลย", "ศรีสะเกษ", "สกลนคร", "สงขลา", "สตูล", "สมุทรปราการ", "สมุทรสงคราม", "สมุทรสาคร", "สระแก้ว", "สระบุรี", "สิงห์บุรี", "สุโขทัย", "สุพรรณบุรี", "สุราษฎร์ธานี", "สุรินทร์", "หนองคาย", "หนองบัวลำภู", "อยุธยา", "อ่างทอง", "อำนาจเจริญ", "อุดรธานี", "อุตรดิตถ์", "อุทัยธานี", "อุบลราชธานี");

        $data = [];

        for ($i = 0; $i < count($province); $i++) {

            $data[$i]['province'] = $province[$i];
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $data);
    }

    public function getDownloadFomatImport($params)
    {

        $file = $params;
        $destinationPath = public_path() . "/fomat_import/";

        return response()->download($destinationPath . $file);
    }

    public function checkDigitMemberId($memberId)
    {

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {

            $sum += (int) ($memberId[$i]) * (13 - $i);
        }

        if ((11 - ($sum % 11)) % 10 == (int) ($memberId[12])) {
            return 'true';
        } else {
            return 'false';
        }
    }

    public function genCode(Model $model, $prefix, $number)
    {

        $countPrefix = strlen($prefix);
        $countRunNumber = strlen($number);

        //get last code
        $Property_type = $model::orderby('code', 'desc')->first();
        if ($Property_type) {
            $lastCode = $Property_type->code;
        } else {
            $lastCode = $prefix . $number;
        }

        $codelast = substr($lastCode, $countPrefix, $countRunNumber);

        $newNumber = intval($codelast) + 1;
        $Number = sprintf('%0' . strval($countRunNumber) . 'd', $newNumber);

        $runNumber = $prefix . $Number;

        return $runNumber;
    }


    // public function dateBetween($dateStart, $dateStop)
    // {
    //     $datediff = strtotime($dateStop) - strtotime($this->dateform($dateStart));
    //     return abs($datediff / (60 * 60 * 24));
    // }

    // public function log_noti($Title, $Description, $Url, $Pic, $Type)
    // {
    //     $log_noti = new Log_noti();
    //     $log_noti->title = $Title;
    //     $log_noti->description = $Description;
    //     $log_noti->url = $Url;
    //     $log_noti->pic = $Pic;
    //     $log_noti->log_noti_type = $Type;

    //     $log_noti->save();
    // }

    /////////////////////////////////////////// seach datatable  ///////////////////////////////////////////

    public function withPermission($query, $search)
    {

        $col = array('id', 'name', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('permission', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withMember($query, $search)
    {

        // $col = array('id', 'member_group_id','code', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        // $query->orWhereHas('member', function ($query) use ($search, $col) {

        //     $query->Where(function ($query) use ($search, $col) {

        //         //search datatable
        //         $query->orwhere(function ($query) use ($search, $col) {
        //             foreach ($col as &$c) {
        //                 $query->orWhere($c, 'like', '%' . $search['value'] . '%');
        //             }
        //         });
        //     });

        // });

        // return $query;
    }


    public function withInquiryType($query, $search)
    {

        $col = array('id', 'code', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('inquiry_type', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertyType($query, $search)
    {

        $col = array('id', 'code', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_type', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertySubType($query, $search)
    {

        $col = array('id', 'property_type_id', 'code', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_sub_type', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertyAnnouncer($query, $search)
    {

        $col = array('id', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_announcer', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertyColorLand($query, $search)
    {

        $col = array('id', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_color_land', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertyOwnership($query, $search)
    {

        $col = array('id', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_ownership', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertyFacility($query, $search)
    {

        $col = array('id', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_facility', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });
            });
        });

        return $query;
    }

    public function withPropertySubFacility($query, $search)
    {

        $col = array('id', 'property_facility_id', 'name', 'icon', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_sub_facility', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });

                $query = $this->withPropertyFacility($query, $search);
            });
        });

        return $query;
    }

    public function withPropertySubFacilityExplend($query, $search)
    {

        $col = array('id', 'property_sub_facility_id', 'name', 'status', 'create_by', 'update_by', 'created_at', 'updated_at');

        $query->orWhereHas('property_sub_facility_explend', function ($query) use ($search, $col) {

            $query->Where(function ($query) use ($search, $col) {

                //search datatable
                $query->orwhere(function ($query) use ($search, $col) {
                    foreach ($col as &$c) {
                        $query->orWhere($c, 'like', '%' . $search['value'] . '%');
                    }
                });

                $query = $this->withPropertySubFacility($query, $search);
            });
        });

        return $query;
    }

    /////////////////////////////////////////// seach datatable  ///////////////////////////////////////////


    public function getSyncDataF(Request $request)
    {
        $loginBy = $request->login_by;

        $orders = $request->orders;

        if ($orders) {
            foreach ($orders as $orderData) {
                if($orderData) {
                    // --- จัดการข้อมูลลูกค้า ---
                    $client = null;
                    if (isset($orderData['client_id']) && !empty($orderData['client_id'])) {
                        $client = Clients::find($orderData['client_id']);
                        if ($client) {
                            $client->name    = $orderData['client_name'] ?? $client->name;
                            $client->phone   = $orderData['client_phone'] ?? $client->phone;
                            $client->email   = $orderData['client_email'] ?? $client->email;
                            $client->address = $orderData['client_address'] ?? $client->address;
                            $client->save();
                        } else {
                            $client = new Clients();
                            $client->name    = $orderData['client_name'] ?? null;
                            $client->phone   = $orderData['client_phone'] ?? null;
                            $client->email   = $orderData['client_email'] ?? null;
                            $client->address = $orderData['client_address'] ?? null;
                            $client->save();
                        }
                    } else {
                        // สร้างลูกค้าใหม่ หากไม่มี client_id
                        $client = new Clients();
                        $client->name    = $orderData['client_name'] ?? null;
                        $client->phone   = $orderData['client_phone'] ?? null;
                        $client->email   = $orderData['client_email'] ?? null;
                        $client->address = $orderData['client_address'] ?? null;
                        $client->save();
                    }

                    // --- สร้าง Order ---
                    // สร้าง order code ด้วย timestamp และตัวเลขสุ่ม
                    $prefix = "#OR-";
                    $id = IdGenerator::generate(['table' => 'orders', 'field' => 'code', 'length' => 9, 'prefix' => $prefix]);
        
        
                    $order = new Orders();
                    $order->code        = $id;
                    $order->date        = $orderData['date'] ?? now()->format('Y-m-d');
                    $order->client_id   = $client->id;
                    $order->client_name = $client->name;
                    $order->client_phone = $client->phone;
                    $order->client_email = $client->email;
                    $order->client_address = $client->address;
                    $order->total_price = $orderData['total_price'] ?? 0;
                    $order->status      = 'Ordered';
                    $order->create_by = $loginBy->id;
                    $order->save();

                    // --- สร้าง OrderList สำหรับสินค้าภายใน Order ---
                    if (isset($orderData['products']) && is_array($orderData['products'])) {
                        foreach ($orderData['products'] as $prodData) {
                            // ตรวจสอบสต็อกใน ProductUnit ตาม product_id และ unit_id
                            $checkStock = ProductUnit::where('product_id', $prodData['product_id'])
                                ->where('unit_id', $prodData['unit_id'])
                                ->first();

                            // ดึงข้อมูลหน่วย (Unit)
                            $unit = unit::find($prodData['unit_id']);

                            if ($checkStock) {
                                if ($checkStock->qty < $prodData['qty']) {
                                    $qtyOrder = $prodData['qty'] - $checkStock->qty;

                                    // สร้าง Factory code ด้วย IdGenerator
                                    $prefix = "#FAC-";
                                    $factoryCode = IdGenerator::generate([
                                        'table'  => 'factories',
                                        'field'  => 'code',
                                        'length' => 13,
                                        'prefix' => $prefix
                                    ]);

                                    $factory = new Factory();
                                    $factory->code      = $factoryCode;
                                    $factory->date      = date('Y-m-d');
                                    $factory->order_id  = $order->id;
                                    $factory->product_id = $prodData['product_id'];
                                    $factory->qty       = $qtyOrder;
                                    $factory->unit_id   = $prodData['unit_id'];
                                    $factory->detail    = "สินค้าไม่เพียงพอต่อการจำหน่าย ขาดไปทั้งหมด " 
                                        . $qtyOrder . ' ' . ($unit ? $unit->name : '') . " จำเป็นต้องสั่งผลิต";
                                    $factory->save();
                                }
                            } else {
                                DB::rollBack();
                                return $this->returnErrorData('ไม่พบหน่วยของสินค้านี้ในระบบ', 404);
                            }

                            $orderList = new OrderList();
                            $orderList->order_id     = $order->id;
                            $orderList->product_id   = $prodData['product_id'];
                            $orderList->cost         = $prodData['cost'] ?? 0;
                            $orderList->price        = $prodData['price'] ?? 0;
                            $orderList->qty          = $prodData['qty'] ?? 0;
                            $orderList->unit_id      = $prodData['unit_id'] ?? null;
                            $orderList->promotion_id = $prodData['promotion_id'] ?? null;
                            $orderList->discount     = $prodData['discount'] ?? 0;
                            $orderList->create_by = $loginBy->id;
                            $orderList->save();
                        }
                    }
                }
            }
        }

        $UserzoneMarkets = UserZoneMarket::where('user_id', $loginBy->id)->get();

        $allClients = collect(); // สะสมลูกค้าทั้งหมดที่พบ

        foreach ($UserzoneMarkets as $value) {
            $zoneMarket = ZoneMarket::find($value->zone_market_id);

            if ($zoneMarket) {
   
                // ดึงรายการพื้นที่ในโซนตลาดนั้น
                $zoneMarketLists = ZoneMarketList::where("zone_market_id", $value->zone_market_id)
                    ->select('province', 'district', 'subdistrict', 'postal_code')
                    ->get();

                if ($zoneMarketLists->isNotEmpty()) {
                    // เตรียมเงื่อนไขเพื่อค้นหาลูกค้าในพื้นที่
                    $clients = Clients::where(function($query) use ($zoneMarketLists) {
                        foreach ($zoneMarketLists as $zone) {
                            $query->orWhere(function($q) use ($zone) {
                                $q->where('province', $zone->province);
                                // ->where('district', $zone->district)
                                // ->where('subdistrict', $zone->subdistrict)
                                // ->where('postal_code', $zone->postal_code);
                            });
                        }
                    })->get();

                    // รวมลูกค้าเข้ากับ collection หลัก
                    $allClients = $allClients->merge($clients);
                }
            }
        }

        // กรองข้อมูลซ้ำ (เผื่อว่าลูกค้าคนเดียวอยู่หลายโซน)
        $allClients = $allClients->unique('id')->values();
        
        // หากไม่มี last_sync ส่งข้อมูลทั้งหมด
        $sub_categories   = SubCategoryProduct::all();
        $categories   = CategoryProduct::all();
        $products   = Products::with('product_add_ons.product.product_images')->get();
        $products = Products::with('product_add_ons.product.product_images')->get();

        $products->each(function ($product) {
            $product->product_add_ons->each(function ($addOn) {
                $addOn->product->product_images->each(function ($image) {
                    $image->image = url($image->image);
                });
            });
        });
        
        $clients    = $allClients;
        $productIds = $products->pluck('id')->unique();
        $allPromotions = Promotion::whereIn('product_id', $productIds)->get();
        $allUnits = ProductUnit::whereIn('product_id', $productIds)->get();
        $orders     = Orders::all();
        $orderIds   = $orders->pluck('id')->unique();
        $orderLists = OrderList::whereIn('order_id', $orderIds)->get();

         // --- แนบโปรโมชั่นเข้าไปในสินค้า ---
        // จัดกลุ่มโปรโมชั่นโดยใช้ product_id เป็น key
        $unitsByProduct = $allUnits->groupBy('product_id');
        foreach ($products as $product) {
            // แนบ array ของโปรโมชั่นให้กับ property promotions (ถ้าไม่มีจะได้เป็น array ว่าง)
            $product->category = CategoryProduct::find($product->category_product_id);
            $product->sub_category = SubCategoryProduct::find($product->sub_category_product_id);
            $product->units = $unitsByProduct->get($product->id, []);
            foreach ($product->units as $key => $value) {
                $product->units[$key]->unit = unit::find($value['unit_id']);
            }

            $product->images = ProductImages::where('product_id', $product->id)->get();

            for ($n = 0; $n <= count($product->images) - 1; $n++) {
                $product->images[$n]->image = url($product->images[$n]->image);
            }

            // $product->panorama_images = ProductImagesPanorama::where('product_id', $product->id)->get();

            // for ($n = 0; $n <= count($product->panorama_images) - 1; $n++) {
            //     $product->panorama_images[$n]->image = url($product->panorama_images[$n]->image);
            // }
        }
    
        // --- แนบโปรโมชั่นเข้าไปในสินค้า ---
        // จัดกลุ่มโปรโมชั่นโดยใช้ product_id เป็น key
        $promotionsByProduct = $allPromotions->groupBy('product_id');
        foreach ($products as $product) {
            // แนบ array ของโปรโมชั่นให้กับ property promotions (ถ้าไม่มีจะได้เป็น array ว่าง)
            $product->promotions = $promotionsByProduct->get($product->id, []);
            foreach ($product->promotions as $key => $value) {
                $product->promotions[$key]->product_fee = Products::find($value['product_free_id']);
            }
        }
    
        // --- แนบ order_lists เข้าไปใน orders ---
        // จัดกลุ่ม order_lists โดยใช้ order_id เป็น key
        $orderListsByOrder = $orderLists->groupBy('order_id');
        foreach ($orders as $order) {
            $order->order_lists = $orderListsByOrder->get($order->id, []);
        }
    
        // --- แนบ orders เข้าไปใน clients ---
        // จัดกลุ่ม orders โดยใช้ client_id เป็น key
        $ordersByClient = $orders->groupBy('client_id');
        foreach ($clients as $client) {
            $client->orders = $ordersByClient->get($client->id, []);
        }
    
        return response()->json([
            'success' => true,
            'data'    => [
                'categories' => $categories,
                'sub_categories' => $sub_categories,
                'products' => $products,
                'clients'  => $clients,
                'orders'   => $orders,
            ]
        ]);
    }
    
    public function getSyncData(Request $request)
    {
        $loginBy = $request->login_by;

        // รับค่า last_sync จาก query parameter (ตัวอย่าง: ?last_sync=2025-03-06T12:00:00Z)
        $lastSync = $request->last_sync;
        $orders = $request->orders;

        if ($orders) {
            foreach ($orders as $orderData) {
                if($orderData) {
                    // --- จัดการข้อมูลลูกค้า ---
                    $client = null;
                    if (isset($orderData['client_id']) && !empty($orderData['client_id'])) {
                        $client = Clients::find($orderData['client_id']);
                        if ($client) {
                            $client->name    = $orderData['client_name'] ?? $client->name;
                            $client->phone   = $orderData['client_phone'] ?? $client->phone;
                            $client->email   = $orderData['client_email'] ?? $client->email;
                            $client->address = $orderData['client_address'] ?? $client->address;
                            $client->save();
                        } else {
                            $client = new Clients();
                            $client->name    = $orderData['client_name'] ?? null;
                            $client->phone   = $orderData['client_phone'] ?? null;
                            $client->email   = $orderData['client_email'] ?? null;
                            $client->address = $orderData['client_address'] ?? null;
                            $client->save();
                        }
                    } else {
                        // สร้างลูกค้าใหม่ หากไม่มี client_id
                        $client = new Clients();
                        $client->name    = $orderData['client_name'] ?? null;
                        $client->phone   = $orderData['client_phone'] ?? null;
                        $client->email   = $orderData['client_email'] ?? null;
                        $client->address = $orderData['client_address'] ?? null;
                        $client->save();
                    }

                    // --- สร้าง Order ---
                    // สร้าง order code ด้วย timestamp และตัวเลขสุ่ม
                    $prefix = "#OR-";
                    $id = IdGenerator::generate(['table' => 'orders', 'field' => 'code', 'length' => 9, 'prefix' => $prefix]);
        
        
                    $order = new Orders();
                    $order->code        = $id;
                    $order->date        = $orderData['date'] ?? now()->format('Y-m-d');
                    $order->client_id   = $client->id;
                    $order->client_name = $client->name;
                    $order->client_phone = $client->phone;
                    $order->client_email = $client->email;
                    $order->client_address = $client->address;
                    $order->total_price = $orderData['total_price'] ?? 0;
                    $order->status      = 'Ordered';
                    $order->create_by = $loginBy->id;
                    $order->save();

                    // --- สร้าง OrderList สำหรับสินค้าภายใน Order ---
                    if (isset($orderData['products']) && is_array($orderData['products'])) {
                        foreach ($orderData['products'] as $prodData) {
                            // ตรวจสอบสต็อกใน ProductUnit ตาม product_id และ unit_id
                            $checkStock = ProductUnit::where('product_id', $prodData['product_id'])
                                ->where('unit_id', $prodData['unit_id'])
                                ->first();

                            // ดึงข้อมูลหน่วย (Unit)
                            $unit = unit::find($prodData['unit_id']);

                            if ($checkStock) {
                                if ($checkStock->qty < $prodData['qty']) {
                                    $qtyOrder = $prodData['qty'] - $checkStock->qty;

                                    // สร้าง Factory code ด้วย IdGenerator
                                    $prefix = "#FAC-";
                                    $factoryCode = IdGenerator::generate([
                                        'table'  => 'factories',
                                        'field'  => 'code',
                                        'length' => 13,
                                        'prefix' => $prefix
                                    ]);

                                    $factory = new Factory();
                                    $factory->code      = $factoryCode;
                                    $factory->date      = date('Y-m-d');
                                    $factory->order_id  = $order->id;
                                    $factory->product_id = $prodData['product_id'];
                                    $factory->qty       = $qtyOrder;
                                    $factory->unit_id   = $prodData['unit_id'];
                                    $factory->detail    = "สินค้าไม่เพียงพอต่อการจำหน่าย ขาดไปทั้งหมด " 
                                        . $qtyOrder . ' ' . ($unit ? $unit->name : '') . " จำเป็นต้องสั่งผลิต";
                                    $factory->save();
                                }
                            } else {
                                DB::rollBack();
                                return $this->returnErrorData('ไม่พบหน่วยของสินค้านี้ในระบบ', 404);
                            }

                            $orderList = new OrderList();
                            $orderList->order_id     = $order->id;
                            $orderList->product_id   = $prodData['product_id'];
                            $orderList->cost         = $prodData['cost'] ?? 0;
                            $orderList->price        = $prodData['price'] ?? 0;
                            $orderList->qty          = $prodData['qty'] ?? 0;
                            $orderList->unit_id      = $prodData['unit_id'] ?? null;
                            $orderList->promotion_id = $prodData['promotion_id'] ?? null;
                            $orderList->discount     = $prodData['discount'] ?? 0;
                            $orderList->create_by = $loginBy->id;
                            $orderList->save();
                        }
                    }
                }
            }
        }

        if ($lastSync) {
            // ดึงข้อมูลที่มีการเปลี่ยนแปลงใหม่ โดยตรวจสอบทั้ง created_at และ updated_at
            $categories = CategoryProduct::where(function ($query) use ($lastSync) {
                $query->where('created_at', '>', $lastSync)
                      ->orWhere('updated_at', '>', $lastSync);
            })->get();

            $sub_categories = SubCategoryProduct::where(function ($query) use ($lastSync) {
                $query->where('created_at', '>', $lastSync)
                      ->orWhere('updated_at', '>', $lastSync);
            })->get();

            $products = Products::with('product_add_ons.product.product_images')->where(function ($query) use ($lastSync) {
                $query->where('created_at', '>', $lastSync)
                      ->orWhere('updated_at', '>', $lastSync);
            })->get();

            $products->each(function ($product) {
                $product->product_add_ons->each(function ($addOn) {
                    $addOn->product->product_images->each(function ($image) {
                        $image->image = url($image->image);
                    });
                });
            });
        
            $clients = Clients::where(function ($query) use ($lastSync) {
                $query->where('created_at', '>', $lastSync)
                      ->orWhere('updated_at', '>', $lastSync);
            })->get();
    
            // ดึงโปรโมชั่นที่เกี่ยวข้องกับสินค้าที่ได้มา
            $productIds = $products->pluck('id')->unique();
            $allPromotions = Promotion::whereIn('product_id', $productIds)->get();
            $allUnits = ProductUnit::whereIn('product_id', $productIds)->get();

            // ดึงข้อมูล orders ที่เปลี่ยนแปลง
            $orders = Orders::where(function ($query) use ($lastSync) {
                $query->where('created_at', '>', $lastSync)
                      ->orWhere('updated_at', '>', $lastSync);
            })->get();
    
            // ดึง order_lists ที่เกี่ยวข้องกับ orders ที่ได้มา
            $orderIds = $orders->pluck('id')->unique();
            $orderLists = OrderList::whereIn('order_id', $orderIds)->get();
        } else {
            // หากไม่มี last_sync ส่งข้อมูลทั้งหมด
            $sub_categories   = SubCategoryProduct::all();
            $categories   = CategoryProduct::all();
            $products   = Products::with('product_add_ons.product.product_images')->get();
            $products->each(function ($product) {
                $product->product_add_ons->each(function ($addOn) {
                    $addOn->product->product_images->each(function ($image) {
                        $image->image = url($image->image);
                    });
                });
            });
            $clients    = Clients::all();
            $productIds = $products->pluck('id')->unique();
            $allPromotions = Promotion::whereIn('product_id', $productIds)->get();
            $allUnits = ProductUnit::whereIn('product_id', $productIds)->get();
            $orders     = Orders::all();
            $orderIds   = $orders->pluck('id')->unique();
            $orderLists = OrderList::whereIn('order_id', $orderIds)->get();
        }

         // --- แนบโปรโมชั่นเข้าไปในสินค้า ---
        // จัดกลุ่มโปรโมชั่นโดยใช้ product_id เป็น key
        $unitsByProduct = $allUnits->groupBy('product_id');
        foreach ($products as $product) {
            // แนบ array ของโปรโมชั่นให้กับ property promotions (ถ้าไม่มีจะได้เป็น array ว่าง)
            $product->units = $unitsByProduct->get($product->id, []);
            $product->category = CategoryProduct::find($product->category_product_id);
            $product->sub_category = SubCategoryProduct::find($product->sub_category_product_id);
            foreach ($product->units as $key => $value) {
                $product->units[$key]->unit = unit::find($value['unit_id']);
            }

            $product->images = ProductImages::where('product_id', $product->id)->get();

            for ($n = 0; $n <= count($product->images) - 1; $n++) {
                $product->images[$n]->image = url($product->images[$n]->image);
            }

            $product->panorama_images = ProductImagesPanorama::where('product_id', $product->id)->get();

            for ($n = 0; $n <= count($product->panorama_images) - 1; $n++) {
                $product->panorama_images[$n]->image = url($product->panorama_images[$n]->image);
            }
        }
    
        // --- แนบโปรโมชั่นเข้าไปในสินค้า ---
        // จัดกลุ่มโปรโมชั่นโดยใช้ product_id เป็น key
        $promotionsByProduct = $allPromotions->groupBy('product_id');
        foreach ($products as $product) {
            // แนบ array ของโปรโมชั่นให้กับ property promotions (ถ้าไม่มีจะได้เป็น array ว่าง)
            $product->promotions = $promotionsByProduct->get($product->id, []);
            foreach ($product->promotions as $key => $value) {
                $product->promotions[$key]->product_fee = Products::find($value['product_free_id']);
            }
        }
    
        // --- แนบ order_lists เข้าไปใน orders ---
        // จัดกลุ่ม order_lists โดยใช้ order_id เป็น key
        $orderListsByOrder = $orderLists->groupBy('order_id');
        foreach ($orders as $order) {
            $order->order_lists = $orderListsByOrder->get($order->id, []);
        }
    
        // --- แนบ orders เข้าไปใน clients ---
        // จัดกลุ่ม orders โดยใช้ client_id เป็น key
        $ordersByClient = $orders->groupBy('client_id');
        foreach ($clients as $client) {
            $client->orders = $ordersByClient->get($client->id, []);
        }
    
        return response()->json([
            'success' => true,
            'data'    => [
                'categories' => $categories,
                'sub_categories' => $sub_categories,
                'products' => $products,
                'clients'  => $clients,
                'orders'   => $orders,
            ]
        ]);
    }

    public function importClients(Request $request)
    {
        $file = $request->file('file');

        $data = Excel::toArray([], $file);
        $rows = $data[0];

        
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // ข้าม header ถ้ามี

            $code = trim($row[0]);
            $name = trim($row[1]);
            $phone = trim($row[3] ?? '');
            $addressRaw = trim($row[2] ?? '');

            // แยกจังหวัด อำเภอ ตำบล จากที่อยู่
            preg_match('/ตำบล\s*([^\s]+)/u', $addressRaw, $subdistrictMatch);
            preg_match('/อำเภอ\s*([^\s]+)/u', $addressRaw, $districtMatch);
            preg_match('/จังหวัด\s*([^\s]+)/u', $addressRaw, $provinceMatch);

            $subdistrict = isset($subdistrictMatch[1]) ? trim($subdistrictMatch[1]) : null;
            $district    = isset($districtMatch[1]) ? trim($districtMatch[1]) : null;
            $province    = isset($provinceMatch[1]) ? trim($provinceMatch[1]) : null;

            // $subdistrict = trim($row[4] ?? '');
            // $district = trim($row[5] ?? '');
            // $province = trim($row[6] ?? '');
            $note = trim($row[4] ?? '');

            
            Clients::create([
                'code' => $code,
                'name' => $name,
                'address' => $addressRaw,
                'phone' => $phone,
                'subdistrict' => $subdistrict ?? null,
                'district' => $district ?? null,
                'province' => $province ?? null,
                'note' => $note ?? null,
                'create_by' => auth()->user()->name ?? 'import',
            ]);
        }

        return response()->json(['message' => 'นำเข้าสำเร็จ']);
    }

    public function dashboardSales(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        $target = 1000000; // เป้ายอดขาย 100% = 1,000,000 บาท (แก้ได้ตามจริง)

        try {
            $ordersQuery = Orders::with('user')->where('status', 'Approve');

            if ($month) {
                $ordersQuery->whereMonth('date', $month);
            }
            if ($year) {
                $ordersQuery->whereYear('date', $year);
            }

            $orders = $ordersQuery->get();

            $sales = [];

            foreach ($orders as $order) {
                $totalQty = OrderList::where('order_id', $order->id)->sum('qty');
                if (!isset($sales[$order->create_by])) {
                    $sales[$order->create_by] = [
                        'name' => $order->user ? $order->user->name : '-',
                        'total_sales' => 0,
                        'total_qty' => 0,
                        'commission' => 0,
                        'percent' => 0,
                        'target_percent' => 0,
                    ];
                }

                $sales[$order->create_by]['total_sales'] += $order->total_price;
                $sales[$order->create_by]['total_qty'] += $totalQty;
            }

            foreach ($sales as $key => &$data) {
                $productIdsQuery = OrderList::join('orders', 'orders.id', '=', 'order_lists.order_id')
                    ->where('orders.create_by', $key)
                    ->where('orders.status', 'Approve');

                if ($month) {
                    $productIdsQuery->whereMonth('orders.date', $month);
                }
                if ($year) {
                    $productIdsQuery->whereYear('orders.date', $year);
                }

                $productIds = $productIdsQuery->pluck('product_id')->unique();
                $totalCommission = 0;
                $percentUsed = 0;

                foreach ($productIds as $productId) {
                    $commission = Commission::where('product_id', $productId)
                        ->whereNull('deleted_at')
                        ->with(['steps' => function ($q) {
                            $q->whereNull('deleted_at');
                        }])->first();

                    if ($commission && $commission->steps->count()) {
                        foreach ($commission->steps as $step) {
                            if (
                                $data['total_sales'] >= $step->min_sales &&
                                $data['total_sales'] <= $step->max_sales
                            ) {
                                $percentUsed = $step->percent;
                                $totalCommission += ($data['total_sales'] * ($percentUsed / 100));
                                break;
                            }
                        }
                    }
                }

                $data['commission'] = round($totalCommission);
                $data['percent'] = $percentUsed;
                $data['target_percent'] = $target > 0 ? round(($data['total_sales'] / $target) * 100) : 0;

                // ดึงชื่อจาก users เผื่อไม่มี relation
                $user = User::where('username', $key)->first();
                if ($user) {
                    $data['name'] = $user->name;
                }
            }

            $sorted = collect($sales)->sortByDesc('commission')->values();
            $top10 = $sorted->take(10);
            $totalCommission = $sorted->sum('commission');
            $topSales = $top10->first();

            return response()->json([
                'total_commission' => $totalCommission,
                'top_sales' => [
                    'name' => $topSales['name'] ?? null,
                    'amount' => $topSales['commission'] ?? 0,
                    'sales' => $topSales['total_sales'] ?? 0,
                    'percent' => $topSales['percent'] ?? 0,
                    'target_percent' => $topSales['target_percent'] ?? 0,
                ],
                'top_10' => $top10->map(function ($row) {
                    return [
                        'name' => $row['name'],
                        'total_sales' => $row['total_sales'],
                        'commission' => $row['commission'],
                        'percent' => $row['percent'],
                        'target_percent' => $row['target_percent'],
                    ];
                })->values(),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    public function getPersonalSalesDashboard(Request $request)
    {
        $loginBy = $request->login_by;
        $month = $request->input('month');
        $year = $request->input('year');

        $userId = $loginBy->id;
        $userName = $loginBy->name;

        try {
            $orderQuery = Orders::with('order_lists.product')
                ->where('status', 'Approve')
                ->where('create_by', $userId);

            if ($month) {
                $orderQuery->whereMonth('date', $month);
            }
            if ($year) {
                $orderQuery->whereYear('date', $year);
            }

            $orders = $orderQuery->get();

            $totalSales = $orders->sum('total_price');
            $productSales = [];

            foreach ($orders as $order) {
                foreach ($order->order_lists as $item) {
                    $productId = $item->product_id;

                    if (!isset($productSales[$productId])) {
                        $productSales[$productId] = [
                            'product_name' => $item->product->name ?? 'ไม่พบชื่อสินค้า',
                            'total_qty' => 0,
                            'total_amount' => 0,
                            'commission_rate' => 0,
                            'commission_amount' => 0,
                        ];
                    }

                    $productSales[$productId]['total_qty'] += $item->qty;
                    $productSales[$productId]['total_amount'] += $item->qty * $item->price;
                }
            }

            $totalCommission = 0;

            foreach ($productSales as $productId => &$data) {
                $commission = Commission::where('product_id', $productId)->with('steps')->first();

                if ($commission && $commission->steps) {
                    $steps = $commission->steps->sortBy('min_sales');
                    foreach ($steps as $step) {
                        if (
                            $data['total_amount'] >= $step->min_sales &&
                            $data['total_amount'] <= $step->max_sales
                        ) {
                            $rate = $step->percent;
                            $amount = $data['total_amount'] * ($rate / 100);
                            $data['commission_rate'] = $rate;
                            $data['commission_amount'] = round($amount);
                            $totalCommission += $amount;
                            break;
                        }
                    }
                }
            }

            $topProducts = collect($productSales)->sortByDesc('total_amount')->take(10)->values();

            return response()->json([
                'user' => $userName,
                'total_sales' => round($totalSales),
                'total_commission' => round($totalCommission),
                'top_10_products' => $topProducts
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getHomeData()
    {
        // ------------------ BANNERS ------------------
        $banners = Banner::orderBy('id', 'desc')->get();

        // แปลง image เป็น URL เต็ม
        foreach ($banners as $b) {
            if (!empty($b->image)) {
                $b->image_url = url($b->image);
            } else {
                $b->image_url = null;
            }
        }

        // ------------------ TEXTS (ตำแหน่งหน้าแรก) ------------------
        // สมมติว่าหน้าแรกใช้ text_position.name = 'หน้าแรก'
        $textPosition = TextPosition::where('name', 'หน้าแรก')->first();

        $texts = [];
        if ($textPosition) {
            $texts = Text::where('text_position_id', $textPosition->id)
                ->orderBy('sequence_no', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        // ------------------ MEETINGS ------------------
        // ถ้ายังไม่มีเงื่อนไขอะไร ดึงทั้งหมดเรียงล่าสุดก่อน
        $meetings = Meeting::orderBy('id', 'desc')->get();

        // ถ้าอยากแปลง field อะไรพิเศษของ meeting เพิ่ม ตรงนี้ได้เลย
        // เช่น แปลงวันที่เป็น format ไทย ฯลฯ

        // ------------------ RESPONSE รวม ------------------
        $data = [
            'banners'       => $banners,
            'text_position' => $textPosition,
            'texts'         => $texts,
            'meetings'      => $meetings,
        ];

        return $this->returnSuccess('เรียกดูข้อมูลหน้าแรกสำเร็จ', $data);
    }

    public function overview(Request $request)
    {
        // ถ้าอยากกรองตามช่วงวันที่ยื่นเรื่อง (submitted_at)
        $dateStart = $request->date_start; // รูปแบบ YYYY-MM-DD
        $dateEnd   = $request->date_end;   // รูปแบบ YYYY-MM-DD

        // helper สำหรับใส่เงื่อนไขช่วงวันที่
        $scope = function () use ($dateStart, $dateEnd) {
            $q = Projects::query();

            if (!empty($dateStart) && !empty($dateEnd)) {
                $start = Carbon::parse($dateStart)->startOfDay();
                $end   = Carbon::parse($dateEnd)->endOfDay();
                $q->whereBetween('submitted_at', [$start, $end]);
            }

            return $q;
        };

        // ---------- SUMMARY CARD ด้านบน ----------
        $totalProjects = $scope()->count();

        // ถ้าระบบคุณนับผู้ใช้งานจากตารางอื่น เช่น Member ให้เปลี่ยนเป็น Model นั้น
        $totalUsers    = User::count();

        // กำลังพิจารณา = รอการประเมิน + รอชำระค่าธรรมเนียม (แล้วแต่ที่คุณต้องการ)
        $underReview   = $scope()
            ->whereIn('status', ['awaiting_review', 'awaiting_fee'])
            ->count();

        // ---------- PIPELINE ตามสถานะ ----------
        $submittedCount      = $scope()->where('status', 'submitted')->count();        // ยื่นเรื่อง
        $awaitingFeeCount    = $scope()->where('status', 'awaiting_fee')->count();     // รอชำระค่าธรรมเนียม
        $awaitingReviewCount = $scope()->where('status', 'awaiting_review')->count();  // รอการประเมิน

        // เผื่อมีสถานะสำหรับ "ปรับปรุง" ในอนาคต เช่น revision / need_revision
        $revisionCount       = $scope()->where('status', 'revision')->count();         // ปรับปรุง (ถ้ายังไม่มี ให้ได้ 0 ไปก่อน)

        $certifiedCount      = $scope()->where('status', 'certified')->count();        // อนุมัติแล้ว

        // ---------- RESPONSE ----------
        $data = [
            'summary' => [
                'total_projects' => $totalProjects,
                'total_users'    => $totalUsers,
                'under_review'   => $underReview,
                'last_updated'   => Carbon::now()->toDateTimeString(),
            ],
            'pipeline' => [
                'submitted'       => $submittedCount,
                'awaiting_fee'    => $awaitingFeeCount,
                'awaiting_review' => $awaitingReviewCount,
                'revision'        => $revisionCount,
                'certified'       => $certifiedCount,
            ],
        ];

        return $this->returnSuccess('เรียกดูข้อมูลสรุป Dashboard สำเร็จ', $data);
    }

    public function updateStatus(Request $request, $id)
    {
        $loginBy = $request->login_by;

        // ===== 1) ตรวจสอบ input =====
        if (empty($request->table)) {
            return $this->returnErrorData('กรุณาระบุ table', 400);
        }
        if (empty($request->field)) {
            return $this->returnErrorData('กรุณาระบุ field', 400);
        }
        if (!isset($request->status)) {
            return $this->returnErrorData('กรุณาระบุ status', 400);
        }

        $table  = $request->table;
        $field  = $request->field;          // เช่น approver_by
        $status = $request->status;
        $col    = $field . '_status';       // คอลัมน์จริงในตาราง

        // ===== 2) ตรวจสอบว่าตาราง / ฟิลด์มีอยู่จริง =====
        if (!\Schema::hasTable($table)) {
            return $this->returnErrorData('ไม่พบบนตารางที่ระบุ', 404);
        }

        if (!\Schema::hasColumn($table, $col)) {
            return $this->returnErrorData("ไม่พบฟิลด์ '{$col}' ในตาราง {$table}", 404);
        }

        DB::beginTransaction();

        try {

            // ===== 3) ตรวจสอบว่ามี id นี้อยู่จริง + เก็บค่าเดิมไว้ทำ log =====
            $record = DB::table($table)->where('id', $id)->first();
            if (!$record) {
                return $this->returnErrorData('ไม่พบข้อมูล id นี้ในตาราง', 404);
            }

            $oldValue = $record->{$col} ?? null;

            // ===== 4) อัปเดตสถานะในตารางเป้าหมาย =====
            DB::table($table)
                ->where('id', $id)
                ->update([
                    $col        => $status,
                    'updated_at'=> now()
                ]);

            // ===== 5) บันทึกประวัติลง update_status_logs =====
            DB::table('update_status_logs')->insert([
                'table_name'       => $table,
                'record_id'        => $id,
                'field_name'       => $col,
                'old_value'        => is_null($oldValue) ? null : (string) $oldValue,
                'new_value'        => (string) $status,
                'changed_by'       => $loginBy->id  ?? null,
                'changed_by_name'  => $loginBy->name ?? null,
                'remark'           => $request->remark ?? null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            DB::commit();

            return $this->returnSuccess('อัปเดตสถานะสำเร็จ', [
                'table'        => $table,
                'id'           => $id,
                'field'        => $field,
                $col           => $status,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();
            return $this->returnErrorData('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
        }
    }

    public function getStatusHistory($table, $id)
    {
        // ===== ตรวจสอบว่าตารางมีอยู่จริง =====
        if (!Schema::hasTable($table)) {
            return $this->returnErrorData("ไม่พบตาราง {$table}", 404);
        }

        // ===== ตรวจสอบว่าข้อมูล id มีอยู่ในตารางจริงไหม =====
        $exists = DB::table($table)->where('id', $id)->first();

        if (!$exists) {
            return $this->returnErrorData("ไม่พบข้อมูล id นี้ในตาราง {$table}", 404);
        }

        // ===== ดึงประวัติจาก status_update_logs =====
        $logs = DB::table('update_status_logs')
            ->where('table_name', $table)
            ->where('record_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return $this->returnSuccess('เรียกดูประวัติสำเร็จ', $logs);
    }



}

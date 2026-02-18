<?php

use App\Http\Controllers\CarsController;
use App\Http\Controllers\CharitableContributionController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\ControlledDocumentRequestsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DesignReviewController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSyncController;
use App\Http\Controllers\FeeSheetController;
use App\Http\Controllers\GiftHospitalityController;
use App\Http\Controllers\GiftHospitalityOfferingController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MainMenuController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuPermissionController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectDetailController;
use App\Http\Controllers\ProjectQualityAssurancePlanController;
use App\Http\Controllers\ProjectTypeController;
use App\Http\Controllers\ProposalContractReviewController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionsController;
use App\Http\Controllers\SingleSourceJustificationController;
use App\Http\Controllers\SubConsultantAssessmentsController;
use App\Http\Controllers\SubConsultantEvaluationController;
use App\Http\Controllers\SubConsultantsController;
use App\Http\Controllers\SupplierAssessmentsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierEvaluationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

//////////////////////////////////////////web no route group/////////////////////////////////////////////////////
//Login Admin
Route::post('/login', [LoginController::class, 'login']);
Route::post('/login_ldap', [LoginController::class, 'loginLdap']);

Route::post('/check_login', [LoginController::class, 'checkLogin']);

//user
Route::post('/create_admin', [UserController::class, 'createUserAdmin']);
Route::post('/forgot_password_user', [UserController::class, 'ForgotPasswordUser']);

// Permission
Route::resource('permission', PermissionController::class);
Route::post('/permission_page', [PermissionController::class, 'getPage']);
Route::get('/get_permission', [PermissionController::class, 'getList']);
// Route::post('/get_permisson_menu', [PermissionController::class, 'getPermissonMenu']);

// Permission (extra endpoints)
// Route::post('/permission_page', [PermissionController::class, 'PermissionPage']);
Route::get('/get_permisson_user', [PermissionController::class, 'getPermissonUser']);
Route::post('/get_permisson_menu', [PermissionController::class, 'getPermissonMenu']);

//Main Menu
Route::resource('main_menu', MainMenuController::class);
Route::get('/get_main_menu', [MainMenuController::class, 'getList']);
Route::post('/main_menu_page', [MainMenuController::class, 'getPage']);

//Menu
Route::resource('menu', MenuController::class);
Route::get('/get_menu', [MenuController::class, 'getList']);

//Menu Permission
Route::resource('menu_permission', MenuPermissionController::class);
Route::get('/get_menu_permission', [MenuPermissionController::class, 'getList']);
Route::post('checkAll', [MenuPermissionController::class, 'checkAll']);

//controller
Route::post('upload_images', [Controller::class, 'uploadImages']);
Route::post('upload_multiple_images', [Controller::class, 'uploadMultipleImages']);
Route::post('upload_file', [Controller::class, 'uploadFile']);
Route::post('upload_multiple_files', [Controller::class, 'uploadMultipleFiles']);
Route::post('upload_signature', [Controller::class, 'uploadSignature']);

//charitable_contributions
Route::resource('charitable_contributions', CharitableContributionController::class);
Route::post('/charitable_contributions_page', [CharitableContributionController::class, 'getPage']);
Route::get('/get_charitable_contributions', [CharitableContributionController::class, 'getList']);

//cars
Route::resource('cars', CarController::class);
Route::post('/cars_page', [CarController::class, 'getPage']);
Route::get('/get_cars', [CarController::class, 'getList']);

//gift_hospitalities
Route::resource('gift_hospitalities', GiftHospitalityController::class);
Route::post('/gift_hospitalities_page', [GiftHospitalityController::class, 'getPage']);
Route::get('/get_gift_hospitalities', [GiftHospitalityController::class, 'getList']);

//gift_hospitalities_offering
Route::resource('gift_hospitality_offerings', GiftHospitalityOfferingController::class);
Route::post('/gift_hospitality_offerings_page', [GiftHospitalityOfferingController::class, 'getPage']);
Route::get('/get_gift_hospitality_offerings', [GiftHospitalityOfferingController::class, 'getList']);

//purchase order
Route::resource('purchase_order', PurchaseOrderController::class);
Route::post('/purchase_order_page', [PurchaseOrderController::class, 'getPage']);
Route::get('/get_purchase_order', [PurchaseOrderController::class, 'getList']);
Route::get('/purchase-orders/next-number', [PurchaseOrderController::class, 'getNextNumber']);


//supplier_assessments
Route::resource('supplier_assessments', SupplierAssessmentsController::class);
Route::post('/supplier_assessments_page', [SupplierAssessmentsController::class, 'getPage']);
Route::get('/get_supplier_assessments', [SupplierAssessmentsController::class, 'getList']);

//supplier_evaluation
Route::resource('supplier_evaluation', SupplierEvaluationController::class);
Route::post('/supplier_evaluation_page', [SupplierEvaluationController::class, 'getPage']);
Route::get('/get_supplier_evaluation', [SupplierEvaluationController::class, 'getList']);

//single_source_justification
Route::resource('single_source_justification', SingleSourceJustificationController::class);
Route::post('/single_source_justification_page', [SingleSourceJustificationController::class, 'getPage']);
Route::get('/get_single_source_justification', [SingleSourceJustificationController::class, 'getList']);

//single_source_justification
Route::resource('proposal_contract_reviews', ProposalContractReviewController::class);
Route::post('/proposal_contract_reviews_page', [ProposalContractReviewController::class, 'getPage']);
Route::get('/get_proposal_contract_reviews', [ProposalContractReviewController::class, 'getList']);

//project_quality_assurance_plans
Route::resource('project_quality_assurance_plans', ProjectQualityAssurancePlanController::class);
Route::post('/project_quality_assurance_plans_page', [ProjectQualityAssurancePlanController::class, 'getPage']);
Route::get('/get_project_quality_assurance_plans', [ProjectQualityAssurancePlanController::class, 'getList']);

//controlled_document_requests
Route::resource('controlled_document_requests', ControlledDocumentRequestsController::class);
Route::post('/controlled_document_requests_page', [ControlledDocumentRequestsController::class, 'getPage']);
Route::get('/get_controlled_document_requests', [ControlledDocumentRequestsController::class, 'getList']);

//purchase_requisitions
Route::resource('purchase_requisitions', PurchaseRequisitionsController::class);
Route::post('/purchase_requisitions_page', [PurchaseRequisitionsController::class, 'getPage']);
Route::get('/get_purchase_requisitions', [PurchaseRequisitionsController::class, 'getList']);

//sub_consultant_evaluations
Route::resource('sub_consultant_evaluations', SubConsultantEvaluationController::class);
Route::post('/sub_consultant_evaluations_page', [SubConsultantEvaluationController::class, 'getPage']);
Route::get('/get_sub_consultant_evaluations', [SubConsultantEvaluationController::class, 'getList']);

//sub_consultant_assessments
Route::resource('sub_consultant_assessments', SubConsultantAssessmentsController::class);
Route::post('/sub_consultant_assessments_page', [SubConsultantAssessmentsController::class, 'getPage']);
Route::get('/get_sub_consultant_assessments', [SubConsultantAssessmentsController::class, 'getList']);



//masters
//consultants
Route::resource('sub_consultants', SubConsultantsController::class);
Route::post('/sub_consultants_page', [SubConsultantsController::class, 'getPage']);
Route::get('/get_sub_consultants', [SubConsultantsController::class, 'getList']);

//suppliers
Route::resource('suppliers', SupplierController::class);
Route::post('/suppliers_page', [SupplierController::class, 'getPage']);
Route::get('/get_suppliers', [SupplierController::class, 'getList']);

// employee sync
Route::post('/sync/employees', [EmployeeSyncController::class, 'sync']);

// employees (search + limit)
Route::get('/employees', [EmployeeController::class, 'getList']);

// committees
Route::resource('committees', CommitteeController::class);
Route::get('/get_committees', [CommitteeController::class, 'getList']);
Route::post('/committees_page', [CommitteeController::class, 'getPage']);

//user
Route::resource('user', UserController::class);
Route::get('/get_user', [UserController::class, 'getList']);
Route::post('/user_page', [UserController::class, 'getPage']);
Route::get('/user_profile', [UserController::class, 'getProfileUser']);
Route::post('/update_user', [UserController::class, 'update']);

Route::resource('user', UserController::class);
Route::put('/update_password_user/{id}', [UserController::class, 'updatePasswordUser']);
Route::put('/update_status/{id}', [Controller::class, 'updateStatus']);
Route::get('/update_status_logs/{table}/{id}', [Controller::class, 'getStatusHistory']);
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::group(['middleware' => 'checkjwt'], function () {


    Route::put('/reset_password_user/{id}', [UserController::class, 'ResetPasswordUser']);
    Route::post('/update_profile_user', [UserController::class, 'updateProfileUser']);
    Route::get('/get_profile_user', [UserController::class, 'getProfileUser']);
    Route::resource('orders', OrdersController::class);
    Route::get('/get_users_by_permission_id/{id}', [UserController::class, 'getListByPermission']);


});

Route::post('/upload_file', [UploadController::class, 'uploadFile']);

// Project Type
Route::post('/project_types_page', [ProjectTypeController::class, 'getPage']);
Route::post('/project_types', [ProjectTypeController::class, 'store']);
Route::get('/project_types/{id}', [ProjectTypeController::class, 'show']);
Route::put('/project_types/{id}', [ProjectTypeController::class, 'update']);
Route::delete('/project_types/{id}', [ProjectTypeController::class, 'destroy']);
Route::get('/get_project_types', [ProjectTypeController::class, 'getAll']);

// Project Detail
Route::post('/project_details_page', [ProjectDetailController::class, 'getPage']);
Route::post('/project_details', [ProjectDetailController::class, 'store']);
Route::get('/project_details/{id}', [ProjectDetailController::class, 'show']);
Route::put('/project_details/{id}', [ProjectDetailController::class, 'update']);
Route::delete('/project_details/{id}', [ProjectDetailController::class, 'destroy']);
Route::get('/get_project_details', [ProjectDetailController::class, 'getAll']);

// Discipline
Route::post('/discipline_page', [DisciplineController::class, 'getPage']);
Route::post('/disciplines', [DisciplineController::class, 'store']);
Route::get('/disciplines/{id}', [DisciplineController::class, 'show']);
Route::put('/disciplines/{id}', [DisciplineController::class, 'update']);
Route::delete('/disciplines/{id}', [DisciplineController::class, 'destroy']);
Route::get('/get_disciplines', [DisciplineController::class, 'getAll']);

// Design Review
Route::get('/pages/design_review_page', [DesignReviewController::class, 'getPage']);
Route::post('/design_reviews_list', [DesignReviewController::class, 'getList']);
Route::get('/design_reviews/{id}', [DesignReviewController::class, 'getById']);
Route::post('/design_reviews', [DesignReviewController::class, 'store']);
Route::put('/design_reviews/{id}', [DesignReviewController::class, 'update']);

// Fee sheets
Route::post('/fee-sheets', [FeeSheetController::class, 'store']);
Route::get('/fee_sheets/{id}', [FeeSheetController::class, 'show']);
Route::delete('/fee_sheets/{id}', [FeeSheetController::class, 'destroy']);
Route::get('/get_fee-sheets', [FeeSheetController::class, 'index']);
Route::post('/fee_sheets_page', [FeeSheetController::class, 'page']);


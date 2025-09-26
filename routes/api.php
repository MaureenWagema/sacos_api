<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\syncController;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\agentController;
use App\Http\Controllers\loginController;
use App\Http\Controllers\clientController;
use App\Http\Controllers\premCalController;
use App\Http\Controllers\collectionsController;

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

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/

//Route::apiResource('/employee', 'EmployeeController')->middleware('auth:api');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::group(['middleware' => ['client']], function () {
    Route::get('/test-database-connection', [Controller::class, 'testDatabaseConnection']);
    Route::get('client/getClientDetails', [clientController::class, 'getClientDetails']);
    Route::post('calc/LifeAnidaso', [premCalController::class, 'LifeAnidaso']);
    Route::post('calc/ITCAnidaso', [premCalController::class, 'ITCAnidaso']);
    Route::post('sync/newProposal', [syncController::class, 'synProposal']);
    //Route::post('collections/makePremiumPayment', [collectionsController::class, 'updateHubtel']);
    Route::get('collections/getPaymentHistory', [collectionsController::class, 'getPaymentHistory']);
    Route::get('sync/getPolicyDetails', [USSDController::class, 'getPolicyDetails']);
    Route::get('sync/getProposalDetails', [USSDController::class, 'getProposalDetails']);

    Route::get('agents/getAllAgents', [agentController::class, 'getRecruitedBy']);

    //TODO - 
    //1.updateBeneficiary
    //2.pushpayments
    //3.push claim..

    //test
    Route::post('collections/makePremiumPayment', [USSDController::class, 'updateConsortium']);

    Route::post('itc/setAllBeneficiaries', [USSDController::class, 'setAllBeneficiaries']);
    Route::get('itc/getAllBeneficiaries', [USSDController::class, 'getAllBeneficiaries']);
    Route::post('itc/makeAClaim', [USSDController::class, 'makeAClaim']);
    Route::get('itc/getAClaim', [USSDController::class, 'getAClaim']);


});
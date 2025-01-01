<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('login', [AuthController::class,'login']);
    Route::post('register',  [AuthController::class,'register']);
    Route::post('logout',  [AuthController::class,'logout']);
    Route::post('refresh',  [AuthController::class,'refresh']);
    Route::post('me',  [AuthController::class,'me']);
    Route::post('updateProfile',  [AuthController::class,'updateProfile']);
    Route::post('updatePassword',  [AuthController::class,'updatePassword']);
});

Route::group([

    'middleware' => 'api',
    'prefix' => 'admin'

], function ($router) {
    Route::get('allUsers',  [AuthController::class,'getAllUsers'])->middleware('role:admin');
    Route::post('assignRole', [AdminController::class, 'assignRole']);
});
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
    Route::patch('updateProfile',  [AuthController::class,'updateProfile']);
    Route::patch('updatePassword',  [AuthController::class,'updatePassword']);
});

Route::group([

    'middleware' => 'api',
    'prefix' => 'admin'

], function ($router) {
    Route::get('allUsers',  [AuthController::class,'getAllUsers'])->middleware('role:admin');
    Route::post('assignRole', [AdminController::class, 'assignRole']);
    Route::get('getAllStudents', [AdminController::class, 'getAllStudents']);
    Route::get('getAllProfessors', [AdminController::class, 'getAllProfessors']);
    Route::post('createCourse', [AdminController::class, 'createCourse']);
    Route::get('getAllCourses', [AdminController::class, 'getAllCourses']);
    Route::post('assignCourseToProfessor', [AdminController::class, 'assignCourseToProfessor']);
    Route::post('createUserAccount', [AdminController::class, 'createUserAccount']);
    Route::delete('deleteUserAccount', [AdminController::class, 'deleteUserAccount']);
    Route::patch('updateUserAccount', [AdminController::class, 'updateUserAccount']);
});
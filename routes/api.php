<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\ProfessorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;

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

    Route::post('login', [AuthController::class, 'login']);
    Route::post('register',  [AuthController::class, 'register']);
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::post('refresh',  [AuthController::class, 'refresh']);
    Route::post('me',  [AuthController::class, 'me']);
    Route::patch('updateProfile',  [UserController::class, 'updateProfile']);
    Route::patch('updatePassword',  [UserController::class, 'updatePassword']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'admin'
], function ($router) {
    Route::get('allUsers',  [UserController::class, 'getAllUsers'])->middleware('role:admin');
    Route::post('assignRole', [AdminController::class, 'assignRole']);
    Route::get('getAllStudents', [AdminController::class, 'getAllStudents']);
    Route::get('getAllProfessors', [AdminController::class, 'getAllProfessors']);
    Route::post('createCourse', [AdminController::class, 'createCourse']);
    Route::patch('updateCourse', [AdminController::class, 'updateCourse']);
    Route::delete('deleteCourse', [AdminController::class, 'deleteCourse']);
    Route::get('getAllCourses', [AdminController::class, 'getAllCourses']);
    Route::post('assignCourseToProfessor', [AdminController::class, 'assignCourseToProfessor']);
    Route::post('createUserAccount', [AdminController::class, 'createUserAccount']);
    Route::delete('deleteUserAccount', [AdminController::class, 'deleteUserAccount']);
    Route::patch('updateUserAccount', [AdminController::class, 'updateUserAccount']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'course'
], function ($router) {
    Route::post('registerCourse', [CourseRegistrationController::class, 'registerCourse']);
    Route::get('{courseID}/students', [CourseController::class, 'getStudentsInCourse']);
    Route::get('{courseID}', [CourseController::class, 'getCourseDetails']);
    Route::get('', [StudentController::class, 'getAllCoursesWithProfessors']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'student'
], function ($router) {
    Route::get('viewRegisteredCourses', [StudentController::class, 'viewRegisteredCourses']);
    Route::post('viewCourseMaterials', [StudentController::class, 'viewCourseMaterials']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'professor'
], function ($router) {
    Route::get('viewRegisteredCourses', [ProfessorController::class, 'viewRegisteredCourses']);
    Route::post('uploadCourseMaterial', [ProfessorController::class, 'uploadCourseMaterial']);
    Route::delete('deleteCourseMaterial', [ProfessorController::class, 'deleteCourseMaterial']);
    Route::patch('updateCourseMaterial', [ProfessorController::class, 'updateCourseMaterial']);
});
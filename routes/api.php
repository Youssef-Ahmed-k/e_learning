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

    Route::post('login', [AuthController::class, 'login'])->middleware('check.suspension');
    Route::post('register',  [AuthController::class, 'register']);
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::post('refresh',  [AuthController::class, 'refresh']);
    Route::post('profile',  [AuthController::class, 'me']);
    Route::patch('profile/update',  [UserController::class, 'updateProfile']);
    Route::patch('password/update',  [UserController::class, 'updatePassword']);
    Route::post('profile/upload-profile-picture', [UserController::class, 'uploadProfilePicture']);
    Route::delete('profile/delete-profile-picture', [UserController::class, 'deleteProfilePicture']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'admin'
], function ($router) {
    Route::get('users',  [UserController::class, 'getAllUsers']);
    Route::post('users/assign-role', [AdminController::class, 'assignRole']);
    Route::get('students', [AdminController::class, 'getAllStudents']);
    Route::get('professors', [AdminController::class, 'getAllProfessors']);

    Route::post('courses', [CourseController::class, 'createCourse']);
    Route::patch('courses/{course}', [CourseController::class, 'updateCourse']);
    Route::delete('courses/{course}', [CourseController::class, 'deleteCourse']);
    Route::get('allCourses', [CourseController::class, 'getAllCourses']);
    Route::post('courses/assign-professor', [CourseController::class, 'assignCourseToProfessor']);
    Route::get('courses/with-professor', [CourseController::class, 'getAllCoursesWithProfessorsForAdmin']);

    Route::post('users', [AdminController::class, 'createUserAccount']);
    Route::delete('users/{user}', [AdminController::class, 'deleteUserAccount']);
    Route::patch('users/{user}', [AdminController::class, 'updateUserAccount']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'course'
], function ($router) {
    Route::post('registerCourse', [CourseRegistrationController::class, 'registerCourse']);
    Route::get('{courseID}/students', [CourseController::class, 'getStudentsInCourse']);
    Route::get('{courseID}', [CourseController::class, 'getCourseDetails']);
    Route::get('', [CourseController::class, 'getAllCoursesWithProfessors']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'student'
], function ($router) {
    Route::get('courses', [StudentController::class, 'viewRegisteredCourses']);
    Route::get('materials/{courseID}', [StudentController::class, 'viewCourseMaterials']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'professor'
], function ($router) {
    Route::get('courses', [ProfessorController::class, 'viewRegisteredCourses']);
    Route::post('materials', [ProfessorController::class, 'uploadCourseMaterial']);
    Route::delete('materials/{material_id}', [ProfessorController::class, 'deleteCourseMaterial']);
    Route::patch('materials/{material_id}', [ProfessorController::class, 'updateCourseMaterial']);
    Route::get('/students/suspend', [ProfessorController::class, 'viewSuspendedStudents']);
    Route::post('/students/{id}/suspend', [ProfessorController::class, 'suspendStudent']);
    Route::post('/students/{id}/unsuspend', [ProfessorController::class, 'unsuspendStudent']);
});

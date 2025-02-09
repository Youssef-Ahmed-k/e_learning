<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\ProfessorController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\NotificationController;

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
    Route::get('/students/suspend', [UserController::class, 'viewSuspendedStudents']);
    Route::post('/students/{id}/suspend', [UserController::class, 'suspendStudent']);
    Route::post('/students/{id}/unsuspend', [UserController::class, 'unsuspendStudent']);
    Route::post('forgot-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
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

    Route::get('statistics', [AdminController::class, 'getStatistics']);
    Route::get('recent-activities', [AdminController::class, 'getRecentActivities']);

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
    Route::get('getCoursesWithResults', [ProfessorController::class, 'getCoursesWithResults']);
    Route::post('materials', [ProfessorController::class, 'uploadCourseMaterial']);
    Route::delete('materials/{material_id}', [ProfessorController::class, 'deleteCourseMaterial']);
    Route::patch('materials/{material_id}', [ProfessorController::class, 'updateCourseMaterial']);
    Route::get('materials/{courseID}', [ProfessorController::class, 'getCourseMaterials']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'quiz'
], function ($router) {
    Route::post('create-quiz', [QuizController::class, 'createQuiz']);
    Route::patch('update-quiz/{id}', [QuizController::class, 'updateQuiz']);
    Route::delete('delete-quiz/{id}', [QuizController::class, 'deleteQuiz']);
    Route::post('add-question', [QuestionController::class, 'addQuestion']);
    Route::patch('update-question/{id}', [QuestionController::class, 'updateQuestion']);
    Route::delete('delete-question/{id}', [QuestionController::class, 'deleteQuestion']);
    Route::get('get-questions/{id}', [QuestionController::class, 'getQuizQuestions']);
    Route::get('course-quizzes/{courseId}', [QuizController::class, 'getCourseQuizzes']);
    Route::get('get-quiz/{id}', [QuizController::class, 'getQuiz']);
    Route::get('get-quizzes', [QuizController::class, 'getAllQuizzes']);
    Route::get('student-quizzes', [QuizController::class, 'getStudentQuizzes']);
    Route::get('start-quiz/{id}', [QuizController::class, 'startQuiz']);
    Route::post('submit-quiz/{id}', [QuizController::class, 'submitQuiz']);
    Route::get('getQuizResult/{id}',[QuizController::class, 'getQuizResult']);
    Route::get('getQuizScores/{id}',[QuizController::class, 'getQuizScores']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'notification'
], function ($router) {
    Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);
    Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

});
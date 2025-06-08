<?php

use App\Models\StudentQuiz;
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
use App\Http\Controllers\QuizResult;
use App\Http\Controllers\QuizResultController;
use App\Http\Controllers\QuizSubmissionController;

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

// Authentication Routes
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
    Route::post('forgot-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// User Management Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'users'
], function ($router) {
    Route::post('profile/upload-profile-picture', [UserController::class, 'uploadProfilePicture']);
    Route::delete('profile/delete-profile-picture', [UserController::class, 'deleteProfilePicture']);
    Route::get('/students/suspend', [UserController::class, 'viewSuspendedStudents']);
    Route::post('/students/{id}/suspend', [UserController::class, 'suspendStudent']);
    Route::post('/students/{id}/unsuspend', [UserController::class, 'unsuspendStudent']);
});

// Admin Management Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'admin'
], function ($router) {
    Route::get('users',  [AdminController::class, 'getAllUsers']);
    Route::post('users/assign-role', [AdminController::class, 'assignRole']);
    Route::get('students', [AdminController::class, 'getAllStudents']);
    Route::get('professors', [AdminController::class, 'getAllProfessors']);
    Route::post('users', [AdminController::class, 'createUserAccount']);
    Route::delete('users/{user}', [AdminController::class, 'deleteUserAccount']);
    Route::patch('users/{user}', [AdminController::class, 'updateUserAccount']);

    Route::get('statistics', [AdminController::class, 'getStatistics']);
    Route::get('recent-activities', [AdminController::class, 'getRecentActivities']);
});

// Course Management Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'courses'
], function ($router) {
    Route::post('', [CourseController::class, 'createCourse']);
    Route::patch('{course}', [CourseController::class, 'updateCourse']);
    Route::delete('{course}', [CourseController::class, 'deleteCourse']);
    Route::get('', [CourseController::class, 'getAllCourses']);
    Route::post('assign-professor', [CourseController::class, 'assignCourseToProfessor']);
    Route::get('with-professor', [CourseController::class, 'getAllCoursesWithProfessorsForAdmin']);

    Route::post('register', [CourseRegistrationController::class, 'registerCourses']);
    Route::post('unregister', [CourseRegistrationController::class, 'unregisterCourses']);
    Route::get('{courseID}/students', [CourseController::class, 'getStudentsInCourse']);
    Route::get('{courseID}', [CourseController::class, 'getCourseDetails']);
    Route::get('', [CourseController::class, 'getAllCoursesWithProfessors']);
});

// Student Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'students'
], function ($router) {
    Route::get('courses', [StudentController::class, 'viewRegisteredCourses']);
    Route::get('materials/{courseID}', [StudentController::class, 'viewCourseMaterials']);
});

// Professor Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'professors'
], function ($router) {
    Route::get('courses', [ProfessorController::class, 'viewRegisteredCourses']);
    Route::get('courses-with-results', [ProfessorController::class, 'getCoursesWithResults']);
    Route::post('materials', [ProfessorController::class, 'uploadCourseMaterial']);
    Route::delete('materials/{material_id}', [ProfessorController::class, 'deleteCourseMaterial']);
    Route::patch('materials/{material_id}', [ProfessorController::class, 'updateCourseMaterial']);
    Route::get('materials/{courseID}', [ProfessorController::class, 'getCourseMaterials']);
    Route::get('quizzes/{quizId}/cheaters', [ProfessorController::class, 'getHighCheatingScores']);
    Route::get('/quizzes/{quizId}/{studentId}/cheating-logs', [ProfessorController::class, 'getCheatingLogs']);
    Route::post('/quizzes/{quizId}/results/{studentId}/edit', [ProfessorController::class, 'resetCheatingScore']);
    Route::get('/quizzes/{quizId}/results/{studentId}', [ProfessorController::class, 'getStudentAnswers']);
});

// Quiz Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'quizzes'
], function ($router) {
    Route::get('ended-with-results', [QuizResultController::class, 'getEndedQuizzesWithResults']);
    Route::get('student-quizzes', [StudentController::class, 'getAvailableQuizzes']);
    Route::get('quizzes-results', [QuizResultController::class, 'getAvailableQuizzesWithResults']);
    Route::get('submitted', [StudentController::class, 'getSubmittedQuizzes']);

    Route::get('course/{courseId}', [QuizController::class, 'getCourseQuizzes']);
    Route::post('start/{id}', [StudentController::class, 'startQuiz']);
    Route::post('end/{id}', [StudentController::class, 'endQuiz']);
    Route::post('update-cheating-score', [StudentController::class, 'updateCheatingScore']);
    Route::get('result/{id}', [QuizResultController::class, 'getQuizResult']);
    Route::get('scores/{id}', [QuizResultController::class, 'getQuizScores']);
    Route::get('correct-answer/{id}', [StudentController::class, 'compareStudentAnswers']);

    Route::post('', [QuizController::class, 'createQuiz']);
    Route::get('', [QuizController::class, 'getAllQuizzes']);
    Route::get('{id}', [QuizController::class, 'getQuiz']);
    Route::patch('{id}', [QuizController::class, 'updateQuiz']);
    Route::delete('{id}', [QuizController::class, 'deleteQuiz']);

    Route::post('submit/{id}', [QuizSubmissionController::class, 'submitQuiz']);
});

// Question Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'questions'
], function ($router) {
    Route::post('', [QuestionController::class, 'addQuestion']);
    Route::patch('{id}', [QuestionController::class, 'updateQuestion']);
    Route::delete('{id}', [QuestionController::class, 'deleteQuestion']);
    Route::get('quiz/{id}', [QuestionController::class, 'getQuizQuestions']);
});

// Notification Routes
Route::group([
    'middleware' => 'api',
    'prefix' => 'notifications'
], function ($router) {
    Route::get('', [NotificationController::class, 'getUserNotifications']);
    Route::get('unread', [NotificationController::class, 'getUnreadNotifications']);
    Route::post('read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('{id}', [NotificationController::class, 'deleteNotification']);
    Route::delete('', [NotificationController::class, 'deleteAllNotifications']);
    Route::get('unread-count', [NotificationController::class, 'getUnreadNotificationCount']);
});

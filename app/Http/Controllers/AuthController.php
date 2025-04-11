<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Register;
use App\Http\Requests\UpdatePassword;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfile;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Hash;
use App\Notifications\ResetPasswordverificationNOtification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use phpDocumentor\Reflection\PseudoTypes\True_;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $otp;
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $otp = new Otp();
        $this->otp = $otp;
        $this->middleware('auth:api', ['except' => ['login', 'register', 'sendResetLinkEmail', 'resetPassword']]);
    }

    public function register(Register $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $user = User::create($data);

            // Commit transaction to ensure user is in database
            DB::commit();

            $capturedImages = $request->input('captured_images', []);
            if (count($capturedImages) < 3) {
                return response()->json(['message' => 'At least 3 images are required'], 422);
            }

            // Send to FastAPI with proper form data
            $response = Http::post('http://localhost:8001/register', [
                'user_id' => $user->id,
                'images' => $capturedImages,
            ]);

            if ($response->failed()) {
                // Consider removing the user if FastAPI fails
                User::destroy($user->id);    
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to send images to face API',
                    'error' => $response->json()['detail'] ?? $response->body()
                ], 500);
            }

            DB::commit();

            return response()->json([
                'message' => 'Registration successful',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Get a JWT via given credentials. 
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->isSuspended()) {
                Auth::logout();
                return response()->json(['message' => 'Your account is suspended. Please contact the administrator.'], 403);
            }

            if (! $token = auth()->attempt($credentials)) {
                return response()->json(['message' => 'Email or password is incorrect'], 401);
            }

            $user = auth()->user();

            return response()->json([
                'message' => 'Login successful',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() * 60,
                ],
            ]);
        }

        return response()->json(['message' => 'Email or password is incorrect'], 401);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
    public function sendResetLinkEmail(ForgotPasswordRequest $request)
    {
        try {
            $input = $request->only('email');
            $user = User::where('email', $input['email'])->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->notify(new ResetPasswordverificationNOtification());
            return response()->json(['message' => 'Reset password link sent to your email.']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $otp = $this->otp->validate($request->email, $request->otp);

            if (!$otp->status) {
                return response()->json(['message' => 'Invalid OTP provided'], 400);
            }

            $user = User::where('email', $request->email)->first();
            $user->password = $request->password;
            $user->save();

            return response()->json(['message' => 'Password has been successfully changed']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
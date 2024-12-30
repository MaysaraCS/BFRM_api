<?php 

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function Register(Request $request)
    {
        try {
            // Validation
            $validateUser = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'password' => [
                    'required',
                    'string',
                    'min:8',                // Minimum of 8 characters
                    'regex:/[a-z]/',        // At least one lowercase letter
                    'regex:/[A-Z]/',        // At least one uppercase letter
                    'regex:/[0-9]/',        // At least one digit
                    'regex:/[@$!%*?&]/',    // At least one special character
                    'confirmed',            // Matches password_confirmation
                ],
                'role' => 'required|in:merchant,customer',
            ], [
                'password.min' => 'Password must be at least 8 characters.',
                'password.regex' => 'Password must include uppercase, lowercase, numbers, and special characters.',
                'password.confirmed' => 'Passwords do not match.',
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            // Generate OTP
            $otp = rand(100000, 999999);

            // Create User
            $user = User::create([
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'otp' => $otp,
            ]);
            
            // Send OTP Email
            Mail::to($user->email)->send(new \App\Mail\OtpMail($otp));

            return response()->json([
                'status' => true,
                'message' => 'User Registered Successfully. Check your email for OTP.',
                'token' => $user->createToken("API TOKEN", [$user->role])->plainTextToken,
                'data' => [
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $user = User::where('email', strtolower($request->email))->where('otp', $request->otp)->first();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Invalid OTP'], 401);
        }

        $user->is_verified = true;
        $user->otp = null; // Clear OTP after verification
        $user->save();

        return response()->json(['status' => true, 'message' => 'Email verified successfully']);
    }

    public function Login(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            if (!Auth::attempt(['email' => strtolower($request->email), 'password' => $request->password])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email & Password do not match our records.',
                ], 401);
            }

            $user = User::where('email', strtolower($request->email))->first();

            if (!$user->is_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please verify your email before logging in.',
                ], 403);
            }

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN", [$user->role])->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }
    public function Logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}


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
            // Validated
            $validateUser = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|confirmed',
                'role' => 'required|in:merchant,customer',
            ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            $otp = rand(100000, 999999);
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'otp' => $otp,
            ]);
            
            Mail::to($user->email)->send(new \App\Mail\OtpMail($otp));

            return response()->json([
                'status' => true,
                'message' => 'User Registered Successfully. Check your email for OTP',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'data' => [
                    'email' => $user->email,
                    'password' => $request->password, 
                    'role' => $user->role,
            ],
        ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();

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

            if (!Auth::attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email & Password do not match our records.',
                ], 401);
            }

            $user = User::where('email', $request->email)->first();

            if(!$user->is_verified){
                    return response()->json([
                    'status' => false,
                    'message' => 'Please verify your email before logging in.',
                ], 403);
            }

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}

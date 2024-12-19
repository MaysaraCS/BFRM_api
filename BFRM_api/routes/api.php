<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('register', [UserController::class, 'Register']);
Route::post('verify-otp', [UserController::class, 'verifyOtp']);
Route::post('login', [UserController::class, 'Login']);
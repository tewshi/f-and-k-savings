<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [AuthController::class, 'register']);

Route::post('/sanctum/token', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user()->load('wallet'));
    });

    Route::get('/wallet', function (Request $request) {
        return response()->json($request->user()->wallet);
    });

    Route::get('/wallet/payments', function (Request $request) {
        return response()->json($request->user()->wallet->payments);
    });

    Route::post('/wallet', [WalletController::class, 'store']);
});


// email verification links
Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = User::find($id);
    Auth::guard('api')->setUser($user);
    Auth::loginUsingId($id);

    $expires = request()->query('expires');
    $signature = request()->query('signature');

    $link = url("/api/email/verify-account/{$id}/{$hash}?expires={$expires}&signature={$signature}");

    return redirect($link);
})->middleware(['signed'])->name('verification.pre.verify');

Route::get('/email/verify-account/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return response()->json(['message' => 'Email verified']);
})->middleware(['auth:api', 'signed'])->name('verification.verify');


Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return response()->json(['message' => 'Email Verification link sent!']);
})->middleware(['auth:api', 'throttle:6,1'])->name('verification.send');

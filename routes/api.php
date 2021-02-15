<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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

Route::post('/register', function (Request $request) {
    $data = $request->validate([
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'name' => 'required|min:2',
    ]);

    $data['password'] = Hash::make($data['password']);
    $user = User::create($data);

    if (!$user) {
        throw ValidationException::withMessages([
            'error' => ['Sorry, we could not create your account at this time.'],
        ]);
    }

     event(new Registered($user));

    return response()->json(['message' => 'Account created!']);
});


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


Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return $user->createToken($request->device_name)->plainTextToken;
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('wallet');
    });

    Route::get('/wallet', function (Request $request) {
        return $request->user()->wallet;
    });

    Route::post('/wallet', function (Request $request) {
        return $request->user()->wallet;
    });
});

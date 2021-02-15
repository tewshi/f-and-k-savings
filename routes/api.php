<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPayment;
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

    $wallet = Wallet::create([]);

    $wallet->user()->associate($user)->save();

    event(new Registered($user));

    return response()->json(['message' => 'Account created!'], 201);
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

    return response()->json($user->createToken($request->device_name)->plainTextToken);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user()->load('wallet'));
    });

    Route::get('/wallet', function (Request $request) {
        return response()->json($request->user()->wallet);
    });

    Route::post('/wallet', function (Request $request) {
        $form_data = $request->validate([
            'email' => 'sometimes|email',
            'reference' => 'required',
            'amount' => 'required|int|min:1000|max:10000000',
        ]);

        $ref = $form_data['reference'];
        $email = $form_data['email'];
        $requested_amount = $form_data['amount'];

        $payment_exists = WalletPayment::where('reference', $ref)->exists();

        if ($payment_exists) {
            return response()->json(['message' => 'Payment already verified']);
        }

        try {
            $client = new GuzzleHttp\Client;

            $verification_response = $client->get(
                "https://api.paystack.co/transaction/verify/{$ref}",
                ['headers' => ['Authorization' => 'Bearer ' . env('PAYSTACK_SEC_KEY')]]
            );
            $code = $verification_response->getStatusCode();

            $response = json_decode($verification_response->getBody());
            $data = $response->data;
            $amount = $data->amount / 100;
            $status = $data->status;
            $message = $data->message;
            $fees = $data->fees;

            if ($code !== 200) {
                return response()->json(['message' => $message ?? 'An unexpected error occurred']);
            }

            if ($status !== 'success') {
                return response()->json(['message' => $message ?? 'Payment could not be verified'], 422);
            } else if ($amount !== $requested_amount) {
                return response()->json(['message' => "Paid amount ({$requested_amount}) does not match with verified amount"], 422);
            }

            $user = $request->user();
            $wallet = $user->wallet;
            $wallet->deposit($amount);

            $funded_wallet = $wallet;

            if ($email) {
                $funded_wallet = User::where('email', $email)->wallet;
            }

            $payment = WalletPayment::create([
                'reference' => $ref,
                'amount' => $data->amount,
                'fees' => $fees,
                'user_id' => $user->id,
                'wallet_id' => $funded_wallet->id,
            ]);

            return response()->json(['message' => 'Payment verified, wallet credited', 'wallet' => $wallet, 'payment' => $payment]);

        } catch (\Exception $e) {
            $error_message = explode("response:\n", $e->getMessage(), 2);
            if (count($error_message) == 2) {
                $error = json_decode($error_message[1]);
                return response()->json(['message' => $error->message], $e->getCode());
            }
            return response()->json(['message' => 'An unexpected error occurred', 'm' => $e->getMessage()], 400);
        }
    });
});

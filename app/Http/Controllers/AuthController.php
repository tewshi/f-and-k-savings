<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    function register(Request $request) {
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
    }

    function login(Request $request) {
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
    }
}

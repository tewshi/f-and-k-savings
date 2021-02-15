<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletPayment;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{

    /**
     * Create wallet transaction
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $form_data = $request->validate([
            'email' => 'sometimes|email',
            'reference' => 'required',
            'amount' => 'required|int|min:1000|max:10000000',
        ]);

        $ref = $form_data['reference'];
        $email = $form_data['email'] ?? '';
        $requested_amount = $form_data['amount'];

        // check if payment reference has already been used
        $payment_exists = WalletPayment::where('reference', $ref)->exists();

        if ($payment_exists) {
            return response()->json(['message' => 'Payment already verified']);
        }

        try {
            $client = new Client();

            $verification_response = $client->get(
                "https://api.paystack.co/transaction/verify/{$ref}",
                ['headers' => ['Authorization' => 'Bearer ' . env('PAYSTACK_SEC_KEY')]]
            );
            $code = $verification_response->getStatusCode();

            $response = json_decode($verification_response->getBody());
            $data = $response->data;
            $amount = $data->amount / 100; // get naira value
            $status = $data->status;
            $message = $data->message;
            $fees = $data->fees / 100; // get naira value

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

            $funded_wallet = $wallet;

            if ($email) {
                $for_user = User::where('email', $email)->first();
                // return if the user does not exist
                if (!$for_user) {
                    return response()->json(['message' => 'Payment could not be completed, user not found'], 404);
                }
                $funded_wallet = $for_user->wallet;
            }

            $funded_wallet->deposit($amount);

            $payment = WalletPayment::create([
                'reference' => $ref,
                'amount' => $amount,
                'fees' => $fees,
                'status' => $status,
            ]);

            $payment->user()->associate($user);
            $payment->wallet()->associate($funded_wallet)->save();

            return response()->json(['message' => 'Payment verified, wallet credited']);

        } catch (GuzzleException $e) {
            $error_message = explode("response:\n", $e->getMessage(), 2);
            if (count($error_message) == 2) {
                $error = json_decode($error_message[1]);
                return response()->json(['message' => $error->message], $e->getCode());
            }
            return response()->json(['message' => 'An unexpected error occurred', 'm' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'An unexpected error occurred', 'm' => $e->getMessage()], 400);
        }
    }

}

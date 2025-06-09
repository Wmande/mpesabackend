<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use MongoDB\Client as Mongo;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function stkPush(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1'
        ]);

        $phone = $this->formatPhoneNumber($request->input('phone'));
        $amount = $request->input('amount');
        $timestamp = now()->format('YmdHis');

        $shortcode = env('MPESA_SHORTCODE');
        $passkey = env('MPESA_PASSKEY');
        $callbackUrl = env('MPESA_CALLBACK_URL');

        $password = base64_encode($shortcode . $passkey . $timestamp);

        // Step 1: Get access token
        $authResponse = Http::withBasicAuth(
            env('MPESA_CONSUMER_KEY'),
            env('MPESA_CONSUMER_SECRET')
        )->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

        if (!$authResponse->ok()) {
            return response()->json(['error' => 'Failed to obtain access token', 'details' => $authResponse->body()], 500);
        }

        $access_token = $authResponse['access_token'];

        // Step 2: Send STK Push
        $stkResponse = Http::withToken($access_token)->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
            "BusinessShortCode" => $shortcode,
            "Password" => $password,
            "Timestamp" => $timestamp,
            "TransactionType" => "CustomerPayBillOnline",
            "Amount" => $amount,
            "PartyA" => $phone,
            "PartyB" => $shortcode,
            "PhoneNumber" => $phone,
            "CallBackURL" => $callbackUrl,
            "AccountReference" => "VIDENCE",
            "TransactionDesc" => "Payment"
        ]);

        // Return the actual M-Pesa response to the frontend
        if ($stkResponse->successful()) {
            return response()->json($stkResponse->json(), 200);
        } else {
            return response()->json([
                'error' => 'STK Push failed',
                'details' => $stkResponse->json(),
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            $mongo = new Mongo(env('MONGO_DB_URI'));
            $collection = $mongo->Mpesa->vidence;

            $collection->insertOne([
                'body' => $request->all(),
                'received_at' => now()
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('M-Pesa callback error: ' . $e->getMessage());
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to log callback'], 500);
        }
    }

    private function formatPhoneNumber($phone)
    {
        // Normalize phone number (e.g. 0712xxxxxx -> 254712xxxxxx)
        $phone = preg_replace('/\s+/', '', $phone);

        if (preg_match('/^0/', $phone)) {
            return '254' . substr($phone, 1);
        }

        if (preg_match('/^\+254/', $phone)) {
            return substr($phone, 1); // Remove the "+"
        }

        return $phone;
    }
}

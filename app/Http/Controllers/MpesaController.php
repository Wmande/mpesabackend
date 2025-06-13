<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function stkPush(Request $request)
    {
        // Validate request input
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^2547\d{8}$/'],
            'amount' => ['required', 'numeric', 'min:1']
        ]);

        $phone = $request->input('phone');
        $amount = (int) $request->input('amount');
        $timestamp = now()->format('YmdHis');

        // Load sensitive env values
        $shortcode = env('MPESA_SHORTCODE');
        $passkey = env('MPESA_PASSKEY');
        $callbackUrl = env('MPESA_CALLBACK_URL');
        $consumerKey = env('MPESA_CONSUMER_KEY');
        $consumerSecret = env('MPESA_CONSUMER_SECRET');

        if (!$shortcode || !$passkey || !$callbackUrl || !$consumerKey || !$consumerSecret) {
            return response()->json([
                'ResponseCode' => '1',
                'errorMessage' => 'Server configuration error. Please contact support.'
            ], 500);
        }

        $password = base64_encode($shortcode . $passkey . $timestamp);

        try {
            // Step 1: Get Access Token
            $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(30)
                ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

            if (!$authResponse->ok()) {
                return response()->json([
                    'ResponseCode' => '1',
                    'errorMessage' => 'Failed to obtain access token: ' . $authResponse->body()
                ], 500);
            }

            $access_token = $authResponse['access_token'];

            // Step 2: Send STK Push
            $stkResponse = Http::withToken($access_token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $callbackUrl,
                    'AccountReference' => 'VIDENCE',
                    'TransactionDesc' => 'Payment'
                ]);

            if ($stkResponse->successful()) {
                $responseData = $stkResponse->json();

                // Save STK request metadata
                DB::connection('mongodb')->collection('stk_requests')->insert([
                    'phone' => $phone,
                    'amount' => $amount,
                    'MerchantRequestID' => $responseData['MerchantRequestID'] ?? null,
                    'CheckoutRequestID' => $responseData['CheckoutRequestID'] ?? null,
                    'status' => 'pending',
                    'requested_at' => now(),
                ]);

                return response()->json([
                    'ResponseCode' => $responseData['ResponseCode'] ?? '0',
                    'CustomerMessage' => $responseData['CustomerMessage'] ?? 'STK Push initiated.',
                    'MerchantRequestID' => $responseData['MerchantRequestID'] ?? null,
                    'CheckoutRequestID' => $responseData['CheckoutRequestID'] ?? null
                ], 200);
            }

            return response()->json([
                'ResponseCode' => '1',
                'errorMessage' => 'STK Push failed: ' . json_encode($stkResponse->json())
            ], 500);
        } catch (\Exception $e) {
            Log::error('STK Push error: ' . $e->getMessage());
            return response()->json([
                'ResponseCode' => '1',
                'errorMessage' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            DB::connection('mongodb')->collection('vidence')->insert([
                'body' => $request->all(),
                'received_at' => now()
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('M-Pesa callback error: ' . $e->getMessage());
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed to log callback'], 500);
        }
    }
}

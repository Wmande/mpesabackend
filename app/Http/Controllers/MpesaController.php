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
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^2547\d{8}$/'],
            'amount' => ['required', 'numeric', 'min:1']
        ]);

        $phone = $request->input('phone');
        $amount = (int) $request->input('amount');
        $timestamp = now()->format('YmdHis');

        $shortcode = env('MPESA_SHORTCODE');
        $passkey = env('MPESA_PASSKEY');
        $callbackUrl = env('MPESA_CALLBACK_URL');

        if (!$shortcode || !$passkey || !$callbackUrl) {
            return response()->json([
                'ResponseCode' => '1',
                'errorMessage' => 'Server configuration error. Please contact support.'
            ], 500);
        }

        $password = base64_encode($shortcode . $passkey . $timestamp);

        try {
            $authResponse = Http::withBasicAuth(
                env('MPESA_CONSUMER_KEY'),
                env('MPESA_CONSUMER_SECRET')
            )->timeout(30)->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

            if (!$authResponse->ok()) {
                return response()->json([
                    'ResponseCode' => '1',
                    'errorMessage' => 'Failed to obtain access token: ' . $authResponse->body()
                ], 500);
            }

            $access_token = $authResponse['access_token'];

            $stkResponse = Http::withToken($access_token)->timeout(30)->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
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
                $responseData['ResponseCode'] = $responseData['ResponseCode'] ?? '0';
                return response()->json($responseData, 200);
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
            $collection = DB::connection('mongodb')->collection('vidence');

            $collection->insert([
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
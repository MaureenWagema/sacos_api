<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Log;

class AuthController extends Controller
{
    public function clientCredentialsAccessToken(Request $request)
    {
        try {
            $tokenRequest = Http::asForm()->timeout(30)->post(url('/') . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => '9743a477-41c3-47fa-bbe2-d1ab87f83bcb',
                'client_secret' => 'F0gSDutq24oDxPi90y0cLoYbgZUj8qTeXSIFtj0Q',
                'scope' => '',
            ]);

            $res = $tokenRequest->json();

        } catch (\Exception $exception) {
            $res = [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        } catch (\Throwable $throwable) {
            $res = [
                'success' => false,
                'message' => $throwable->getMessage()
            ];
        }

        return response()->json($res);
    }
    public function APIclientCredentialsAccessToken(Request $request)
{
    try {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $client_id = $request->input('client_id');
        $client_secret = $request->input('client_secret');

        // Log the token request details
        Log::info('Token Request:', [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ]);

        $response = Http::asForm()->timeout(120)->post(config('app.url') . '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ]);

        $res = $response->json();

        Log::info('Token Response:', ['response' => $res]);

        if (isset($res['access_token'])) {
            $res['success'] = true;
            $res['message'] = 'Token generated successfully';
        } else {
            $errorMessage = isset($res['error_description']) ? $res['error_description'] : 'Unknown error';
            $res['success'] = false;
            $res['message'] = 'Token generation failed: ' . $errorMessage;
        }
    } catch (\Exception $exception) {
        Log::error('Exception occurred:', ['exception' => $exception]);
        $res = [
            'success' => false,
            'message' => 'Exception occurred: ' . $exception->getMessage(),
        ];
    } catch (\Throwable $throwable) {
        Log::error('Throwable occurred:', ['throwable' => $throwable]);
        $res = [
            'success' => false,
            'message' => 'Throwable occurred: ' . $throwable->getMessage(),
        ];
    }

    return response()->json($res);
}



}
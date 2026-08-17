<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{

public function store(LoginRequest $request)
{
    $username = $request->username;
    $password = $request->password;

    // Detect email or mobile
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {

        // USER LOGIN (email only)
        $credentials = [
            'email' => $username,
            'password' => $password,
            'role_type' => 'user'
        ];

    } elseif (preg_match('/^[0-9]{10}$/', $username)) {

        // ADMIN LOGIN (mobile only)
        $credentials = [
            'mobile_no' => $username,
            'password' => $password,
            'role_type' => 'admin'
        ];

    } else {
        return response()->json([
            'message' => 'Please enter valid email or mobile number'
        ], 422);
    }

    // Attempt login
    if (!auth()->attempt($credentials)) {
        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }

    $user = auth()->user();

    // Check account status
    if (!$user->account_status) {
        return response()->json([
            'message' => 'Your account is inactive. Please contact support.'
        ], 403);
    }

    // Create API token
    $token = $user->createToken('api_token')->plainTextToken;

    $accessToken = $user->tokens()->latest()->first();
    $accessToken->expires_at = now()->addWeek();
    $accessToken->save();

    return response()->json([
        'message' => 'Login successful for ' . ucfirst($user->role_type),
        'token' => $token,
        'auth' => auth()->id(),
        'user' => $user,
    ]);
}

    /**
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Optional but recommended: same inactive-account check jo login mein hai
        if (!$user->account_status) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact support.'
            ], 403);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    public function destroy(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();
    
        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
    // Authorization: `Bearer ${localStorage.getItem('api_token')}`,
}
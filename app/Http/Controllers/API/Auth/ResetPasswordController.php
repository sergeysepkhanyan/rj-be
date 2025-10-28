<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PasswordService;

class ResetPasswordController extends Controller
{
    protected PasswordService $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    public function forgot(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string'
        ]);

        $response = $this->passwordService->sendResetLink($request->identifier);

        return response()->json($response);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'token' => 'required|string',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $response = $this->passwordService->resetPassword(
            $request->identifier,
            $request->token,
            $request->password
        );

        return response()->json($response);
    }
}


<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'mobile' => 'nullable|string|unique:users,mobile',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (! $request->filled('email') && ! $request->filled('mobile')) {
            return response()->json(['error' => 'Email or Mobile is required'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $token = auth()->login($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }


    public function login(Request $request): JsonResponse
    {
        $password = $request->input('password');
        if ($request->filled('email')) {
            $credentials = ['email' => $request->email, 'password' => $password];
        } elseif ($request->filled('mobile')) {
            $credentials = ['mobile' => $request->mobile, 'password' => $password];
        } else {
            return response()->json(['error' => 'Email or Mobile is required'], 422);
        }

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'user' => auth()->user(),
            'token' => $token,
        ]);
    }


}

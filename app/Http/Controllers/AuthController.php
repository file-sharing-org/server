<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            /**
             * TODO CHANGE MIN IN PRODUCTION TO 6
             */
            'password' => 'required|string|min:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $credentials = $request->only('name', 'password');
        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();
        return response()->json([
            'status' => 'success',
            'message' => 'You have been logged in',
            'user' => $user,
            'token' => $token,
        ], Response::HTTP_OK);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            /**
             * TODO CHANGE MIN IN PRODUCTION TO 6
             */
            'password' => 'required|string|min:1|confirmed',
            'password_confirmation' => 'required|string|min:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $oldUser = User::where('name', $request->name)->first();
        if ($oldUser != null) {
            return response()->json([
                'status' => 'warning',
                'message' => 'User with username: ' . $request->name . ' already exists',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        /**
         * each user has default everyone group
         */
        $user->groups()->attach(1);
        $user->save();

        $token = Auth::login($user);
        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user,
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }
}

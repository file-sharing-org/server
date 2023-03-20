<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function createModerator(Request $request)
    {
        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
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
                'message' => 'Moderator with name: ' . $request->name . ' already exists',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_moderator' => true,
        ]);
        /**
         * each admin has default everyone, admins group
         */
        $user->groups()->attach(1);
        $user->groups()->attach(2);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Moderator created successfully',
            'user' => $user,
        ], Response::HTTP_CREATED);
    }
}

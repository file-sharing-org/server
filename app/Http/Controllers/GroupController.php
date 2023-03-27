<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    public function createGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'u' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Auth::user();

        $oldGroup = Group::where('name', $request->name)->first();
        if ($oldGroup != null) {
            return response()->json([
                'status' => 'warning',
                'message' => $request->name . ' group already exists',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $group = new Group();
        $group->name = $request->name;
        $group->creator = $user->name;
        $group->save();
        foreach($request->u as $uName) {
            $user = User::where('name', $uName)->first()->id;
            $group->users()->attach($user);
        }
        $group->save();

        return response()->json([
            'status' => 'success',
        ], Response::HTTP_OK);
    }

    public function getGroups(Request $request)
    {
        $groups = Group::all();
        if ($groups == null) {
            return response()->json([
                'status' => 'warning',
                'message' => 'No groups',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        return response()->json([
            'status' => 'success',
            'groups' => $groups,
        ], Response::HTTP_OK);
    }

    public function usersGroup(Request $request)
    {
        $group = Group::where('name', $request->group)->first();
        if ($group == null) {
            return response()->json([
                'status' => 'warning',
                'message' => $request->group . " doesn't exists",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        $users = $group->users;
        return response()->json([
            'status' => 'success',
            'users' => $users,
        ], Response::HTTP_OK);
    }

    public function renameGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old' => 'required|string|max:255',
            'new' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $group = Group::where('name', $request->old)->first();
        if ($group == null) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Group with name ' . $request->old . " doesn't exists",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        $group->name = $request->new;
        $group->save();

        return response()->json([
            'status' => 'success',
        ], Response::HTTP_OK);
    }

    public function deleteGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $group = Group::where('name', $request->name)->first();
        if ($group == null) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Group with name ' . $request->name . " doesn't exists",
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        $result = $group->delete();
        if ($result) {
            return response()->json([
                'status' => 'success',
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed deleting ' . $request->name . ' group',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'u' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $group = Group::where('name', $request->name)->first();

        foreach ($request->u as $uName) {
            $flag = 0;
            foreach($group->users as $u) {
                if ($uName == $u->name) {
                    $flag = 1;
                }
            }
            if ($flag == 1) {
                continue;
            }
            $user = User::where('name', $uName)->first()->id;
            $group->users()->attach($user);
        }
        $group->save();

        return response()->json([
            'status' => 'success',
        ], Response::HTTP_OK);
    }

    public function deleteUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'u' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation error',
                'errors' => $validator->messages(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $group = Group::where('name', $request->name)->first();
        foreach($request->u as $uName) {
            $user = User::where('name', $uName)->first()->id;
            $group->users()->detach($user);
        }
        $group->save();

        return response()->json([
            'status' => 'success',
        ], Response::HTTP_OK);
    }
}

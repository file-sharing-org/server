<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FileController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function uploadFile(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $user = Auth::user();
            $file = $request->file('file');

            $path = $user->name . '/' . $request->folder;
            $file->store($path);

            return response()->json([
                'status' => 'success'
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => "File wasn't passed"
            ], 401);
        }
    }

    public function downloadFile(Request $request): BinaryFileResponse|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }
        $path = storage_path() . '/app/' . $user->name . '/' . $request->query('path');
        if (file_exists($path)) {
            return response()->download($path);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 401);
        }
    }
    public function copyFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $user = Auth::user();
            $groups = DB::table('groups_users')
                ->join('groups', 'groups_users.group_id', '=', 'groups.id')
                ->select('groups.name')
                ->where('user_id','=',$user->id)
                ->get();

            $pathFolder =  storage_path() . '/app/root/' . $request->query('folder');
            $content = file_get_contents($pathFolder .'.json');
            $folderConfig = json_decode($content);

            if (!in_array($user->name, $folderConfig->move->users)) {
                $check = FALSE;
                foreach($folderConfig->move->groups as $group) {
                    if ($groups->contains('name',$group)) {
                    $check = TRUE;
                    break;
                    }
                }
                if(!$check) {
                    return response()->json([
                        'status' => 'No access for copying',
                    ]);
                }
            }

            $newPathFolder = storage_path() . '/app/root/' . $request->query('path');
            $content = file_get_contents($newPathFolder .'.json');
            $folderConfig = json_decode($content);

            if (!in_array($user->name, $folderConfig->look->users)) {
                $check = FALSE;
                foreach ($folderConfig->look->groups as $group) {
                    if ($groups->contains('name', $group)) {
                        $check = TRUE;
                        break;
                    }
                }
                if(!$check) {
                    return response()->json([
                        'status' => 'No access to open'
                    ]);
                }
            }

            File::copyDirectory( $pathFolder, $newPathFolder. '/' . basename($pathFolder));
            File::copy( $pathFolder . '.json', $newPathFolder. '/' . basename($pathFolder) . '.json');

            return response()->json([
                'status' => 'success'
            ]);

        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
    public function createFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $pathFolder = $request->query('folder');

            $user = Auth::user();

            $path = storage_path() . '/app/root/' . $pathFolder;

            File::makeDirectory($path);
            $currentTime = Carbon::now();

            $users = DB::table('users')
                ->where('is_admin','=', 1)
                ->orWhere('is_moderator','=', 1)
                ->orWhere('name', $user->name)
                ->pluck('name');

            $template = [
                "file_type" => "dir",
                "file_name" => basename($path),
                "creation_date" => $currentTime,
                "creator" => $user->name,
                "look" => [
                    "groups" => [],
                    "users" => $users,
                ],
                "edit" => [
                    "groups" => [],
                    "users" => $users,
                ],
                "move" => [
                    "groups" => [],
                    "users" => $users,
                ],
                "file_extensions" => []
            ];

            file_put_contents($path . '.json', json_encode($template));
            //return response()->json($template);
            return response()->json([
                'status' => 'success'
            ]);

        }
        else
            {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Folder wasnt passed'
                ], 401);
        }
    }
    public function openFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $pathFolder = $request->query('folder');
            $path = $pathFolder;

            $files = Storage::files($path);
            $directories = Storage::directories($path);

            return response()->json([
                'status' => 'success',
                'files' => $files,
                'directories' => $directories
            ]);
        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
}

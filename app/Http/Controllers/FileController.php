<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function createFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $pathFolder = $request->query('folder');
            $user = Auth::user();
            $path = storage_path() . '/app/' . $user->name . '/' . $pathFolder;
            File::makeDirectory($path);

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
            $user = Auth::user();
            $path = storage_path() . '/app/' . $user->name . '/' . $pathFolder;
            $path = $user->name . '/' . $pathFolder;

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

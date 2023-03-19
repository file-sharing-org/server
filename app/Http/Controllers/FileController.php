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
use Illuminate\Filesystem\Filesystem;
use function Nette\Utils\isEmpty;

class FileController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function createConfig($path,$pathCreate,$fileType)
    {
        $user = Auth::user();
        $currentTime = Carbon::now();
        $users = DB::table('users')
            ->where('is_admin','=', 1)
            ->orWhere('is_moderator','=', 1)
            ->orWhere('name', $user->name)
            ->pluck('name');
        $template = [
            "file_type" => $fileType,
            "file_name" => basename($path),
            "creation_date" => $currentTime,
            "creator" => $user->name,
            "look" => [
                "groups" => ["everyone"],
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

        file_put_contents($pathCreate . '.conf', json_encode($template));
    }
    public function fileConflictResolution($fileOriginalName,$path = '')
    {
        if ($path == '') {
            $filename = pathinfo($fileOriginalName, PATHINFO_FILENAME);
        }
        else {
            $filename = $path . '/' . pathinfo($fileOriginalName, PATHINFO_FILENAME);
        }
        $extension = pathinfo($fileOriginalName, PATHINFO_EXTENSION);
        $files = Storage::files($path);
        $index = 1;
        $oldName = $filename;
        while(in_array($filename . '.' . $extension, $files))
        {
            $filename = $oldName . '(' . $index . ')';
            ++$index;
        }

        return $filename . '.' . $extension;
    }
    public function folderConflictResolution($path,$folderName)
    {
        $folders = Storage::directories($path);
        $index = 1;
        $oldName = $folderName;
        while(in_array($folderName, $folders))
        {
            $folderName = $oldName . '(' . $index . ')';
            ++$index;
        }
        return $folderName;
    }
    public function uploadFile(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileOriginalName = $request->file('file')->getClientOriginalName();

            $path = $request->folder;
            $path = self::fileConflictResolution($fileOriginalName,$path);
            if (substr($path, 0,1) == '/')
            {
                $path = substr($path, 1);
            }

            $file->storeAs('', $path, 'local');
            $path = storage_path() . '/app/root/' . $path;

            self::createConfig(basename($path),$path,"file");

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
        $path = storage_path() . '/app/root/' . $request->query('path');
        if (file_exists($path)) {
            return response()->download($path);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 401);
        }
    }

    public function checkRights($path,$flag)
    {
        $user = Auth::user();
        $groups = DB::table('groups_users')
            ->join('groups', 'groups_users.group_id', '=', 'groups.id')
            ->select('groups.name')
            ->where('user_id','=',$user->id)
            ->get();

        $content = file_get_contents($path .'.conf');
        $folderConfig = json_decode($content);

        switch($flag){
            case 'move':
                if (!in_array($user->name, $folderConfig->move->users)) {
                    foreach ($folderConfig->move->groups as $group) {
                        if ($groups->contains('name', $group)) {
                            return TRUE;
                            break;
                        }
                    }
                    return FALSE;
                }
                break;
            case 'edit':
                if (!in_array($user->name, $folderConfig->edit->users)) {
                    foreach ($folderConfig->edit->groups as $group) {
                        if ($groups->contains('name', $group)) {
                            return TRUE;
                            break;
                        }
                    }
                    return FALSE;
                }
                break;
            case 'look':
                if (!in_array($user->name, $folderConfig->look->users)) {
                    foreach ($folderConfig->look->groups as $group) {
                        if ($groups->contains('name', $group)) {
                            return TRUE;
                            break;
                        }
                    }
                    return FALSE;
                }
                break;
        }

        return TRUE;
    }

    public function rebaseFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {

            $pathFile =  storage_path() . '/app/root/' . $request->query('file');
            $newPathFolder = storage_path() . '/app/root/' . $request->query('path');

            if (self::checkRights($pathFile,'move')) {
                if (basename($newPathFolder) == 'root')
                    $nameFile = basename(self::fileConflictResolution(basename($pathFile)));
                else
                    $nameFile = basename(self::fileConflictResolution(basename($pathFile),basename($newPathFolder)));
                File::move($pathFile, $newPathFolder . '/' . $nameFile);
                File::move($pathFile . '.conf', $newPathFolder . '/' . $nameFile . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
    public function copyFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {

            $pathFile =  storage_path() . '/app/root/' . $request->query('file');
            $newPathFolder = storage_path() . '/app/root/' . $request->query('path');

            if (self::checkRights($pathFile,'move')) {
                if (basename($newPathFolder) == 'root')
                    $nameFile = basename(self::fileConflictResolution(basename($pathFile)));
                else
                    $nameFile = basename(self::fileConflictResolution(basename($pathFile),basename($newPathFolder)));
                File::copy($pathFile, $newPathFolder . '/' . $nameFile);
                File::copy($pathFile . '.conf', $newPathFolder . '/' . $nameFile . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
            ], 401);
        }
    }
    public function rebaseFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $folder = $request->query('folder');
            $newPath = $request->query('path');
            $pathFolderStorage =  storage_path() . '/app/root/' . $folder;
            $newPathFolderStorage = storage_path() . '/app/root/' . $newPath;

            if (self::checkRights($pathFolderStorage,'move')) {
                if ($newPath == '') {
                    $nameFolder = self::folderConflictResolution($newPath, basename($folder));
                }
                else{
                    $nameFolder = self::folderConflictResolution($newPath, $newPath . '/' . basename($folder));
                }
                File::moveDirectory($pathFolderStorage, $newPathFolderStorage . '/' . basename($nameFolder));
                File::move($pathFolderStorage . '.conf', $newPathFolderStorage . '/' . basename($nameFolder) . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
    public function copyFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {
            $folder = $request->query('folder');
            $newPath = $request->query('path');
            $pathFolderStorage =  storage_path() . '/app/root/' . $folder;
            $newPathFolderStorage = storage_path() . '/app/root/' . $newPath;

            if (self::checkRights($pathFolderStorage,'move')) {
                if ($newPath == '') {
                    $nameFolder = self::folderConflictResolution($newPath, basename($folder));
                }
                else{
                    $nameFolder = self::folderConflictResolution($newPath, $newPath . '/' . basename($folder));
                }
                File::copyDirectory($pathFolderStorage, $newPathFolderStorage . '/' . basename($nameFolder));
                File::copy($pathFolderStorage . '.conf', $newPathFolderStorage . '/' . basename($nameFolder) . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
    public function renameFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {

            $folder = $request->query('folder');
            $newName = $request->query('name');

            $pathRoot = str_replace(basename($folder), '', $folder);
            $pathFolderStorage =  storage_path() . '/app/root/' . $folder;
            if (substr("$pathRoot", -1) == '/')
            {
                $pathRoot = substr($pathRoot, 0, -1);
            }
            if (self::checkRights($pathFolderStorage,'edit')) {
                if ($pathRoot == '') {
                    $nameFolder = self::folderConflictResolution($pathRoot, $newName);
                }
                else{
                    $nameFolder = self::folderConflictResolution($pathRoot,$pathRoot . '/' . $newName);
                }
                //return response()->json(['status' => $nameFolder]);
                //$nameFolder = storage_path() . '/app/root/' . $nameFolder;
                $pathNewFolderStorage = str_replace(basename($folder), '', $pathFolderStorage) . basename($nameFolder);
                File::moveDirectory($pathFolderStorage, $pathNewFolderStorage);
                File::move($pathFolderStorage . '.conf', $pathNewFolderStorage . '.conf');
                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder wasnt passed'
            ], 401);
        }
    }
    public function renameFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {

            $pathFile = $request->query('file');
            $newName = $request->query('name');

            $pathRoot = str_replace(basename($pathFile), '', $pathFile);
            $pathFileStorage =  storage_path() . '/app/root/' . $pathFile;

            if (substr("$pathRoot", -1) == '/')
            {
                $pathRoot = substr($pathRoot, 0, -1);
            }

            if (self::checkRights($pathFileStorage,'edit')) {
                if ($pathRoot == '') {
                    $nameFile = self::fileConflictResolution($newName, basename($pathRoot));
                }
                else{
                    $nameFile = self::fileConflictResolution($pathRoot . '/' . $newName,basename($pathRoot));
                }

                $nameFile = storage_path() . '/app/root/' . $nameFile;
                $pathNewFileStorage = str_replace(basename($pathFile), '', $pathFileStorage) . basename($nameFile);
                File::move($pathFileStorage, $pathNewFileStorage);
                File::move($pathFileStorage . '.conf', $pathNewFileStorage . '.conf');
                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights',
                    'message' => 'File not found'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
            ], 401);
        }
    }
    public function deleteFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {

            $pathFolder = $request->query('folder');
            $pathFolderStorage =  storage_path() . '/app/root/' . $pathFolder;
            if (self::checkRights($pathFolderStorage,'edit')) {

                File::deleteDirectory($pathFolderStorage);
                File::delete($pathFolderStorage . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights',
                    'message' => 'File not found'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
            ], 401);
        }
    }
    public function deleteFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {
            $pathFile = $request->query('file');
            $pathFileStorage =  storage_path() . '/app/root/' . $pathFile;
            if (self::checkRights($request,$pathFileStorage,'edit')) {

                File::delete($pathFileStorage);
                File::delete($pathFileStorage . '.conf');

                return response()->json([
                    'status' => 'success'
                ]);
            }
            else
            {
                return response()->json([
                    'status' => 'Not enough rights',
                    'message' => 'File not found'
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 'error',
            ], 401);
        }
    }
    public function createFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {

            $pathFolder = $request->query('folder');
            $pathRoot = str_replace(basename($pathFolder), '', $pathFolder);

            if ($pathRoot == '') {
                $nameFolder = self::folderConflictResolution($pathRoot, basename($pathFolder));
            }
            else{
                $nameFolder = self::folderConflictResolution($pathRoot,$pathRoot . '/' . basename($pathFolder));
            }

            $path = storage_path() . '/app/root/' . $nameFolder;

            File::makeDirectory($path);
            self::createConfig($path,$path,"dir");
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

            foreach ($directories as $key => $dir)
            {
                $pathFolderStorage =  storage_path() . '/app/root/' . $dir;
                if (!self::checkRights($pathFolderStorage,'look')) {
                    unset($directories[$key]);
                }
            }
            foreach ($files as $key => $file)
            {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if ($extension == 'conf'){
                    unset($files[$key]);
                }
                else{
                    $pathFileStorage =  storage_path() . '/app/root/' . $file;
                    if (!self::checkRights($pathFileStorage,'look')) {
                        unset($files[$key]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'files' => array_values($files),
                'directories' => array_values($directories)
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

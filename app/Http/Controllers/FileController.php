<?php

namespace App\Http\Controllers;

use App\Models\User;
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
use Symfony\Component\HttpFoundation\Response;
use function Nette\Utils\isEmpty;

class FileController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public $root;
    public function __construct()
    {
        $this->root = storage_path() . '/app/root/';
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
    public function checkExtensions($path,$ext)
    {
        $countTokens = substr_count($path,'/');
        $parentPath = $path;
        for ($i = 0; $i < $countTokens + 1; ++$i) {
            $folderExtensions = DB::table('files')
                ->where('path','=',$parentPath)
                ->select('file_extensions')
                ->first();

            if(!is_null($folderExtensions->file_extensions) && in_array($ext,json_decode($folderExtensions->file_extensions))) {
                return false;
            }
            $folder = substr(strrchr($parentPath, '/'), 1);
            $parentPath = substr($parentPath, 0, - strlen($folder) - 1);
        }
        return true;
    }
    public function uploadFile(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileSize = $file->getSize();
            $fileOriginalName = $request->file('file')->getClientOriginalName();
            $path = $request->folder;

            if (self::checkExtensions($path,pathinfo($fileOriginalName, PATHINFO_EXTENSION)) == false)
                return response()->json([
                'status' => 'warning',
                'message' => pathinfo($fileOriginalName, PATHINFO_EXTENSION) . " files can't be uploaded to the directory",
            ], Response::HTTP_BAD_REQUEST);

            $path = self::fileConflictResolution($fileOriginalName,$path);
            if (substr($path, 0,1) == '/')
            {
                $path = substr($path, 1);
            }

            $file->storeAs('', $path, 'local');
            $user = Auth::user();

            $fileMetadata = new \App\Models\File();
            $fileMetadata->path = $path;
            $fileMetadata->file_type = 'file';
            $fileMetadata->file_size = $fileSize / 1000000;
            $fileMetadata->file_name = basename($path);
            $fileMetadata->creator = $user->id;
            $fileMetadata->look_groups = [1, 2];
            $fileMetadata->look_users = [$user->id];
            $fileMetadata->move_groups = [2];
            $fileMetadata->move_users = [$user->id];
            $fileMetadata->edit_groups = [2];
            $fileMetadata->edit_users = [$user->id];
            $fileMetadata->save();

            return response('');

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
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }
        $path = $this->root . $request->query('path');
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
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $groups = DB::table('groups_users')
            ->join('groups', 'groups_users.group_id', '=', 'groups.id')
            ->select('groups.id')
            ->where('user_id','=',$user->id)
            ->get();

        $file = \App\Models\File::find($path)->first();
        switch ($flag) {
            case 'look':
                if (in_array($user->id, $file->look_users)) {
                    return true;
                } else {
                    foreach ($groups as $group) {
                        if (in_array($group->id, $file->look_groups)) {
                            return true;
                        }
                    }
                }
                break;
            case 'edit':
                if (in_array($user->id, $file->edit_users)) {
                    return true;
                } else {
                    foreach ($groups as $group) {
                        if (in_array($group->id, $file->edit_groups)) {
                            return true;
                        }
                    }
                }
                break;
            case 'move':
                if (in_array($user->id, $file->move_users)) {
                    return true;
                } else {
                    foreach ($groups as $group) {
                        if (in_array($group->id, $file->move_groups)) {
                            return true;
                        }
                    }
                }
                break;
        }

        return false;
    }
    public function rebaseFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {
            $file = $request->file;
            $path = $request->path;

            if (self::checkExtensions($path,pathinfo($file, PATHINFO_EXTENSION)) == false)
                return response()->json([
                    'status' => 'warning',
                    'message' => pathinfo($file, PATHINFO_EXTENSION) . " files can't be uploaded to the directory",
                ], Response::HTTP_BAD_REQUEST);

            if (self::checkRights($file,'move')) {
                if (basename($this->root . $path) == 'root')
                    $nameFile = basename(self::fileConflictResolution(basename($file)));
                else
                    $nameFile = basename(self::fileConflictResolution(basename($file),$path));
                File::move($this->root . $file, $this->root . $path . '/' . $nameFile);

                if ($path != '') $path = $path . '/' ;

                DB::table('files')
                    ->where('path', '=', $file)
                    ->update(['file_name' => $nameFile , 'path' => $path . $nameFile]);

                DB::table('links')
                    ->where('path', '=', $file)
                    ->update(['path' => $path . $nameFile]);

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
    public function copyMetaData($file,$path,$name = '')
    {
        $metaData = DB::table('files')
            ->where('path', '=', $file)
            ->first();

        if ($path != '' && $name != '') $path = $path . '/' ;
        $path = $path . $name;
        $copyMetadata = new \App\Models\File();
        $copyMetadata->path = $path;
        $copyMetadata->file_type = $metaData->file_type;
        $copyMetadata->file_size = $metaData->file_size;
        $copyMetadata->file_name = basename($path);
        $copyMetadata->creator = $metaData->creator;
        $copyMetadata->look_groups = json_decode($metaData->look_groups);
        $copyMetadata->look_users = json_decode($metaData->look_users);
        $copyMetadata->move_groups = json_decode($metaData->move_groups);
        $copyMetadata->move_users = json_decode($metaData->move_users);
        $copyMetadata->edit_groups = json_decode($metaData->edit_groups);
        $copyMetadata->edit_users = json_decode($metaData->edit_users);
        $copyMetadata->save();
    }
    public function copyFile(Request $request): JsonResponse
    {
        if ($request->has('file')) {
            $file = $request->file;
            $path = $request->path;

            if (self::checkExtensions($path,pathinfo($file, PATHINFO_EXTENSION)) == false)
                return response()->json([
                    'status' => 'warning',
                    'message' => pathinfo($file, PATHINFO_EXTENSION) . " files can't be uploaded to the directory",
                ], Response::HTTP_BAD_REQUEST);

            if (self::checkRights($file,'move')) {
                if (basename($this->root . $path) == 'root')
                    $nameFile = basename(self::fileConflictResolution(basename($file)));
                else
                    $nameFile = basename(self::fileConflictResolution(basename($file),$path));

                File::copy($this->root . $file, $this->root . $path . '/' . $nameFile);
                self::copyMetaData($file,$path,$nameFile);
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
            $folder = $request->folder;
            $path = $request->path;
            if (self::checkRights($folder,'move')) {
                if ($path == '') {
                    $nameFolder = self::folderConflictResolution($path, basename($folder));
                }
                else{
                    $nameFolder = self::folderConflictResolution($path, $path . '/' . basename($folder));
                }
                $newPath = $path;
                if ($newPath != "") $newPath = $newPath . '/';
                $files = Storage::allFiles($folder);
                $directories = Storage::allDirectories($folder);
                foreach ($files as $file)
                {
                    if (self::checkExtensions($path,pathinfo($file, PATHINFO_EXTENSION)) == false)
                        return response()->json([
                            'status' => 'warning',
                            'message' => pathinfo($file, PATHINFO_EXTENSION) . " files can't be uploaded to the directory",
                        ], Response::HTTP_BAD_REQUEST);
                }
                foreach ($files as $file)
                {
                    $newPathFile = self::str_replace_first($folder,$newPath . basename($nameFolder),$file);

                    DB::table('files')
                        ->where('path', '=', $file)
                        ->update(['path' => $newPathFile]);

                    DB::table('links')
                        ->where('path', '=', $file)
                        ->update(['path' => $newPathFile]);
                }
                foreach ($directories as $dir)
                {
                    $newPathDir = self::str_replace_first($folder,$newPath . basename($nameFolder),$dir);
                    DB::table('files')
                        ->where('path', '=', $dir)
                        ->update(['path' => $newPathDir]);
                }

                File::moveDirectory($this->root . $folder, $this->root . $newPath . basename($nameFolder));
                DB::table('files')
                    ->where('path', '=', $folder)
                    ->update(['file_name' =>  basename($nameFolder) , 'path' => $nameFolder]);

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
            $folder = $request->folder;
            $path = $request->path;
            if (self::checkRights($folder,'move')) {
                if ($path == '') {
                    $nameFolder = self::folderConflictResolution($path, basename($folder));
                }
                else{
                    $nameFolder = self::folderConflictResolution($path, $path . '/' . basename($folder));
                }

                $newPath = $path;
                if ($newPath != "") $newPath = $newPath . '/';
                $files = Storage::allFiles($folder);
                $directories = Storage::allDirectories($folder);
                foreach ($files as $file)
                {
                    if (self::checkExtensions($path,pathinfo($file, PATHINFO_EXTENSION)) == false) {
                        return response()->json([
                            'status' => 'warning',
                        ]);
                    }
                }
                foreach ($files as $file)
                {
                    $newPathFile = self::str_replace_first($folder,$newPath . basename($nameFolder),$file);
                    self::copyMetaData($file,$newPathFile);
                }
                foreach ($directories as $dir)
                {
                    $newPathDir = self::str_replace_first($folder,$newPath . basename($nameFolder),$dir);
                    self::copyMetaData($dir,$newPathDir);
                }

                File::copyDirectory($this->root . $folder, $this->root . $newPath . basename($nameFolder));
                self::copyMetaData($folder,$path,basename($nameFolder));

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
    function str_replace_first($from, $to, $content) {

        $from = '/'.preg_quote($from, '/').'/';

        return preg_replace($from, $to, $content, 1);

    }
    public function renameFolder(Request $request): JsonResponse
    {
        if ($request->has('folder')) {

            $folder = $request->folder;
            $newName = $request->name;
            $pathRoot = str_replace(basename($folder), '', $folder);
            if (substr("$pathRoot", -1) == '/')
            {
                $pathRoot = substr($pathRoot, 0, -1);
            }

            if (self::checkRights($folder,'edit')) {
                if ($pathRoot == '') {
                    $nameFolder = self::folderConflictResolution($pathRoot, $newName);
                }
                else{
                    $nameFolder = self::folderConflictResolution($pathRoot,$pathRoot . '/' . $newName);
                }

                $pathFolderStorage =  storage_path() . '/app/root/' . $folder;
                $pathNewFolderStorage = str_replace(basename($folder), '', $pathFolderStorage) . basename($nameFolder);

                $files = Storage::allFiles($folder);
                $directories = Storage::allDirectories($folder);

                foreach ($files as $file)
                {
                    $newFolderPath = str_replace(storage_path() . '/app/root/', '', $pathNewFolderStorage);
                    $newPathFile = self::str_replace_first($folder,$newFolderPath,$file);
                    DB::table('files')
                        ->where('path', '=', $file)
                        ->update(['path' => $newPathFile]);

                    DB::table('links')
                        ->where('path', '=', $file)
                        ->update(['path' => $newPathFile]);
                }
                foreach ($directories as $dir)
                {
                    $newFolderPath = str_replace(storage_path() . '/app/root/', '', $pathNewFolderStorage);
                    $newPathDir = self::str_replace_first($folder,$newFolderPath,$dir);
                    DB::table('files')
                        ->where('path', '=', $dir)
                        ->update(['path' => $newPathDir]);

                }

                File::moveDirectory($pathFolderStorage, $pathNewFolderStorage);

                DB::table('files')
                    ->where('path', '=', $folder)
                    ->update(['file_name' => basename($nameFolder),'path' => $nameFolder]);


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


            $file = $request->file;
            $newName = $request->name;
            $path = str_replace(basename($file), '', $file);
            $pathFileStorage =  storage_path() . '/app/root/' . $file;
            if (substr("$path", -1) == '/')
            {
                $path = substr($path, 0, -1);
            }

            if (self::checkRights($file,'edit')) {
                if ($path == '') {
                    $nameFile = self::fileConflictResolution($newName);
                }
                else{
                    $nameFile = self::fileConflictResolution($path . '/' . $newName,$path);
                }

                $nameFile = storage_path() . '/app/root/' . $nameFile;
                $pathNewFileStorage = str_replace(basename($file), '', $pathFileStorage) . basename($nameFile);
                File::move($pathFileStorage, $pathNewFileStorage);

                if ($path != '') $path = $path . '/' ;
                DB::table('files')
                    ->where('path', '=', $file)
                    ->update(['file_name' => basename($nameFile),'path' => $path . basename($nameFile)]);

                DB::table('links')
                    ->where('path', '=', $file)
                    ->update(['path' => $path . basename($nameFile)]);

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

            $pathFolder = $request->folder;
            $pathFolderStorage =  storage_path() . '/app/root/' . $pathFolder;
            if (self::checkRights($pathFolder,'edit')) {

                $files = Storage::allFiles($pathFolder);
                $directories = Storage::allDirectories($pathFolder);

                File::deleteDirectory($pathFolderStorage);

                DB::table('files')
                    ->where('path', '=', $pathFolder)
                    ->delete();

                foreach ($files as $file)
                {
                    DB::table('files')
                        ->where('path', '=', $file)
                        ->delete();

                    DB::table('links')
                        ->where('path', '=', $file)
                        ->delete();
                }
                foreach ($directories as $dir)
                {
                    DB::table('files')
                        ->where('path', '=', $dir)
                        ->delete();
                }


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
            $path= $request->file;
            $pathFileStorage =  storage_path() . '/app/root/' . $path;
            if (self::checkRights($path,'edit')) {

                File::delete($pathFileStorage);

                DB::table('files')
                    ->where('path', '=', $path)
                    ->delete();

                DB::table('links')
                    ->where('path', '=', $path)
                    ->delete();

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
    public function createFolder(Request $request)
    {
        if ($request->has('folder')) {

            $folder = $request->folder;
            $path = substr(strrchr($folder, '/'), 1);
            $parentPath = substr($folder, 0, - strlen($path));

            if ($parentPath == '') {
                $nameFolder = self::folderConflictResolution($parentPath, basename($folder));
            }
            else{
                $nameFolder = self::folderConflictResolution($parentPath,$parentPath . basename($folder));
            }
            File::makeDirectory($this->root . $nameFolder);

            $user = Auth::user();
            $fileMetadata = new \App\Models\File();
            $fileMetadata->path = $nameFolder;
            $fileMetadata->file_type = 'dir';
            $fileMetadata->file_size = 0;
            $fileMetadata->file_name = basename($nameFolder);
            $fileMetadata->creator = $user->id;
            $fileMetadata->look_groups = [1, 2];
            $fileMetadata->look_users = [$user->id];
            $fileMetadata->move_groups = [2];
            $fileMetadata->move_users = [$user->id];
            $fileMetadata->edit_groups = [2];
            $fileMetadata->edit_users = [$user->id];
            $fileMetadata->save();

            return response('');
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
            $path = $request->folder;
            $files = Storage::files($path);
            $directories = Storage::directories($path);

            foreach ($directories as $key => $dir)
            {
                if (!self::checkRights($dir,'look')) {
                    unset($directories[$key]);
                }
            }
            foreach ($files as $key => $file)
            {
                if (!self::checkRights($file,'look')) {
                    unset($files[$key]);
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

    /**
     * ALL FUNCTIONS BELOW EXPECTS,
     * 1 - LOGGED IN USER == FILE_CREATOR, USER IS ADMIN OR MODERATOR
     * 2 - AND FILE_CREATOR CAN'T CHOOSE ADMIN OR MODERATORS FOR DELETING
     */

    public function permissionDeleteUsers(Request $request)
    {
        $path = $request->path;
        $permission = $request->permission;
        $users = $request->u;

        if ($permission != 'look' && $permission != 'edit' && $permission != 'move') {
            return response()->json([
                'message' => 'Permission should be look, edit or move'
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathFileStorage = storage_path() . '/app/root/' . $path;

        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $json = file_get_contents($pathFileStorage . '.conf');
        unlink($pathFileStorage . '.conf');
        $config = json_decode($json);
        switch ($permission) {
            case 'look': {
                foreach ($config->look->users as $i => $user) {
                    if (in_array($user, $users)) {
                        unset($config->look->users[$i]);
                    }
                }
                $config->look->users = array_values($config->look->users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
            case 'move': {
                foreach ($config->move->users as $i => $user) {
                    if (in_array($user, $users)) {
                        unset($config->move->users[$i]);
                    }
                }
                $config->move->users = array_values($config->move->users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
            case 'edit': {
                foreach ($config->edit->users as $i => $user) {
                    if (in_array($user, $users)) {
                        unset($config->edit->users[$i]);
                    }
                }
                $config->edit->users = array_values($config->edit->users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
        }
        return response()->json([
            'status' => 'success'
        ]);
    }

    public function permissionDeleteGroups(Request $request)
    {
        $path = $request->path;
        $permission = $request->permission;
        $groups = $request->g;

        if (in_array('admins',$groups))
        {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete admins',
            ]);
        }

        if ($permission != 'look' && $permission != 'edit' && $permission != 'move') {
            return response()->json([
                'message' => 'Permission should be look, edit or move'
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathFileStorage = storage_path() . '/app/root/' . $path;

        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $json = file_get_contents($pathFileStorage . '.conf');
        unlink($pathFileStorage . '.conf');
        $config = json_decode($json);
        switch ($permission) {
            case 'look': {
                foreach ($config->look->groups as $i => $group) {
                    if (in_array($group, $groups)) {
                        unset($config->look->groups[$i]);
                    }
                }
                $config->look->groups = array_values($config->look->groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
            case 'move': {
                foreach ($config->move->groups as $i => $group) {
                    if (in_array($group, $groups)) {
                        unset($config->move->groups[$i]);
                    }
                }
                $config->move->groups = array_values($config->move->groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
            case 'edit': {
                foreach ($config->edit->groups as $i => $group) {
                    if (in_array($group, $groups)) {
                        unset($config->edit->groups[$i]);
                    }
                }
                $config->edit->groups = array_values($config->edit->groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            }
        }
        return response()->json([
            'status' => 'success'
        ]);
    }

    public function permissionAddUsers(Request $request)
    {
        $path = $request->path;
        $permission = $request->permission;
        $users = $request->u;

        if ($permission != 'look' && $permission != 'edit' && $permission != 'move') {
            return response()->json([
                'message' => 'Permission should be look, edit or move'
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathFileStorage = storage_path() . '/app/root/' . $path;

        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $json = file_get_contents($pathFileStorage . '.conf');
        unlink($pathFileStorage . '.conf');
        $config = json_decode($json);
        switch ($permission) {
            case 'look':
                $config->look->users = array_merge($config->look->users, $users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            case 'edit':
                $config->edit->users = array_merge($config->edit->users, $users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            case 'move':
                $config->move->users = array_merge($config->move->users, $users);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
        }
        return response()->json([
            'status' => 'success'
        ]);
    }

    public function permissionAddGroups(Request $request)
    {
        $path = $request->path;
        $permission = $request->permission;
        $groups = $request->g;

        if ($permission != 'look' && $permission != 'edit' && $permission != 'move') {
            return response()->json([
                'message' => 'Permission should be look, edit or move'
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathFileStorage = storage_path() . '/app/root/' . $path;

        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $json = file_get_contents($pathFileStorage . '.conf');
        unlink($pathFileStorage . '.conf');
        $config = json_decode($json);
        switch ($permission) {
            case 'look':
                $config->look->groups = array_merge($config->look->groups, $groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            case 'edit':
                $config->edit->groups = array_merge($config->edit->groups, $groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
            case 'move':
                $config->move->groups = array_merge($config->move->groups, $groups);
                file_put_contents($pathFileStorage . '.conf', json_encode($config));
                break;
        }
        return response()->json([
            'status' => 'success'
        ]);
    }

    public function extensionsAdd(Request $request)
    {
        $dir = $request->folder;
        $extensions = $request->ext;

        $pathFolderStorage =  storage_path() . '/app/root/' . $dir;
        if (self::checkRights($pathFolderStorage, 'edit')) {
            $json = file_get_contents($pathFolderStorage . '.conf');
            unlink($pathFolderStorage . '.conf');
            $config = json_decode($json);

            $config->file_extensions = array_merge($config->file_extensions, $extensions);
            file_put_contents($pathFolderStorage . '.conf', json_encode($config));

            return response()->json([
                'status' => 'success',
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function extensionsDelete(Request $request)
    {
        $dir = $request->folder;
        $extensions = $request->ext;

        $pathFolderStorage =  storage_path() . '/app/root/' . $dir;
        if (self::checkRights($pathFolderStorage, 'edit')) {
            $json = file_get_contents($pathFolderStorage . '.conf');
            unlink($pathFolderStorage . '.conf');
            $config = json_decode($json);

            foreach ($config->file_extensions as $i => $extension) {
                if (in_array($extension, $extensions)) {
                    unset($config->file_extensions[$i]);
                }
            }
            $config->file_extensions = array_values($config->file_extensions);
            file_put_contents($pathFolderStorage . '.conf', json_encode($config));

            return response()->json([
                'status' => 'success',
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

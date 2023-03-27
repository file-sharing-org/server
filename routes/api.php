<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
});

Route::controller(FileController::class)->group(function () {
    Route::post('upload-file', 'uploadFile');
    Route::post('create-folder', 'createFolder');
    Route::post('copy-folder', 'copyFolder');
    Route::post('rebase-folder', 'rebaseFolder');
    Route::post('copy-file', 'copyFile');
    Route::post('rebase-file', 'rebaseFile');
    Route::post('rename-folder', 'renameFolder');
    Route::post('rename-file', 'renameFile');
    Route::post('delete-file', 'deleteFile');
    Route::post('delete-folder', 'deleteFolder');

    Route::get('files', 'downloadFile');
    Route::get('open-folder', 'openFolder');

    Route::post('permission-delete-users', 'permissionDeleteUsers');
    Route::post('permission-delete-groups', 'permissionDeleteGroups');
    Route::post('permission-add-users', 'permissionAddUsers');
    Route::post('permission-add-groups', 'permissionAddGroups');

    Route::post('ext-add', 'extensionsAdd');
    Route::post('ext-delete', 'extensionsDelete');
});

Route::controller(GroupController::class)->group(function () {
    Route::get('get-groups', 'getGroups');
    Route::get('users-group', 'usersGroup');
    Route::middleware(CheckPermissions::class)->group(function () {
        Route::post('create-group', 'createGroup');
        Route::post('rename-group', 'renameGroup');
        Route::post('delete-group', 'deleteGroup');
        Route::post('group-add-users', 'addUsers');
        Route::post('group-delete-users', 'deleteUsers');
    });
});

Route::controller(UserController::class)->group(function () {
    Route::post('create-moderator', 'createModerator');
});

Route::controller(LinkController::class)->group(function () {
    Route::post('create-link', 'createLink');
    Route::get('i/{link}', 'openLink');
});


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RegisterController;

Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

Route::post('/auth/google', [AuthController::class, 'googleLogin']);

Route::get('/email/verify', [AuthController::class, 'emailVerify'])->name('verification.verify.api');
Route::post('/email/resend', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:3,1');
Route::post('/forgot-password',  [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::put('/user/profile', [UserController::class, 'updateSelf']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // ROLE
    Route::get('/roles', [RolePermissionController::class, 'getRoles'])->middleware('permission:role.view');
    Route::post('/roles', [RolePermissionController::class, 'createRole'])->middleware('permission:role.create');
    Route::delete('/roles/{id}', [RolePermissionController::class, 'deleteRole'])->middleware('permission:role.delete');
    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'getAllRolesWithPermissions'])->middleware('permission:role.view');
    // PERMISSION
    Route::get('/permissions', [RolePermissionController::class, 'getPermissions'])->middleware('permission:permission.view');
    Route::post('/permissions', [RolePermissionController::class, 'addPermission'])->middleware('permission:permission.create');
    Route::delete('/permissions/{id}', [RolePermissionController::class, 'deletePermission'])->middleware('permission:permission.delete');
    Route::post('/roles/{role}/permissions/update', [RolePermissionController::class, 'updateRolePermissions'])->middleware('permission:permission.assignRole');
    // USER ROLE MANAGEMENT
    Route::post('/users/{id}/roles/update', [RolePermissionController::class, 'updateUserRoles'])->middleware('permission:role.assignUser');
});


Route::post('/pendaftaran', [RegisterController::class, 'register']);
Route::post('/donor/lookup-nik', [RegisterController::class, 'lookupByNik']);
Route::post('/ocr/ktp', [RegisterController::class, 'ocrKtp'])->name('ocr.ktp');

Route::get('/queues', [QueueController::class, 'index']);
Route::post('/queues/{id}/to-lab', [QueueController::class, 'moveToLab']);

Route::get('/lab/patients', [LabController::class, 'list']);
Route::post('/lab/process', [LabController::class, 'process']);

Route::get('/label/sample/{id}', [LabelController::class, 'sample']);
Route::get('/label/blood/{id}', [LabelController::class, 'blood']);

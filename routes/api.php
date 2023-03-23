<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NewsAggregate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//Login route
Route::post('login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json([
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '0704103356',
        'accesstoken' => $user->createToken($request->email)->plainTextToken,
    ]);

});
//Create account route
Route::post('register',[AuthController::class, 'register']);
//home page news if user is not logged in
Route::get('news',[NewsAggregate::class,'getNewsArticles']);
//search route
Route::post('search',[NewsAggregate::class, 'searchNews']);
//Footer news
Route::get('footer-news',[NewsAggregate::class,'footerNews']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('save-settings', [NewsAggregate::class, 'saveSettings']);
    Route::get('settings', [NewsAggregate::class, 'getSettings']);
    Route::get('filter',[NewsAggregate::class, 'getNewsArticles']);
});

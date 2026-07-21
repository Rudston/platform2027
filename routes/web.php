<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\RequestController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Communities\CommunityPage;
use App\Livewire\Communities\Services\Forums\ForumDiscussionPage;
use App\Livewire\Communities\Services\Forums\ForumGroupPage;
use App\Livewire\Explore\ExploreCommunities;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::livewire('/counter', 'counter');

Route::get('/explore', ExploreCommunities::class)->name('explore');

// Language switcher — sets the session locale, then redirects back.
Route::get('/locale/{locale}', LocaleController::class)->name('locale.update');

// Single community (circle) full page. Public for now — permissions later.
Route::get('/communities/{circle}', CommunityPage::class)->name('communities.show');

// A forum group's Discussions page. scopeBindings() resolves {forumGroup:slug}
// within {circle} (slugs are unique per circle, not globally).
Route::get('/communities/{circle}/forums/{forumGroup:slug}', ForumGroupPage::class)
    ->scopeBindings()
    ->name('communities.forums.show');

// A single discussion. scopeBindings() resolves {forumDiscussion:slug} within
// {forumGroup} within {circle}.
Route::get('/communities/{circle}/forums/{forumGroup:slug}/{forumDiscussion:slug}', ForumDiscussionPage::class)
    ->scopeBindings()
    ->name('communities.forums.discussions.show');

/*
|--------------------------------------------------------------------------
| External request approval (public, token-based — no auth)
|--------------------------------------------------------------------------
*/

Route::get('/requests/confirm/{token}', [RequestController::class, 'show'])
    ->name('requests.confirm');

Route::post('/requests/confirm/{token}/approve', [RequestController::class, 'approve'])
    ->name('requests.confirm.approve');

Route::post('/requests/confirm/{token}/deny', [RequestController::class, 'deny'])
    ->name('requests.confirm.deny');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

// Guest routes (the 'guest' middleware redirects authed users to /dashboard).
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Authenticated routes.
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Logout.
Route::post('/logout', LogoutController::class)->name('logout');

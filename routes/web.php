<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\ShareViewController;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});

Route::post('/logout', function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/kanban', 'kanban')->name('kanban');
    Route::livewire('/notes', 'notes')->name('notes');
    Route::livewire('/calendar', 'calendar')->name('calendar');
    Route::livewire('/projects', 'projects.index')->name('projects');
    Route::livewire('/projects/{project}/members', 'projects.members')->name('projects.members');
    Route::livewire('/projects/{project}/share', 'projects.share')->name('projects.share');
    Route::livewire('/api-tester', 'api-tester')->name('api-tester');
    Route::livewire('/logs', 'logs')->name('logs');
    Route::livewire('/snippets', 'snippets')->name('snippets');
    // Google Calendar OAuth
    Route::get('/auth/google/calendar', [GoogleCalendarController::class, 'redirect'])->name('google.calendar.redirect');
    Route::get('/auth/google/callback', [GoogleCalendarController::class, 'callback'])->name('google.calendar.callback');
    Route::delete('/auth/google/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google.calendar.disconnect');
});

// Public share links
Route::get('/s/{token}', [ShareViewController::class, 'show'])->name('share.view');
Route::livewire('/share', 'share-view')->name('share.public');


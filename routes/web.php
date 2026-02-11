<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Broadcast routes for WebSocket authentication
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Welcome page (public)
Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

// Public calendar feed (no auth — uses token)
Route::get('/cal/{token}.ics', [\App\Http\Controllers\Api\CalendarFeedController::class, 'feed']);

// Main application routes (with auth middleware)
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard (home)
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Dashboard alias for direct URL access
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    });

    // Chat
    Route::get('/chat', function () {
        return Inertia::render('Chat');
    })->name('chat');

    // Tasks (cases - discrete work items agents work on)
    Route::get('/tasks', function () {
        return Inertia::render('Tasks');
    })->name('tasks');

    Route::get('/tasks/{id}', function (string $id) {
        return Inertia::render('Tasks/Show', ['taskId' => $id]);
    })->whereUuid('id')->name('tasks.show');

    // Lists (kanban boards - formerly Tasks)
    Route::get('/lists', function () {
        return Inertia::render('Lists');
    })->name('lists');

    // Documents
    Route::get('/docs', function () {
        return Inertia::render('Docs');
    })->name('docs');

    // Activity
    Route::get('/activity', function () {
        return Inertia::render('Activity');
    })->name('activity');

    // Approvals
    Route::get('/approvals', function () {
        return Inertia::render('Approvals');
    })->name('approvals');

    // Automation
    Route::get('/automation', function () {
        return Inertia::render('Automation');
    })->name('automation');

    // Organization
    Route::get('/org', function () {
        return Inertia::render('Org');
    })->name('org');

    // Settings
    Route::get('/settings', function () {
        return Inertia::render('Settings');
    })->name('settings');

    // Workload
    Route::get('/workload', function () {
        return Inertia::render('Workload');
    })->name('workload');

    // Integrations
    Route::get('/integrations', function () {
        return Inertia::render('Integrations');
    })->name('integrations');

    // Calendar
    Route::get('/calendar', function () {
        return Inertia::render('Calendar');
    })->name('calendar');

    // Tables
    Route::get('/tables', function () {
        return Inertia::render('Tables');
    })->name('tables');

    Route::get('/tables/{id}', function (string $id) {
        return Inertia::render('Tables/Show', ['tableId' => $id]);
    })->name('tables.show');

    // Agent detail
    Route::get('/agent/{id}', function (string $id) {
        return Inertia::render('Agent/Show', ['id' => $id]);
    })->name('agent.show');

    // Messages (DM) - Redirect to unified chat
    Route::get('/messages', function () {
        return redirect('/chat');
    })->name('messages.index');

    Route::get('/messages/{id}', function (string $id) {
        return redirect('/chat?dm=' . $id);
    })->name('messages.show');

    // Profile pages
    Route::get('/profile/{id}', function (string $id) {
        $user = \App\Models\User::findOrFail($id);
        if ($user->type === 'agent') {
            return redirect("/agent/{$id}");
        }

        return Inertia::render('Profile/Show', ['id' => $id]);
    })->name('profile.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

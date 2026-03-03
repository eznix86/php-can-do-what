<?php

use App\Http\Controllers\Dashboard\ChatMessageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('team-chat', 'dashboard')->name('team-chat');
    Route::post('team-chat/chat-messages', [ChatMessageController::class, 'store'])
        ->name('team-chat.chat-messages.store');
});

require __DIR__.'/settings.php';

<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/kanban');
Route::livewire('/kanban', 'kanban')->name('kanban');
Route::livewire('/notes', 'notes')->name('notes');
Route::livewire('/calendar', 'calendar')->name('calendar');


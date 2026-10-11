<?php

use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MediaStreamController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SocialController;
use App\Http\Controllers\Public\TipsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::match(['get', 'head'], 'media/{path}', MediaStreamController::class)
    ->where('path', '.*')
    ->name('media.stream');

Route::get('tips', [TipsController::class, 'index'])->name('tips.index');
Route::get('tips/{slug}', [TipsController::class, 'show'])->name('tips.show');

Route::get('mi-historia', [PageController::class, 'historia'])->name('pages.historia');
Route::get('mi-proceso', [PageController::class, 'proceso'])->name('pages.proceso');
Route::get('aviso-de-privacidad', [PageController::class, 'privacidad'])->name('pages.privacidad');
Route::get('redes', SocialController::class)->name('social.index');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

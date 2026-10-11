<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeSettingsController;
use App\Http\Controllers\Admin\MediaCollectionController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageEntryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SocialLinkController;
use App\Http\Controllers\Admin\UploadSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('multimedia/buscar', [MediaController::class, 'search'])->name('multimedia.buscar');
    Route::resource('multimedia', MediaController::class)
        ->parameters(['multimedia' => 'media'])
        ->except(['show', 'edit', 'create']);

    Route::get('multimedia-colecciones', [MediaCollectionController::class, 'index'])->name('multimedia-colecciones.index');
    Route::post('multimedia-colecciones', [MediaCollectionController::class, 'store'])->name('multimedia-colecciones.store');

    Route::post('multimedia-sesiones', [UploadSessionController::class, 'store'])->name('multimedia-sesiones.store');
    Route::post('multimedia-sesiones/{uuid}/fragmentos', [UploadSessionController::class, 'appendChunk'])->name('multimedia-sesiones.fragmentos');
    Route::post('multimedia-sesiones/{uuid}/completar', [UploadSessionController::class, 'complete'])->name('multimedia-sesiones.completar');
    Route::delete('multimedia-sesiones/{uuid}', [UploadSessionController::class, 'destroy'])->name('multimedia-sesiones.destroy');

    Route::get('inicio', [HomeSettingsController::class, 'edit'])->name('inicio.edit');
    Route::put('inicio', [HomeSettingsController::class, 'update'])->name('inicio.update');

    Route::get('paginas', [PageController::class, 'index'])->name('paginas.index');
    Route::get('paginas/{key}', [PageController::class, 'edit'])->name('paginas.edit');
    Route::put('paginas/{key}', [PageController::class, 'update'])->name('paginas.update');

    Route::post('paginas-entradas', [PageEntryController::class, 'store'])->name('paginas-entradas.store');
    Route::put('paginas-entradas/{pageEntry}', [PageEntryController::class, 'update'])->name('paginas-entradas.update');
    Route::delete('paginas-entradas/{pageEntry}', [PageEntryController::class, 'destroy'])->name('paginas-entradas.destroy');

    Route::resource('redes-sociales', SocialLinkController::class)
        ->parameters(['redes-sociales' => 'socialLink'])
        ->except(['show', 'create', 'edit']);

    Route::resource('categorias', CategoryController::class)
        ->parameters(['categorias' => 'category'])
        ->except(['show']);

    Route::resource('publicaciones', PostController::class)
        ->parameters(['publicaciones' => 'post'])
        ->except(['show']);

    Route::get('publicaciones/{post}/vista-previa', [PostController::class, 'preview'])->name('publicaciones.preview');
    Route::patch('publicaciones/{post}/autoguardar', [PostController::class, 'autosave'])->name('publicaciones.autoguardar');
    Route::post('publicaciones/{post}/publicar', [PostController::class, 'publish'])->name('publicaciones.publicar');
    Route::post('publicaciones/{post}/programar', [PostController::class, 'schedule'])->name('publicaciones.programar');
    Route::post('publicaciones/{post}/archivar', [PostController::class, 'archive'])->name('publicaciones.archivar');
    Route::post('publicaciones/{post}/destacar', [PostController::class, 'toggleFeatured'])->name('publicaciones.destacar');
});

<?php

use App\Enums\MediaVariantType;
use App\Jobs\GenerateImageVariants;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\MediaVariant;
use App\Models\StorageProvider;
use App\Models\User;
use App\Services\MediaUploadService;
use App\Support\MediaPaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $root = MediaCollection::query()->create(['parent_id' => null, 'name' => 'MR. INSIGHT', 'slug' => 'mr-insight', 'path' => 'mr-insight']);
    MediaCollection::query()->create(['parent_id' => $root->id, 'name' => 'General', 'slug' => 'general', 'path' => 'mr-insight/general']);
    $this->actingAs(User::factory()->create());
});

test('new uploads get a readable, unique filename inside their collection folder', function () {
    Storage::fake('media_local');
    Queue::fake();

    $first = app(MediaUploadService::class)->upload(UploadedFile::fake()->image('Portada Evento CDMX ñandú.JPG', 40, 40));
    $second = app(MediaUploadService::class)->upload(UploadedFile::fake()->image('Portada Evento CDMX ñandú.JPG', 40, 40));

    $date = now()->format('Ymd');
    expect($first->filename)->toMatch("/^portada-evento-cdmx-nandu-{$date}-[a-z0-9]{6}\\.jpg$/")
        ->and($first->path)->toBe('mr-insight/general/'.$first->filename)
        ->and($first->original_filename)->toBe('Portada Evento CDMX ñandú.JPG')
        ->and($second->filename)->not->toBe($first->filename);

    Storage::disk('media_local')->assertExists($first->path);
});

test('filenames without a usable name or extension fall back safely', function () {
    expect(MediaPaths::slug('???'))->toBe('archivo')
        ->and(MediaPaths::slug('../../etc/passwd'))->not->toContain('/')->not->toContain('.');

    Storage::fake('media_local');
    $name = MediaPaths::uniqueFilename(Storage::disk('media_local'), 'mr-insight/general', 'sin-extension');
    expect($name)->toEndWith('.bin');
});

test('image variants are stored apart in a _variantes folder with the original name', function () {
    Storage::fake('media_local');

    $media = app(MediaUploadService::class)->upload(UploadedFile::fake()->image('foto boda.jpg', 1200, 800));
    (new GenerateImageVariants($media->id))->handle();

    $base = pathinfo($media->filename, PATHINFO_FILENAME);
    $webp = MediaVariant::query()->where('media_id', $media->id)->where('type', MediaVariantType::OptimizedWebp->value)->first();
    $thumb = MediaVariant::query()->where('media_id', $media->id)->where('type', MediaVariantType::Thumbnail->value)->first();

    expect($webp->path)->toBe("mr-insight/general/_variantes/{$base}.webp")
        ->and($thumb->path)->toBe("mr-insight/general/_variantes/{$base}-miniatura.webp");
    Storage::disk('media_local')->assertExists([$webp->path, $thumb->path, $media->path]);
});

test('readable names are used on SynologyExtra too', function () {
    $root = fakeSynologyMount();
    Queue::fake();
    StorageProvider::query()->create(['key' => 'local', 'label' => 'Servidor principal', 'is_active' => false, 'config' => ['disk' => 'media_local']]);
    StorageProvider::query()->create(['key' => 'synology', 'label' => 'SynologyExtra', 'is_active' => true, 'config' => ['disk' => 'media_synology']]);

    $media = app(MediaUploadService::class)->upload(UploadedFile::fake()->image('Sesión estudio.png', 40, 40));

    expect($media->disk)->toBe('media_synology')
        ->and($media->filename)->toStartWith('sesion-estudio-')
        ->and(is_file($root.'/'.$media->path))->toBeTrue();
});

test('existing media keep their stored path', function () {
    Storage::fake('media_local');
    Storage::disk('media_local')->put('mr-insight/general/0b6f3c1e-legacy.jpg', 'x');
    $legacy = Media::factory()->create(['disk' => 'media_local', 'path' => 'mr-insight/general/0b6f3c1e-legacy.jpg']);

    expect($legacy->fresh()->path)->toBe('mr-insight/general/0b6f3c1e-legacy.jpg');
    Storage::disk('media_local')->assertExists($legacy->path);
});

test('video posters go to the _variantes folder', function () {
    expect(MediaPaths::variantPath('mr-insight/videos/entrevista-20261109-a1b2c3.mp4', 'portada', 'jpg'))
        ->toBe('mr-insight/videos/_variantes/entrevista-20261109-a1b2c3-portada.jpg');
});

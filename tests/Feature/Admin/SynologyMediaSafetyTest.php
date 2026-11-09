<?php

use App\Enums\MediaStatus;
use App\Enums\UploadSessionStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateImageVariants;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\MediaVariant;
use App\Models\StorageProvider;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\FfmpegService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Seguridad de escritura y lectura con el NAS Synology como proveedor
 * activo: nunca se escribe en el punto de montaje vacío, nunca se cae en
 * silencio al disco local y los archivos históricos siguen leyéndose de
 * su propio disco.
 */

function nasAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function activateSynology(): void
{
    StorageProvider::query()->where('key', 'local')->update(['is_active' => false]);
    StorageProvider::query()->where('key', 'synology')->update(['is_active' => true]);
}

function nasMedia(string $disk, string $content = 'contenido-nas', string $mime = 'image/jpeg', string $type = 'image'): Media
{
    $collection = MediaCollection::query()->where('path', 'mr-insight/general')->firstOrFail();
    $path = 'mr-insight/general/'.Str::uuid().'.jpg';
    Storage::disk($disk)->put($path, $content);

    return Media::factory()->create([
        'media_collection_id' => $collection->id,
        'disk' => $disk,
        'path' => $path,
        'mime_type' => $mime,
        'type' => $type,
        'size' => strlen($content),
    ]);
}

beforeEach(function () {
    Storage::fake('media_local');
    Storage::fake('local');

    StorageProvider::query()->create(['key' => 'local', 'label' => 'Almacenamiento local', 'is_active' => true, 'config' => ['disk' => 'media_local']]);
    StorageProvider::query()->create(['key' => 'synology', 'label' => 'Synology NAS', 'is_active' => false, 'config' => ['disk' => 'media_synology']]);

    $root = MediaCollection::query()->create(['parent_id' => null, 'name' => 'MR. INSIGHT', 'slug' => 'mr-insight', 'path' => 'mr-insight']);
    MediaCollection::query()->create(['parent_id' => $root->id, 'name' => 'General', 'slug' => 'general', 'path' => 'mr-insight/general']);
});

test('an upload with synology active and mounted is stored on the NAS with its variants', function () {
    $root = fakeSynologyMount();
    activateSynology();

    $this->actingAs(nasAdmin())
        ->postJson('/admin/multimedia', ['file' => UploadedFile::fake()->image('foto.jpg', 800, 600)])
        ->assertCreated();

    $media = Media::query()->firstOrFail();
    expect($media->disk)->toBe('media_synology');
    expect(is_file($root.'/'.$media->path))->toBeTrue();
    expect(MediaVariant::query()->where('media_id', $media->id)->where('disk', 'media_synology')->count())->toBe(2);
    expect(Storage::disk('media_local')->allFiles())->toBe([]);
});

test('an upload with synology active but unmounted fails without creating media or writing anywhere', function () {
    $root = fakeSynologyMount(mounted: false);
    activateSynology();

    $response = $this->actingAs(nasAdmin())
        ->postJson('/admin/multimedia', ['file' => UploadedFile::fake()->image('foto.jpg', 800, 600)]);

    $response->assertStatus(503);
    expect($response->json('message'))->toContain('Synology');
    expect($response->json('message'))->not->toContain($root);

    expect(Media::query()->count())->toBe(0);
    expect(filesUnder($root))->toBe([]); // nada en el punto de montaje vacío
    expect(Storage::disk('media_local')->allFiles())->toBe([]); // nada de fallback al disco local
    expect(StorageProvider::query()->where('key', 'synology')->first()->is_active)->toBeTrue();
});

test('a chunked upload cannot start while synology is active but unmounted', function () {
    fakeSynologyMount(mounted: false);
    activateSynology();

    $this->actingAs(nasAdmin())->postJson('/admin/multimedia-sesiones', [
        'filename' => 'video.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => 1000,
    ])->assertStatus(503);

    expect(UploadSession::query()->count())->toBe(0);
});

test('completing a chunked upload after the NAS drops fails and keeps the session resumable', function () {
    $root = fakeSynologyMount();
    activateSynology();
    $user = nasAdmin();
    $content = str_repeat('v', 50_000);

    $uuid = $this->actingAs($user)->postJson('/admin/multimedia-sesiones', [
        'filename' => 'video.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => strlen($content),
    ])->assertCreated()->json('uuid');

    $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", [
        'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $content),
        'chunkIndex' => 0,
    ])->assertOk();

    // El NAS se desmonta entre el último fragmento y el ensamblado.
    setSynologyMountTable($root.'.mounts', []);

    $this->actingAs($user)->postJson("/admin/multimedia-sesiones/{$uuid}/completar")->assertStatus(503);

    $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
    expect(Media::query()->count())->toBe(0);
    expect($session->status)->toBe(UploadSessionStatus::Uploading);
    Storage::disk('local')->assertExists($session->temp_path);
    expect(filesUnder($root))->toBe([]);
    expect(Storage::disk('media_local')->allFiles())->toBe([]);
});

test('historical media on the local disk keeps streaming while synology is active and unmounted', function () {
    fakeSynologyMount(mounted: false);
    $media = nasMedia('media_local', 'archivo-historico');
    activateSynology();

    $response = $this->get("/media/{$media->path}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('archivo-historico');
});

test('streaming media stored on an unmounted NAS returns 503 without leaking paths', function () {
    $root = fakeSynologyMount();
    $media = nasMedia('media_synology');
    setSynologyMountTable($root.'.mounts', []);

    $response = $this->get("/media/{$media->path}");

    $response->assertStatus(503);
    $response->assertHeader('Retry-After', '60');
    expect($response->getContent())->not->toContain($root);
});

test('streaming media stored on a mounted NAS works', function () {
    fakeSynologyMount();
    $media = nasMedia('media_synology', '0123456789');

    $response = $this->withHeaders(['Range' => 'bytes=2-5'])->get("/media/{$media->path}");

    $response->assertStatus(206);
    expect($response->streamedContent())->toBe('2345');
});

test('image variants are postponed instead of written elsewhere when the NAS is down', function () {
    $root = fakeSynologyMount();
    $media = nasMedia('media_synology', (string) UploadedFile::fake()->image('foto.jpg', 600, 400)->getContent());
    setSynologyMountTable($root.'.mounts', []);

    $job = (new GenerateImageVariants($media->id))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased();
    expect(MediaVariant::query()->count())->toBe(0);
    expect(Storage::disk('media_local')->allFiles())->toBe([]);
});

test('video processing is postponed when the NAS is down and the video keeps its state', function () {
    $root = fakeSynologyMount();
    $media = nasMedia('media_synology', 'video', 'video/mp4', 'video');
    $media->update(['status' => MediaStatus::Processing->value]);
    setSynologyMountTable($root.'.mounts', []);

    $ffmpeg = Mockery::mock(FfmpegService::class);
    $ffmpeg->shouldReceive('isAvailable')->andReturn(true);
    $ffmpeg->shouldNotReceive('durationSeconds');
    $ffmpeg->shouldNotReceive('generateThumbnail');

    $job = (new ProcessVideoMedia($media->id))->withFakeQueueInteractions();
    $job->handle($ffmpeg);

    $job->assertReleased();
    expect($media->refresh()->status)->toBe(MediaStatus::Processing);
    expect(MediaVariant::query()->count())->toBe(0);
});

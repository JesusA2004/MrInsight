<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantType;
use App\Exceptions\StorageUnavailableException;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\FfmpegService;
use App\Services\Storage\StorageHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Extrae duración y miniatura de un video recién subido. Si FFmpeg no está
 * instalado en el servidor, el video permanece utilizable (estado "ready")
 * simplemente sin miniatura ni duración automáticas.
 *
 * Si el video vive en el NAS y este no está disponible, el trabajo se
 * reintenta más tarde con espera creciente: FFmpeg nunca lee ni escribe en
 * el punto de montaje vacío. Agotados los intentos, el trabajo falla con
 * el motivo registrado y el video conserva su estado.
 */
class ProcessVideoMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public int $mediaId) {}

    public function handle(FfmpegService $ffmpeg): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            return;
        }

        if (! $ffmpeg->isAvailable()) {
            Log::info('ProcessVideoMedia: FFmpeg no disponible, se omite miniatura/duración.', ['media_id' => $media->id]);
            $media->update(['status' => MediaStatus::Ready->value]);

            return;
        }

        try {
            app(StorageHealth::class)->assertWritable($media->disk);
        } catch (StorageUnavailableException $e) {
            Log::warning('ProcessVideoMedia: almacenamiento no disponible, se pospone.', [
                'media_id' => $media->id,
                'disk' => $media->disk,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            } else {
                $this->release(min(600, 60 * $this->attempts()));
            }

            return;
        }

        $disk = Storage::disk($media->disk);
        $absoluteVideoPath = $disk->path($media->path);

        $duration = $ffmpeg->durationSeconds($absoluteVideoPath);

        $posterFilename = Str::uuid()->toString().'.jpg';
        $posterPath = dirname($media->path).'/'.$posterFilename;
        $absolutePosterPath = $disk->path($posterPath);

        $generated = $ffmpeg->generateThumbnail($absoluteVideoPath, $absolutePosterPath);

        if ($generated) {
            MediaVariant::query()->create([
                'media_id' => $media->id,
                'type' => MediaVariantType::Poster->value,
                'disk' => $media->disk,
                'path' => $posterPath,
                'size' => filesize($absolutePosterPath) ?: null,
            ]);
        }

        $media->update([
            'duration_seconds' => $duration,
            'status' => MediaStatus::Ready->value,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessVideoMedia: se agotaron los intentos; el video conserva su estado.', [
            'media_id' => $this->mediaId,
            'reason' => $exception instanceof StorageUnavailableException ? 'almacenamiento_no_disponible' : ($exception !== null ? $exception::class : null),
        ]);
    }
}

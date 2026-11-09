<?php

namespace App\Jobs;

use App\Enums\MediaVariantType;
use App\Exceptions\StorageUnavailableException;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\Storage\StorageHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Genera una miniatura y una versión WebP optimizada de una imagen recién
 * subida. Corre en cola para no bloquear la petición de subida con trabajo
 * de procesamiento de imagen. El archivo original nunca se modifica ni se
 * elimina.
 *
 * Las variantes se escriben en el mismo disco que el original. Si ese disco
 * es el NAS y no está disponible, el trabajo se reintenta más tarde (nunca
 * escribe en el punto de montaje vacío ni en el disco local).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            return;
        }

        if (! $this->storageReady($media)) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $absoluteOriginal = $disk->path($media->path);

        $manager = ImageManager::gd();
        $directory = dirname($media->path);
        $basename = pathinfo($media->path, PATHINFO_FILENAME);

        $image = $manager->read($absoluteOriginal);

        $webpPath = "{$directory}/{$basename}.webp";
        $webpEncoded = $image->toWebp(quality: 82);
        $this->write($media->disk, $webpPath, (string) $webpEncoded);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => MediaVariantType::OptimizedWebp->value],
            ['disk' => $media->disk, 'path' => $webpPath, 'width' => $image->width(), 'height' => $image->height(), 'size' => strlen((string) $webpEncoded)]
        );

        $thumbnail = $manager->read($absoluteOriginal)->scaleDown(width: 480);
        $thumbPath = "{$directory}/{$basename}-thumb.webp";
        $thumbEncoded = $thumbnail->toWebp(quality: 75);
        $this->write($media->disk, $thumbPath, (string) $thumbEncoded);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => MediaVariantType::Thumbnail->value],
            ['disk' => $media->disk, 'path' => $thumbPath, 'width' => $thumbnail->width(), 'height' => $thumbnail->height(), 'size' => strlen((string) $thumbEncoded)]
        );
    }

    /**
     * Con el NAS caído: reintenta con espera creciente y, agotados los
     * intentos, falla de forma explícita con un motivo registrado.
     */
    private function storageReady(Media $media): bool
    {
        try {
            app(StorageHealth::class)->assertWritable($media->disk);

            return true;
        } catch (StorageUnavailableException $e) {
            Log::warning('GenerateImageVariants: almacenamiento no disponible, se pospone.', [
                'media_id' => $media->id,
                'disk' => $media->disk,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            } else {
                $this->release(min(600, 60 * $this->attempts()));
            }

            return false;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('GenerateImageVariants: se agotaron los intentos; la imagen queda sin variantes.', [
            'media_id' => $this->mediaId,
            'reason' => $exception instanceof StorageUnavailableException ? 'almacenamiento_no_disponible' : ($exception !== null ? $exception::class : null),
        ]);
    }

    private function write(string $disk, string $path, string $contents): void
    {
        app(StorageHealth::class)->assertWritable($disk);

        if (Storage::disk($disk)->put($path, $contents) === false) {
            throw new StorageUnavailableException('No se pudo guardar una variante de imagen en el almacenamiento.');
        }
    }
}

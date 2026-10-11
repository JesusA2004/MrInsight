<?php

namespace App\Services;

use App\Enums\UploadSessionStatus;
use App\Models\Media;
use App\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Subida reanudable por fragmentos para archivos grandes (sobre todo
 * video). Los fragmentos se acumulan en un archivo temporal privado
 * (disco "local") y, al completarse, se entregan a MediaUploadService
 * para ingresar a la biblioteca con el mismo flujo que una subida simple.
 *
 * No depende de que una única petición HTTP viva todo el tiempo que tarda
 * la subida: cada fragmento es su propia petición corta.
 */
class ChunkedUploadService
{
    private const TEMP_DIR = 'uploads-tmp';

    public function start(
        string $filename,
        string $mimeType,
        int $totalSize,
        ?int $userId,
        ?string $collectionPath,
        ?string $storageProviderKey,
        MediaUploadService $uploader,
    ): UploadSession {
        $uuid = (string) Str::uuid();
        $collection = $uploader->resolveCollection($collectionPath ?? 'mr-insight/general');

        return UploadSession::query()->create([
            'uuid' => $uuid,
            'user_id' => $userId,
            'media_collection_id' => $collection->id,
            'storage_provider_key' => $storageProviderKey,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'total_size' => $totalSize,
            'received_size' => 0,
            'chunk_size' => config('media.chunk_size_mb') * 1024 * 1024,
            'status' => UploadSessionStatus::Uploading->value,
            'temp_path' => self::TEMP_DIR.'/'.$uuid.'.part',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function appendChunk(UploadSession $session, UploadedFile $chunk): UploadSession
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($session->temp_path);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $out = fopen($absolutePath, 'ab');
        $in = fopen($chunk->getRealPath(), 'rb');

        if ($out === false || $in === false) {
            throw new \RuntimeException('No se pudo escribir el fragmento de subida.');
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $session->update(['received_size' => filesize($absolutePath) ?: $session->received_size]);

        return $session->refresh();
    }

    public function complete(UploadSession $session, MediaUploadService $uploader): Media
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($session->temp_path);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('No se encontró el archivo temporal de la subida.');
        }

        $media = $uploader->ingest(
            $absolutePath,
            $session->filename,
            $session->mime_type ?? 'application/octet-stream',
            (int) $session->received_size,
            $session->collection?->path,
        );

        @unlink($absolutePath);

        $session->update([
            'status' => UploadSessionStatus::Completed->value,
            'media_id' => $media->id,
        ]);

        return $media;
    }

    public function abort(UploadSession $session): void
    {
        $disk = Storage::disk('local');
        if ($session->temp_path && $disk->exists($session->temp_path)) {
            $disk->delete($session->temp_path);
        }

        $session->update(['status' => UploadSessionStatus::Aborted->value]);
    }

    /**
     * Limpia sesiones de subida abandonadas (sin actividad y ya vencidas).
     */
    public function pruneExpired(): int
    {
        $disk = Storage::disk('local');
        $expired = UploadSession::query()
            ->whereIn('status', [UploadSessionStatus::Pending->value, UploadSessionStatus::Uploading->value])
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $session) {
            if ($session->temp_path && $disk->exists($session->temp_path)) {
                $disk->delete($session->temp_path);
            }
            $session->update(['status' => UploadSessionStatus::Aborted->value]);
        }

        return $expired->count();
    }
}

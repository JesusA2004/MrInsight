<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\UploadSession;
use App\Services\ActivityLogger;
use App\Services\ChunkedUploadService;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UploadSessionController extends Controller
{
    public function store(Request $request, ChunkedUploadService $chunks, MediaUploadService $uploader): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string', 'max:100'],
            'totalSize' => ['required', 'integer', 'min:1', 'max:'.(config('media.max_upload_mb') * 1024 * 1024)],
            'collection' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $chunks->start(
            $validated['filename'],
            $validated['mimeType'],
            $validated['totalSize'],
            $request->user()?->id,
            $validated['collection'] ?? null,
            null,
            $uploader,
        );

        return response()->json([
            'uuid' => $session->uuid,
            'chunkSize' => $session->chunk_size,
        ], 201);
    }

    public function appendChunk(Request $request, string $uuid, ChunkedUploadService $chunks): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        $request->validate(['chunk' => ['required', 'file']]);

        $session = $chunks->appendChunk($session, $request->file('chunk'));

        return response()->json([
            'receivedSize' => $session->received_size,
            'totalSize' => $session->total_size,
            'progress' => $session->progressPercent(),
        ]);
    }

    public function complete(Request $request, string $uuid, ChunkedUploadService $chunks, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        $media = $chunks->complete($session, $uploader);

        $activity->log('multimedia.subido', $media, ['filename' => $media->original_filename, 'chunked' => true]);

        return response()->json([
            'media' => [
                'id' => $media->id,
                'filename' => $media->original_filename,
                'type' => $media->type->value,
                'url' => $media->url(),
                'status' => $media->status->value,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $uuid, ChunkedUploadService $chunks): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        $chunks->abort($session);

        return response()->json(['aborted' => true]);
    }
}

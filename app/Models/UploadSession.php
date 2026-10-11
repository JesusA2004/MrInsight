<?php

namespace App\Models;

use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSession extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'media_collection_id', 'media_id', 'storage_provider_key',
        'filename', 'mime_type', 'total_size', 'received_size', 'chunk_size',
        'status', 'temp_path', 'error_message', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UploadSessionStatus::class,
            'total_size' => 'integer',
            'received_size' => 'integer',
            'chunk_size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MediaCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'media_collection_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_size <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->received_size / $this->total_size) * 100));
    }
}

<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Convierte modelos Post en los arreglos planos que consumen las plantillas
 * Inertia del sitio público (y la previsualización autorizada del admin),
 * para no repetir el mismo mapeo en cada controlador.
 */
class PostPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'coverUrl' => $post->cover?->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'author' => $post->author?->name,
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'coverUrl' => $post->cover?->url(),
            'gallery' => $post->galleryMedia
                ->map(fn ($m) => ['id' => $m->id, 'url' => $m->url(), 'alt' => $m->alt_text])
                ->values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function related(Post $post, int $limit = 3): Collection
    {
        return $post->relatedPosts()
            ->take($limit)
            ->map(fn (Post $p) => $this->relatedItem($p));
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedItem(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'coverUrl' => $post->cover?->url(),
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
        ];
    }
}

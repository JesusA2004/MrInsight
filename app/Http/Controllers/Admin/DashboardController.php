<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totals = DB::table('posts')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $postsByMonth = Post::query()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn (Post $post) => $post->created_at !== null ? $post->created_at->format('Y-m') : 'desconocido')
            ->map(fn ($group) => $group->count())
            ->sortKeys()
            ->map(fn (int $total, string $month) => ['month' => $month, 'total' => $total])
            ->values();

        $postsByCategory = DB::table('posts')
            ->join('categories', 'categories.id', '=', 'posts.category_id')
            ->selectRaw('categories.name as category, categories.color as color, count(*) as total')
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->get();

        $mediaCount = Media::query()->count();
        $mediaBytes = (int) Media::query()->sum('size');

        $recentPosts = Post::query()
            ->with(['category', 'author'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalPosts' => Post::query()->count(),
                'published' => (int) ($totals[PostStatus::Published->value] ?? 0),
                'drafts' => (int) ($totals[PostStatus::Draft->value] ?? 0),
                'scheduled' => (int) ($totals[PostStatus::Scheduled->value] ?? 0),
                'archived' => (int) ($totals[PostStatus::Archived->value] ?? 0),
                'featured' => Post::query()->where('is_featured', true)->count(),
                'mediaCount' => $mediaCount,
                'mediaBytes' => $mediaBytes,
            ],
            'charts' => [
                'postsByMonth' => $postsByMonth,
                'postsByCategory' => $postsByCategory,
            ],
            'recentPosts' => $recentPosts->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'category' => $post->category?->name,
                'author' => $post->author?->name,
                'createdAt' => $post->created_at?->toIso8601String(),
            ]),
        ]);
    }
}

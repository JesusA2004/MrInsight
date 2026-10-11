<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Services\PostPresenter;
use App\Services\PublicSiteDataService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(PublicSiteDataService $siteData, PostPresenter $presenter): Response
    {
        $categories = Category::query()->where('status', 'active')->orderBy('order')->get();

        $empiezaAqui = $categories->map(function (Category $category) use ($presenter) {
            $post = Post::query()
                ->where('category_id', $category->id)
                ->published()
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->with('cover')
                ->first();

            return [
                'category' => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'description' => $category->description,
                ],
                'post' => $post ? $presenter->summary($post) : null,
            ];
        })->values();

        $latestPosts = Post::query()
            ->published()
            ->with(['category', 'cover'])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get()
            ->map(fn (Post $post) => $presenter->summary($post));

        $historiaPage = Page::query()->where('key', 'mi-historia')->first();
        $procesoPage = Page::query()->where('key', 'mi-proceso')->first();

        $hero = SiteSetting::get('hero', []);
        $heroPhoto = ! empty($hero['photo_media_id'])
            ? Media::query()->find((int) $hero['photo_media_id'])
            : null;

        return Inertia::render('public/Home', [
            ...$siteData->base(),
            'hero' => [
                'eyebrow' => $hero['eyebrow'] ?? '',
                'titleLines' => $hero['title_lines'] ?? [],
                'primaryCtaLabel' => $hero['primary_cta_label'] ?? '',
                'secondaryCtaLabel' => $hero['secondary_cta_label'] ?? '',
                'tertiaryLinkLabel' => $hero['tertiary_link_label'] ?? '',
                'photoUrl' => $heroPhoto?->url(),
            ],
            'empiezaAqui' => [
                'title' => SiteSetting::get('empieza_aqui', [])['title'] ?? 'CONOCE MI PERSPECTIVA.',
                'items' => $empiezaAqui,
            ],
            'tips' => [
                'title' => SiteSetting::get('tips_section', [])['title'] ?? 'IDEAS QUE DEJAN HUELLA.',
                'posts' => $latestPosts,
            ],
            'historia' => [
                'title' => SiteSetting::get('historia_preview', [])['title'] ?? 'MI HISTORIA',
                'intro' => $historiaPage !== null ? ($historiaPage->intro ?? '') : '',
                'coverUrl' => $historiaPage?->cover?->url(),
            ],
            'proceso' => [
                'title' => SiteSetting::get('proceso_preview', [])['title'] ?? '365 DÍAS DE CUIDAR DE MÍ.',
                'hasContent' => $procesoPage !== null,
            ],
        ]);
    }
}

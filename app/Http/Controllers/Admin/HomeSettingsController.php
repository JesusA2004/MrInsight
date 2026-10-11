<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSettingsRequest;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Services\PostPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeSettingsController extends Controller
{
    public function edit(PostPresenter $presenter): Response
    {
        abort_unless(request()->user()?->canManagePosts(), 403);

        $hero = SiteSetting::get('hero', []);
        $heroPhoto = ! empty($hero['photo_media_id']) ? Media::query()->find((int) $hero['photo_media_id']) : null;

        $historia = SiteSetting::get('historia_preview', []);
        $historiaPhoto = ! empty($historia['photo_media_id']) ? Media::query()->find((int) $historia['photo_media_id']) : null;

        $categories = Category::query()->where('status', 'active')->orderBy('order')->get();
        $preview = $categories->map(function (Category $category) use ($presenter) {
            $post = Post::query()
                ->where('category_id', $category->id)
                ->published()
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->with('cover')
                ->first();

            return [
                'category' => $category->name,
                'post' => $post ? $presenter->summary($post) : null,
            ];
        });

        return Inertia::render('admin/inicio/Form', [
            'hero' => [
                'eyebrow' => $hero['eyebrow'] ?? '',
                'title_lines' => $hero['title_lines'] ?? [],
                'primary_cta_label' => $hero['primary_cta_label'] ?? '',
                'secondary_cta_label' => $hero['secondary_cta_label'] ?? '',
                'tertiary_link_label' => $hero['tertiary_link_label'] ?? '',
                'photo' => $heroPhoto ? ['id' => $heroPhoto->id, 'url' => $heroPhoto->url()] : null,
            ],
            'empiezaAqui' => SiteSetting::get('empieza_aqui', ['title' => '']),
            'tipsSection' => SiteSetting::get('tips_section', ['title' => '', 'posts_per_page' => 9]),
            'historiaPreview' => [
                'title' => $historia['title'] ?? '',
                'photo' => $historiaPhoto ? ['id' => $historiaPhoto->id, 'url' => $historiaPhoto->url()] : null,
            ],
            'procesoPreview' => SiteSetting::get('proceso_preview', ['title' => '']),
            'footer' => SiteSetting::get('footer', ['tagline' => '']),
            'sections' => HomepageSection::query()->orderBy('order')->get(['section_key', 'is_visible', 'order']),
            'empiezaAquiPreview' => $preview,
        ]);
    }

    public function update(HomeSettingsRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validated();

        SiteSetting::set('hero', $data['hero']);
        SiteSetting::set('empieza_aqui', $data['empieza_aqui']);
        SiteSetting::set('tips_section', $data['tips_section']);
        SiteSetting::set('historia_preview', $data['historia_preview']);
        SiteSetting::set('proceso_preview', $data['proceso_preview']);
        SiteSetting::set('footer', $data['footer']);

        foreach ($data['sections'] as $section) {
            HomepageSection::query()->where('section_key', $section['section_key'])->update([
                'is_visible' => $section['is_visible'] ?? true,
                'order' => $section['order'] ?? 0,
            ]);
        }

        $activity->log('inicio.actualizado');

        return back()->with('success', 'Inicio actualizado.');
    }
}

<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Agrega los datos comunes que necesita cualquier página del sitio público:
 * identidad del sitio, navegación, categorías activas y redes sociales
 * visibles. Se usa desde los controladores públicos para no repetir
 * consultas en cada página.
 */
class PublicSiteDataService
{
    /**
     * @return array<string, mixed>
     */
    public function base(): array
    {
        return [
            'site' => $this->identity(),
            'categories' => $this->categories(),
            'social' => $this->socialLinks(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        $site = ThemeSetting::get('site', ['name' => 'MR. INSIGHT', 'subtitle' => 'POR ZURIEL ÁVILA']);
        $logo = ThemeSetting::get('logo', []);
        $footer = SiteSetting::get('footer', ['tagline' => '']);

        return [
            'name' => $site['name'] ?? 'MR. INSIGHT',
            'subtitle' => $site['subtitle'] ?? '',
            'logoMediaId' => $logo['logo_media_id'] ?? null,
            'footerTagline' => $footer['tagline'] ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return Cache::remember('public.categories.active', 300, function () {
            return Category::query()
                ->where('status', 'active')
                ->orderBy('order')
                ->get(['id', 'name', 'slug', 'color', 'icon', 'description'])
                ->toArray();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function socialLinks(): array
    {
        return Cache::remember('public.social_links.visible', 300, function () {
            return SocialLink::query()
                ->visible()
                ->get(['platform', 'username', 'url', 'icon', 'open_new_tab'])
                ->toArray();
        });
    }
}

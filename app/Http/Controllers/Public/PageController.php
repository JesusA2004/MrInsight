<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageEntry;
use App\Services\PublicSiteDataService;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function historia(PublicSiteDataService $siteData): Response
    {
        return $this->renderPage('mi-historia', 'public/MiHistoria', $siteData);
    }

    public function proceso(PublicSiteDataService $siteData): Response
    {
        $page = Page::query()->where('key', 'mi-proceso')->firstOrFail();
        $entries = $page->entries()->where('is_visible', true)->with('cover')->get();

        return Inertia::render('public/MiProceso', [
            ...$siteData->base(),
            'page' => $this->pageData($page),
            'entries' => $entries->map(fn (PageEntry $entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'date' => $entry->entry_date?->toIso8601String(),
                'coverUrl' => $entry->cover?->url(),
            ]),
        ]);
    }

    public function privacidad(PublicSiteDataService $siteData): Response
    {
        return $this->renderPage('aviso-privacidad', 'public/AvisoPrivacidad', $siteData);
    }

    private function renderPage(string $key, string $component, PublicSiteDataService $siteData): Response
    {
        $page = Page::query()->where('key', $key)->firstOrFail();

        return Inertia::render($component, [
            ...$siteData->base(),
            'page' => $this->pageData($page),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Page $page): array
    {
        return [
            'title' => $page->title,
            'intro' => $page->intro,
            'body' => $page->body,
            'coverUrl' => $page->cover?->url(),
            'seoTitle' => $page->seo_title,
            'seoDescription' => $page->seo_description,
        ];
    }
}

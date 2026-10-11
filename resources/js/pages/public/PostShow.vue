<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PostBody from '@/components/public/PostBody.vue';
import type { TiptapContent } from '@/types';

interface GalleryItem {
    id: number;
    url: string | null;
    alt: string | null;
}

interface PostDetail {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: TiptapContent | null;
    publishedAt: string | null;
    author: string | null;
    category: { name: string; slug: string; color: string } | null;
    coverUrl: string | null;
    gallery: GalleryItem[];
}

interface RelatedPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    coverUrl: string | null;
    category: { name: string; slug: string; color: string } | null;
}

withDefaults(
    defineProps<{
        post: PostDetail;
        related: RelatedPost[];
        previewing?: boolean;
    }>(),
    { previewing: false },
);

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
}
</script>

<template>
    <Head :title="post.title" />

    <div
        v-if="previewing"
        class="sticky top-20 z-30 bg-gold py-2 text-center text-sm font-medium text-ink"
    >
        Modo vista previa — esta publicación aún no es visible al público.
    </div>

    <article>
        <header class="border-b border-white/10 py-16">
            <div class="container-editorial max-w-3xl">
                <Link
                    v-if="post.category"
                    :href="`/tips?categoria=${post.category.slug}`"
                    class="text-sm font-medium tracking-[0.2em] uppercase"
                    :style="{ color: post.category.color }"
                >
                    {{ post.category.name }}
                </Link>
                <p class="mt-3 text-sm text-mist">
                    {{ formatDate(post.publishedAt) }} · {{ post.author }}
                </p>
                <h1 class="text-headline mt-4 font-display text-paper">
                    {{ post.title }}
                </h1>
                <p
                    v-if="post.excerpt"
                    class="text-editorial-body mt-6 text-mist"
                >
                    {{ post.excerpt }}
                </p>
            </div>
        </header>

        <div v-if="post.coverUrl" class="container-editorial mt-10 max-w-4xl">
            <div
                class="aspect-[16/9] w-full overflow-hidden rounded-2xl bg-ink-soft"
            >
                <img
                    :src="post.coverUrl"
                    :alt="post.title"
                    class="size-full object-cover"
                />
            </div>
        </div>

        <div class="container-editorial my-14 max-w-3xl">
            <PostBody :content="post.body" />

            <div
                v-if="post.gallery.length > 0"
                class="mt-12 grid gap-4 sm:grid-cols-2"
            >
                <div
                    v-for="item in post.gallery"
                    :key="item.id"
                    class="aspect-square overflow-hidden rounded-xl bg-ink-soft"
                >
                    <img
                        v-if="item.url"
                        :src="item.url"
                        :alt="item.alt ?? ''"
                        class="size-full object-cover"
                    />
                </div>
            </div>
        </div>

        <section v-if="related.length" class="border-t border-white/10 py-16">
            <div class="container-editorial">
                <h2 class="font-display text-2xl text-paper">
                    También te puede interesar
                </h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-3">
                    <Link
                        v-for="item in related"
                        :key="item.id"
                        :href="`/tips/${item.slug}`"
                        class="group flex flex-col overflow-hidden rounded-xl border border-white/10 bg-ink-soft"
                    >
                        <div
                            class="aspect-[16/10] w-full overflow-hidden bg-ink"
                        >
                            <img
                                v-if="item.coverUrl"
                                :src="item.coverUrl"
                                :alt="item.title"
                                class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <div class="p-5">
                            <span
                                v-if="item.category"
                                class="text-xs font-medium tracking-[0.2em] uppercase"
                                :style="{ color: item.category.color }"
                            >
                                {{ item.category.name }}
                            </span>
                            <p class="mt-2 font-display text-base text-paper">
                                {{ item.title }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>
    </article>
</template>

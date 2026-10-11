<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { CategorySummary, PostSummary } from '@/types';

defineProps<{
    categories: CategorySummary[];
    activeCategory: string;
    posts: {
        data: PostSummary[];
        currentPage: number;
        lastPage: number;
        nextPageUrl: string | null;
        prevPageUrl: string | null;
        total: number;
    };
}>();

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
}

function filterHref(slug: string): string {
    return slug === 'todos' ? '/tips' : `/tips?categoria=${slug}`;
}
</script>

<template>
    <Head title="Tips" />

    <section class="border-b border-white/10 py-16">
        <div class="container-editorial">
            <p class="text-sm font-medium tracking-[0.3em] text-gold">TIPS</p>
            <h1 class="text-headline mt-4 font-display text-paper">
                IDEAS QUE DEJAN HUELLA.
            </h1>

            <div class="mt-8 flex flex-wrap gap-3">
                <Link
                    :href="filterHref('todos')"
                    class="flex h-11 items-center rounded-full border px-5 text-sm tracking-wide uppercase transition-colors"
                    :class="
                        activeCategory === 'todos'
                            ? 'border-gold bg-gold text-ink'
                            : 'border-white/20 text-paper/80 hover:border-gold hover:text-gold'
                    "
                >
                    Todos
                </Link>
                <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="filterHref(category.slug)"
                    class="flex h-11 items-center rounded-full border px-5 text-sm tracking-wide uppercase transition-colors"
                    :class="
                        activeCategory === category.slug
                            ? 'border-gold bg-gold text-ink'
                            : 'border-white/20 text-paper/80 hover:border-gold hover:text-gold'
                    "
                >
                    {{ category.name }}
                </Link>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="container-editorial">
            <div
                v-if="posts.data.length"
                class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="post in posts.data"
                    :key="post.id"
                    :href="`/tips/${post.slug}`"
                    class="group flex flex-col overflow-hidden rounded-xl border border-white/10 bg-ink-soft"
                >
                    <div class="aspect-[16/10] w-full overflow-hidden bg-ink">
                        <img
                            v-if="post.coverUrl"
                            :src="post.coverUrl"
                            :alt="post.title"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <span
                            v-if="post.category"
                            class="text-xs font-medium tracking-[0.2em] uppercase"
                            :style="{ color: post.category.color }"
                        >
                            {{ post.category.name }}
                        </span>
                        <p class="mt-2 font-display text-lg text-paper">
                            {{ post.title }}
                        </p>
                        <p
                            v-if="post.excerpt"
                            class="mt-2 line-clamp-2 text-sm text-mist"
                        >
                            {{ post.excerpt }}
                        </p>
                        <p class="mt-4 text-xs text-mist">
                            {{ formatDate(post.publishedAt) }}
                        </p>
                    </div>
                </Link>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-white/15 p-16 text-center text-mist"
            >
                Todavía no hay tips publicados en esta categoría.
            </div>

            <div
                v-if="posts.lastPage > 1"
                class="mt-12 flex items-center justify-between"
            >
                <Link
                    v-if="posts.prevPageUrl"
                    :href="posts.prevPageUrl"
                    class="flex h-11 items-center rounded-md border border-white/20 px-5 text-sm text-paper hover:border-gold hover:text-gold"
                >
                    ← Anteriores
                </Link>
                <span v-else />
                <span class="text-sm text-mist"
                    >Página {{ posts.currentPage }} de
                    {{ posts.lastPage }}</span
                >
                <Link
                    v-if="posts.nextPageUrl"
                    :href="posts.nextPageUrl"
                    class="flex h-11 items-center rounded-md border border-white/20 px-5 text-sm text-paper hover:border-gold hover:text-gold"
                >
                    Siguientes →
                </Link>
                <span v-else />
            </div>
        </div>
    </section>
</template>

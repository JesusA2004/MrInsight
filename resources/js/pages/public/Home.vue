<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CategorySummary, PostSummary, SocialLinkSummary } from '@/types';

interface HeroData {
    eyebrow: string;
    titleLines: string[];
    primaryCtaLabel: string;
    secondaryCtaLabel: string;
    tertiaryLinkLabel: string;
    photoUrl: string | null;
}

interface EmpiezaAquiItem {
    category: Pick<
        CategorySummary,
        'name' | 'slug' | 'color' | 'icon' | 'description'
    >;
    post: PostSummary | null;
}

const props = defineProps<{
    social: SocialLinkSummary[];
    hero: HeroData;
    empiezaAqui: { title: string; items: EmpiezaAquiItem[] };
    tips: { title: string; posts: PostSummary[] };
    historia: { title: string; intro: string; coverUrl: string | null };
    proceso: { title: string; hasContent: boolean };
}>();

const instagram = computed(() =>
    props.social.find((s) => s.platform === 'instagram'),
);
const tiktok = computed(() =>
    props.social.find((s) => s.platform === 'tiktok'),
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
    <Head title="Inicio" />

    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div
            class="container-editorial grid gap-12 py-16 lg:grid-cols-2 lg:items-center lg:py-28"
        >
            <div>
                <p class="text-sm font-medium tracking-[0.3em] text-gold">
                    {{ hero.eyebrow }}
                </p>
                <h1 class="text-headline mt-4 font-display text-paper">
                    <span
                        v-for="line in hero.titleLines"
                        :key="line"
                        class="block"
                        >{{ line }}</span
                    >
                </h1>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a
                        v-if="instagram && hero.primaryCtaLabel"
                        :href="instagram.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-[50px] items-center rounded-md bg-gold px-6 text-sm font-medium text-ink transition-colors hover:bg-gold-soft"
                    >
                        {{ hero.primaryCtaLabel }}
                    </a>
                    <Link
                        v-if="hero.secondaryCtaLabel"
                        href="/tips"
                        class="inline-flex h-[50px] items-center rounded-md border border-white/20 px-6 text-sm font-medium text-paper transition-colors hover:border-gold hover:text-gold"
                    >
                        {{ hero.secondaryCtaLabel }}
                    </Link>
                </div>
                <a
                    v-if="tiktok && hero.tertiaryLinkLabel"
                    :href="tiktok.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 inline-block text-sm text-mist underline-offset-4 hover:text-gold hover:underline"
                >
                    {{ hero.tertiaryLinkLabel }}
                </a>
            </div>

            <div
                class="relative aspect-[4/5] w-full overflow-hidden rounded-2xl bg-ink-soft lg:aspect-[3/4]"
            >
                <img
                    v-if="hero.photoUrl"
                    :src="hero.photoUrl"
                    alt="Zuriel Ávila"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-2 border border-dashed border-white/15 p-8 text-center"
                >
                    <span class="text-xs tracking-widest text-mist uppercase"
                        >Fotografía pendiente</span
                    >
                    <p class="text-sm text-mist">
                        Se administra desde /admin/inicio.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Empieza aquí -->
    <section class="border-t border-white/10 py-20">
        <div class="container-editorial">
            <h2 class="font-display text-2xl text-paper sm:text-3xl">
                {{ empiezaAqui.title }}
            </h2>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <Link
                    v-for="item in empiezaAqui.items"
                    :key="item.category.slug"
                    :href="
                        item.post
                            ? `/tips/${item.post.slug}`
                            : `/tips?categoria=${item.category.slug}`
                    "
                    class="group flex flex-col justify-between rounded-xl border border-white/10 bg-ink-soft p-6 transition-colors hover:border-gold/50"
                >
                    <div>
                        <span
                            class="text-xs font-medium tracking-[0.2em] uppercase"
                            :style="{ color: item.category.color }"
                        >
                            {{ item.category.name }}
                        </span>
                        <p class="mt-3 font-display text-lg text-paper">
                            {{
                                item.post
                                    ? item.post.title
                                    : item.category.description
                            }}
                        </p>
                        <p
                            v-if="item.post?.excerpt"
                            class="mt-2 text-sm text-mist"
                        >
                            {{ item.post.excerpt }}
                        </p>
                        <p
                            v-else-if="!item.post"
                            class="mt-2 text-sm text-mist"
                        >
                            Aún no hay un tip destacado en esta categoría.
                        </p>
                    </div>
                    <span
                        class="mt-6 text-sm text-gold opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        Leer más →
                    </span>
                </Link>
            </div>
        </div>
    </section>

    <!-- Explora los tips -->
    <section class="border-t border-white/10 py-20">
        <div class="container-editorial">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="font-display text-2xl text-paper sm:text-3xl">
                    {{ tips.title }}
                </h2>
                <Link href="/tips" class="text-sm text-gold hover:underline"
                    >Ver todos los tips →</Link
                >
            </div>

            <div
                v-if="tips.posts.length"
                class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="post in tips.posts"
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
                class="mt-10 rounded-xl border border-dashed border-white/15 p-10 text-center text-mist"
            >
                Todavía no hay tips publicados. Vuelve pronto.
            </div>
        </div>
    </section>

    <!-- Mi historia -->
    <section class="border-t border-white/10 py-20">
        <div
            class="container-editorial grid gap-10 lg:grid-cols-2 lg:items-center"
        >
            <div
                class="order-2 aspect-[4/3] w-full overflow-hidden rounded-2xl bg-ink-soft lg:order-1"
            >
                <img
                    v-if="historia.coverUrl"
                    :src="historia.coverUrl"
                    alt=""
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center text-sm text-mist"
                >
                    Fotografía pendiente
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <h2 class="font-display text-2xl text-paper sm:text-3xl">
                    {{ historia.title }}
                </h2>
                <p
                    v-if="historia.intro"
                    class="text-editorial-body mt-4 text-mist"
                >
                    {{ historia.intro }}
                </p>
                <Link
                    href="/mi-historia"
                    class="mt-6 inline-flex h-[50px] items-center rounded-md border border-white/20 px-6 text-sm font-medium text-paper transition-colors hover:border-gold hover:text-gold"
                >
                    Leer la historia completa
                </Link>
            </div>
        </div>
    </section>

    <!-- Mi proceso -->
    <section class="border-t border-white/10 py-20">
        <div
            class="container-editorial flex flex-col items-start gap-6 rounded-2xl border border-white/10 bg-ink-soft p-10 sm:flex-row sm:items-center sm:justify-between"
        >
            <h2 class="font-display text-2xl text-paper sm:text-3xl">
                {{ proceso.title }}
            </h2>
            <Link
                href="/mi-proceso"
                class="inline-flex h-[50px] shrink-0 items-center rounded-md bg-gold px-6 text-sm font-medium text-ink transition-colors hover:bg-gold-soft"
            >
                Ver el proceso
            </Link>
        </div>
    </section>
</template>

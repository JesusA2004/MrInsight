<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Clock, Link2, Share2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import EditorialCard from '@/components/blocks/shared/EditorialCard.vue';
import PostBody from '@/components/public/PostBody.vue';
import type { PostSummary, TiptapContent } from '@/types';

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

const props = withDefaults(
    defineProps<{
        post: PostDetail;
        related: Omit<PostSummary, 'publishedAt'>[];
        previewing?: boolean;
    }>(),
    { previewing: false },
);

const date = computed(() =>
    props.post.publishedAt
        ? new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(props.post.publishedAt))
        : '',
);

/** Minutos de lectura estimados a partir del texto del documento Tiptap. */
const minutes = computed(() => {
    let words = 0;
    const walk = (node: unknown) => {
        if (!node || typeof node !== 'object') return;
        const n = node as { text?: string; content?: unknown[] };
        if (typeof n.text === 'string') words += n.text.split(/\s+/).filter(Boolean).length;
        n.content?.forEach(walk);
    };
    walk(props.post.body);

    return Math.max(1, Math.round(words / 220));
});

const gallery = computed(() => props.post.gallery.filter((g) => g.url));
const relatedPosts = computed<PostSummary[]>(() => props.related.map((r) => ({ ...r, publishedAt: null })));

// Barra de progreso de lectura.
const progress = ref(0);
const article = ref<HTMLElement | null>(null);
let raf = 0;
function onScroll() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
        const el = article.value;
        if (!el) return;
        const r = el.getBoundingClientRect();
        const total = r.height - window.innerHeight;
        progress.value = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;
    });
}

const copied = ref(false);
const canShare = ref(false);
async function share() {
    const url = window.location.href;
    if (navigator.share) {
        try {
            await navigator.share({ title: props.post.title, url });
        } catch {
            /* cancelado por el usuario */
        }

        return;
    }
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

onMounted(() => {
    canShare.value = typeof navigator.share === 'function';
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <Head :title="post.title">
        <meta v-if="post.excerpt" head-key="description" name="description" :content="post.excerpt" />
        <meta v-if="previewing" head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="fixed inset-x-0 top-0 z-50 h-0.5 origin-left bg-gold"
        :style="{ transform: `scaleX(${progress})` }"
        aria-hidden="true"
    />

    <div v-if="previewing" class="bg-gold py-2 text-center text-sm font-semibold text-ink" role="status">
        Modo vista previa — esta publicación aún no es visible al público.
    </div>

    <article ref="article" class="tone-ink">
        <header class="pt-16 pb-12 lg:pt-24">
            <div class="container-editorial container-editorial--narrow text-center">
                <Link
                    v-if="post.category"
                    :href="`/tips?categoria=${post.category.slug}`"
                    class="inline-flex min-h-9 items-center gap-2 rounded-full border border-line px-4 text-[12px] font-semibold tracking-[0.18em] uppercase transition-colors hover:border-gold"
                    :style="{ color: post.category.color }"
                >
                    <span class="size-1.5 rounded-full" :style="{ background: post.category.color }" aria-hidden="true" />
                    {{ post.category.name }}
                </Link>
                <h1 class="type-display-lg mt-7 text-balance text-paper">{{ post.title }}</h1>
                <p v-if="post.excerpt" class="type-lead mx-auto mt-6 max-w-2xl text-paper/75">{{ post.excerpt }}</p>
                <p class="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-mist">
                    <span v-if="post.author">Por <span class="text-paper">{{ post.author }}</span></span>
                    <span v-if="date" aria-hidden="true">·</span>
                    <time v-if="post.publishedAt" :datetime="post.publishedAt">{{ date }}</time>
                    <span aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1.5"><Clock class="size-3.5" aria-hidden="true" /> {{ minutes }} min de lectura</span>
                </p>
            </div>
        </header>

        <div v-if="post.coverUrl" class="container-editorial container-editorial--wide">
            <figure class="overflow-hidden rounded-[var(--editorial-radius-card-lg)] bg-ink-soft">
                <img :src="post.coverUrl" :alt="post.title" class="aspect-[4/3] w-full object-cover sm:aspect-[16/8]" fetchpriority="high" />
            </figure>
        </div>

        <div class="container-editorial container-editorial--narrow py-14 lg:py-20">
            <PostBody :content="post.body" class="post-article" />

            <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-8">
                <Link href="/tips" class="btn-editorial btn-editorial--link">← Más tips</Link>
                <button type="button" class="btn-editorial btn-editorial--secondary min-h-11" @click="share">
                    <Check v-if="copied" class="size-4" aria-hidden="true" />
                    <Share2 v-else-if="canShare" class="size-4" aria-hidden="true" />
                    <Link2 v-else class="size-4" aria-hidden="true" />
                    {{ copied ? 'Enlace copiado' : canShare ? 'Compartir' : 'Copiar enlace' }}
                </button>
            </div>
        </div>

        <section v-if="gallery.length" class="pb-20" aria-label="Galería">
            <div class="container-editorial">
                <div class="sn-gallery" :class="gallery.length >= 4 ? 'sn-gallery--bento' : 'sn-gallery--grid'">
                    <figure v-for="item in gallery" :key="item.id" class="sn-gallery-item">
                        <div class="sn-frame img-zoom"><img :src="item.url!" :alt="item.alt ?? ''" loading="lazy" decoding="async" /></div>
                    </figure>
                </div>
            </div>
        </section>

        <section v-if="relatedPosts.length" class="sn-section tone-ink-soft spacing-balanced">
            <div class="sn-container">
                <header class="sn-section-header">
                    <div>
                        <p class="sn-eyebrow type-eyebrow">Sigue leyendo</p>
                        <h2 class="sn-heading type-display-md">También te puede interesar</h2>
                    </div>
                </header>
                <div class="sn-posts-grid">
                    <EditorialCard v-for="item in relatedPosts" :key="item.id" :post="item" :show-date="false" />
                </div>
            </div>
        </section>
    </article>
</template>

<style>
.post-article .post-body > div > p:first-of-type::first-letter,
.post-article > div > p:first-of-type::first-letter {
    float: left;
    font-family: var(--font-display);
    font-size: 4.2em;
    line-height: 0.85;
    padding: 0.08em 0.12em 0 0;
    color: var(--color-gold);
}
.post-article p {
    font-size: clamp(1.0625rem, 1rem + 0.25vw, 1.1875rem);
    line-height: 1.8;
}
.post-article :is(h2, h3) {
    font-size: clamp(1.5rem, 1.3rem + 0.8vw, 2rem);
    line-height: 1.2;
}
</style>

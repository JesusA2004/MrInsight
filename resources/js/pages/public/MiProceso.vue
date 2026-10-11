<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    page: {
        title: string;
        intro: string | null;
        coverUrl: string | null;
    };
    entries: {
        id: number;
        title: string;
        date: string | null;
        coverUrl: string | null;
    }[];
}>();

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
    <Head :title="page.title" />

    <section class="py-16">
        <div class="container-editorial max-w-3xl">
            <h1 class="text-headline font-display text-paper">
                {{ page.title.toUpperCase() }}
            </h1>
            <p v-if="page.intro" class="text-editorial-body mt-6 text-mist">
                {{ page.intro }}
            </p>
        </div>
    </section>

    <section class="border-t border-white/10 py-16">
        <div class="container-editorial max-w-3xl">
            <div v-if="entries.length" class="flex flex-col gap-8">
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    class="flex gap-6 border-b border-white/10 pb-8 last:border-b-0"
                >
                    <div
                        class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-ink-soft"
                    >
                        <img
                            v-if="entry.coverUrl"
                            :src="entry.coverUrl"
                            :alt="entry.title"
                            class="size-full object-cover"
                        />
                    </div>
                    <div>
                        <p class="text-xs tracking-widest text-gold uppercase">
                            {{ formatDate(entry.date) }}
                        </p>
                        <p class="mt-1 font-display text-lg text-paper">
                            {{ entry.title }}
                        </p>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-white/15 p-10 text-center text-mist"
            >
                Las entradas de este proceso se publicarán próximamente. Se
                administran desde /admin/paginas.
            </div>
        </div>
    </section>
</template>

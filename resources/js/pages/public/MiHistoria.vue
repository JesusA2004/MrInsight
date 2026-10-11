<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    page: {
        title: string;
        intro: string | null;
        coverUrl: string | null;
    };
}>();
</script>

<template>
    <Head :title="page.title" />

    <section class="py-16">
        <div class="container-editorial max-w-3xl">
            <h1 class="text-headline font-display text-paper">
                {{ page.title.toUpperCase() }}
            </h1>

            <div
                v-if="page.coverUrl"
                class="mt-10 aspect-[16/9] w-full overflow-hidden rounded-2xl bg-ink-soft"
            >
                <img
                    :src="page.coverUrl"
                    :alt="page.title"
                    class="size-full object-cover"
                />
            </div>

            <p v-if="page.intro" class="text-editorial-body mt-10 text-mist">
                {{ page.intro }}
            </p>
            <div
                v-else
                class="mt-10 rounded-xl border border-dashed border-white/15 p-10 text-center text-mist"
            >
                Esta historia todavía se está escribiendo. Se administra desde
                /admin/paginas.
            </div>
        </div>
    </section>
</template>

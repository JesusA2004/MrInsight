<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SocialIcon from '@/components/public/SocialIcon.vue';
import type { SocialLinkSummary } from '@/types';

defineProps<{
    social: SocialLinkSummary[];
}>();
</script>

<template>
    <Head title="Redes" />

    <section class="py-16">
        <div class="container-editorial max-w-3xl">
            <p class="text-sm font-medium tracking-[0.3em] text-gold">REDES</p>
            <h1 class="text-headline mt-4 font-display text-paper">
                SÍGUEME DONDE PREFIERAS.
            </h1>

            <div v-if="social.length" class="mt-12 flex flex-col gap-4">
                <a
                    v-for="link in social"
                    :key="link.platform"
                    :href="link.url"
                    :target="link.open_new_tab ? '_blank' : undefined"
                    rel="noopener noreferrer"
                    class="flex items-center gap-4 rounded-xl border border-white/10 bg-ink-soft p-5 transition-colors hover:border-gold/50"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-white/5 text-gold"
                    >
                        <SocialIcon :platform="link.platform" />
                    </span>
                    <span>
                        <span
                            class="block font-display text-base text-paper capitalize"
                            >{{ link.platform }}</span
                        >
                        <span
                            v-if="link.username"
                            class="block text-sm text-mist"
                            >{{ link.username }}</span
                        >
                    </span>
                </a>
            </div>
            <div
                v-else
                class="mt-12 rounded-xl border border-dashed border-white/15 p-10 text-center text-mist"
            >
                Aún no hay redes sociales configuradas.
            </div>
        </div>
    </section>
</template>

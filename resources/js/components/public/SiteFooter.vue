<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SocialIcon from '@/components/public/SocialIcon.vue';
import type { SiteIdentity, SocialLinkSummary } from '@/types';

defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
}>();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-white/10 bg-ink">
        <div class="container-editorial flex flex-col gap-8 py-14">
            <div
                class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <p class="font-display text-xl text-paper">
                        {{ site.name }}
                    </p>
                    <p
                        v-if="site.footerTagline"
                        class="mt-2 max-w-md text-sm text-mist"
                    >
                        {{ site.footerTagline }}
                    </p>
                </div>

                <div v-if="social.length" class="flex items-center gap-5">
                    <a
                        v-for="link in social"
                        :key="link.platform"
                        :href="link.url"
                        :target="link.open_new_tab ? '_blank' : undefined"
                        rel="noopener noreferrer"
                        class="flex size-11 items-center justify-center text-mist transition-colors hover:text-gold"
                        :aria-label="link.platform"
                    >
                        <SocialIcon :platform="link.platform" />
                    </a>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-mist sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    © {{ year }} {{ site.name }}. Todos los derechos reservados.
                </p>
                <Link href="/aviso-de-privacidad" class="hover:text-gold"
                    >Aviso de privacidad</Link
                >
            </div>
        </div>
    </footer>
</template>

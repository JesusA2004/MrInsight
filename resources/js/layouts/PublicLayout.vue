<script setup lang="ts">
import SiteFooter from '@/components/public/SiteFooter.vue';
import SiteHeader from '@/components/public/SiteHeader.vue';
import type { SiteIdentity, SocialLinkSummary } from '@/types';

const { site, social } = defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
}>();
</script>

<template>
    <div class="min-h-screen bg-ink font-sans text-paper antialiased">
        <SiteHeader :site="site" :social="social" />
        <main>
            <slot />
        </main>
        <SiteFooter :site="site" :social="social" />
    </div>
</template>

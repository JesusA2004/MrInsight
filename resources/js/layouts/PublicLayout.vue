<script setup lang="ts">
import { watchEffect } from 'vue';
import SiteFooter from '@/components/public/SiteFooter.vue';
import SiteHeader from '@/components/public/SiteHeader.vue';
import type { SiteIdentity, SocialLinkSummary, ThemePayload } from '@/types';

const { site, social, theme } = defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
    theme?: ThemePayload;
}>();

const RADIUS_REM: Record<string, number> = { none: 0, sm: 0.25, md: 0.5, lg: 0.75, xl: 1, full: 9999 };

/**
 * Aplica la apariencia publicada (colores, tipografías, radios) como
 * variables CSS en :root. Así /admin/apariencia se refleja en el sitio
 * público de inmediato, sin recompilar Vite.
 */
watchEffect(() => {
    if (!theme) return;
    const root = document.documentElement.style;

    if (theme.colors?.background) root.setProperty('--color-ink', theme.colors.background);
    if (theme.colors?.foreground) root.setProperty('--color-paper', theme.colors.foreground);
    if (theme.colors?.accent) root.setProperty('--color-gold', theme.colors.accent);
    if (theme.colors?.muted) root.setProperty('--color-mist', theme.colors.muted);

    if (theme.fonts?.display) root.setProperty('--font-display', `"${theme.fonts.display}", ui-serif, Georgia, serif`);
    if (theme.fonts?.body) root.setProperty('--font-sans', `"${theme.fonts.body}", ui-sans-serif, system-ui, sans-serif`);

    const buttonRem = theme.buttons?.radius ? RADIUS_REM[theme.buttons.radius] : undefined;
    if (buttonRem !== undefined) {
        root.setProperty('--editorial-radius-button', buttonRem >= 9999 ? '9999px' : `${buttonRem}rem`);
    }

    const cardRem = theme.cards?.radius ? RADIUS_REM[theme.cards.radius] : undefined;
    if (cardRem !== undefined) {
        root.setProperty('--editorial-radius-card', `${cardRem}rem`);
        root.setProperty('--editorial-radius-card-lg', `${cardRem * 1.33}rem`);
    }

    if (theme.faviconUrl) {
        let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.href = theme.faviconUrl;
    }
});
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

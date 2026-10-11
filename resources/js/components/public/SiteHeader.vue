<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { SiteIdentity, SocialLinkSummary } from '@/types';

const { site, social } = defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
}>();

const mobileOpen = ref(false);

const navItems = [
    { label: 'Inicio', href: '/' },
    { label: 'Tips', href: '/tips' },
    { label: 'Mi historia', href: '/mi-historia' },
    { label: 'Redes', href: '/redes' },
];

const instagram = computed(() =>
    social.find((link) => link.platform === 'instagram'),
);
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-white/10 bg-ink/90 backdrop-blur-md"
    >
        <div class="container-editorial flex h-20 items-center justify-between">
            <Link href="/" class="flex items-center gap-3">
                <svg
                    viewBox="0 0 64 64"
                    class="size-9 shrink-0"
                    aria-hidden="true"
                >
                    <rect width="64" height="64" rx="14" fill="#0D0E10" />
                    <circle
                        cx="32"
                        cy="29"
                        r="14"
                        fill="none"
                        stroke="#C7AA7D"
                        stroke-width="3"
                    />
                    <circle cx="32" cy="29" r="4" fill="#C7AA7D" />
                    <line
                        x1="17"
                        y1="49"
                        x2="47"
                        y2="49"
                        stroke="#C7AA7D"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                </svg>
                <span class="flex flex-col leading-tight">
                    <span class="font-display text-lg text-paper">{{
                        site.name
                    }}</span>
                    <span
                        class="text-[10px] tracking-[0.2em] text-mist uppercase"
                        >{{ site.subtitle }}</span
                    >
                </span>
            </Link>

            <nav
                class="hidden items-center gap-8 lg:flex"
                aria-label="Navegación principal"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="text-sm tracking-wide text-paper/80 uppercase transition-colors hover:text-gold"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="hidden items-center gap-4 lg:flex">
                <a
                    v-if="instagram"
                    :href="instagram.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-[50px] items-center rounded-md bg-gold px-5 text-sm font-medium text-ink transition-colors hover:bg-gold-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
                >
                    Sígueme en Instagram
                </a>
            </div>

            <button
                type="button"
                class="-mr-2 flex size-11 items-center justify-center text-paper lg:hidden"
                aria-label="Abrir menú"
                @click="mobileOpen = true"
            >
                <Menu class="size-6" />
            </button>
        </div>
    </header>

    <Sheet v-model:open="mobileOpen">
        <SheetContent side="right" class="border-white/10 bg-ink text-paper">
            <SheetHeader>
                <SheetTitle class="font-display text-paper">{{
                    site.name
                }}</SheetTitle>
            </SheetHeader>
            <nav class="flex flex-col gap-1 px-4" aria-label="Navegación móvil">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex min-h-11 items-center text-base tracking-wide text-paper/90 uppercase hover:text-gold"
                    @click="mobileOpen = false"
                >
                    {{ item.label }}
                </Link>
            </nav>
            <div class="mt-auto flex flex-col gap-4 px-4 pb-6">
                <a
                    v-if="instagram"
                    :href="instagram.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex h-[50px] items-center justify-center rounded-md bg-gold px-5 text-sm font-medium text-ink"
                >
                    Sígueme en Instagram
                </a>
                <div
                    v-if="social.length"
                    class="flex items-center gap-4 text-mist"
                >
                    <a
                        v-for="link in social"
                        :key="link.platform"
                        :href="link.url"
                        :target="link.open_new_tab ? '_blank' : undefined"
                        rel="noopener noreferrer"
                        class="flex size-11 items-center justify-center hover:text-gold"
                        :aria-label="link.platform"
                    >
                        <SocialIcon :platform="link.platform" />
                    </a>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

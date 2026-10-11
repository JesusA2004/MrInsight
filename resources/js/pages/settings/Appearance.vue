<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tema',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tema" />

    <h1 class="sr-only">Tema del panel</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Tema del panel"
            description="Elige cómo se ve el panel administrador en este dispositivo (esto no afecta al sitio público)."
        />
        <AppearanceTabs />
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    page: {
        title: string;
        intro: string | null;
    };
}>();
</script>

<template>
    <Head :title="page.title" />

    <section class="py-16">
        <div class="container-editorial max-w-2xl">
            <h1 class="font-display text-3xl text-paper">{{ page.title }}</h1>
            <p v-if="page.intro" class="text-editorial-body mt-6 text-mist">
                {{ page.intro }}
            </p>
        </div>
    </section>
</template>

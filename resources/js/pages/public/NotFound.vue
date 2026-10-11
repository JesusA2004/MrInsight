<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Página no encontrada" />

    <section class="flex min-h-[70vh] items-center py-16">
        <div class="container-editorial max-w-xl text-center">
            <p class="text-sm font-medium tracking-[0.3em] text-gold">404</p>
            <h1 class="text-headline mt-4 font-display text-paper">
                ESTO NO ESTÁ AQUÍ.
            </h1>
            <p class="text-editorial-body mt-6 text-mist">
                La página que buscas no existe o fue movida. Sin tapujos:
                volvamos al inicio.
            </p>
            <Link
                href="/"
                class="mt-8 inline-flex h-[50px] items-center rounded-md bg-gold px-6 text-sm font-medium text-ink transition-colors hover:bg-gold-soft"
            >
                Volver al inicio
            </Link>
        </div>
    </section>
</template>

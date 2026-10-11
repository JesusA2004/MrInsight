<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import paginas from '@/routes/admin/paginas';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    pages: { key: string; label: string; title: string; updatedAt: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Páginas', href: paginas.index() },
        ] as BreadcrumbItem[],
    },
});
</script>

<template>
    <Head title="Páginas" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:max-w-2xl md:p-6">
        <h1 class="text-xl font-semibold">Páginas</h1>

        <Card v-for="page in pages" :key="page.key">
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>{{ page.label }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ page.title }}</p>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link :href="paginas.edit(page.key)">
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </Button>
            </CardHeader>
            <CardContent v-if="page.updatedAt" class="text-xs text-muted-foreground">
                Última actualización: {{ new Date(page.updatedAt).toLocaleDateString('es-MX') }}
            </CardContent>
        </Card>
    </div>
</template>

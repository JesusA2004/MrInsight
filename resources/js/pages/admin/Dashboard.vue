<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    type ChartData,
    type ChartOptions,
    Legend,
    LinearScale,
    Tooltip as ChartTooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { FilePlus, ImagePlus, ExternalLink, Home as HomeIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import inicio from '@/routes/admin/inicio';
import multimedia from '@/routes/admin/multimedia';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem } from '@/types';

ChartJS.register(BarElement, CategoryScale, LinearScale, ChartTooltip, Legend);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: '/admin' }] as BreadcrumbItem[],
    },
});

const props = defineProps<{
    stats: {
        totalPosts: number;
        published: number;
        drafts: number;
        scheduled: number;
        archived: number;
        featured: number;
        mediaCount: number;
        mediaBytes: number;
    };
    charts: {
        postsByMonth: { month: string; total: number }[];
        postsByCategory: { category: string; color: string; total: number }[];
    };
    recentPosts: {
        id: number;
        title: string;
        slug: string;
        status: string;
        category: string | null;
        author: string | null;
        createdAt: string | null;
    }[];
}>();

const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    scheduled: 'Programado',
    published: 'Publicado',
    archived: 'Archivado',
};

function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 MB';
    const mb = bytes / (1024 * 1024);

    return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${mb.toFixed(1)} MB`;
}

const barData = computed<ChartData<'bar'>>(() => ({
    labels: props.charts.postsByMonth.map((p) => p.month),
    datasets: [
        {
            label: 'Publicaciones creadas',
            data: props.charts.postsByMonth.map((p) => p.total),
            backgroundColor: '#C7AA7D',
            borderRadius: 6,
        },
    ],
}));

const barOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
};
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap gap-3">
            <Button as-child>
                <Link :href="publicaciones.create()">
                    <FilePlus class="size-4" />
                    Nueva publicación
                </Link>
            </Button>
            <Button as-child variant="outline">
                <Link :href="multimedia.index()">
                    <ImagePlus class="size-4" />
                    Subir multimedia
                </Link>
            </Button>
            <Button as-child variant="outline">
                <Link :href="inicio.edit()">
                    <HomeIcon class="size-4" />
                    Editar inicio
                </Link>
            </Button>
            <Button as-child variant="ghost">
                <a href="/" target="_blank" rel="noopener noreferrer">
                    <ExternalLink class="size-4" />
                    Ver sitio público
                </a>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Publicaciones totales</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.totalPosts
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Publicadas</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.published
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Borradores</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.drafts
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Programadas</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.scheduled
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Destacadas</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.featured
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Archivadas</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.archived
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Archivos multimedia</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    stats.mediaCount
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Espacio utilizado</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    formatBytes(stats.mediaBytes)
                }}</CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Publicaciones creadas por mes</CardTitle>
                </CardHeader>
                <CardContent class="h-64">
                    <Bar
                        v-if="charts.postsByMonth.length"
                        :data="barData"
                        :options="barOptions"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Sin datos suficientes todavía.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Por categoría</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div
                        v-if="charts.postsByCategory.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin publicaciones todavía.
                    </div>
                    <div
                        v-for="row in charts.postsByCategory"
                        :key="row.category"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="size-3 rounded-full"
                            :style="{ backgroundColor: row.color }"
                        />
                        <span class="flex-1 text-sm">{{ row.category }}</span>
                        <span class="text-sm font-medium">{{ row.total }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Últimas publicaciones</CardTitle>
                <Link
                    :href="publicaciones.index()"
                    class="text-sm text-primary hover:underline"
                    >Ver todas</Link
                >
            </CardHeader>
            <CardContent>
                <div
                    v-if="recentPosts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Aún no has creado ninguna publicación.
                    <Link
                        :href="publicaciones.create()"
                        class="text-primary hover:underline"
                        >Crea la primera</Link
                    >.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="post in recentPosts"
                        :key="post.id"
                        class="flex items-center justify-between gap-4 py-3"
                    >
                        <div class="min-w-0">
                            <Link
                                :href="publicaciones.edit(post.id)"
                                class="truncate font-medium hover:underline"
                            >
                                {{ post.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ post.category ?? 'Sin categoría' }} ·
                                {{ post.author }}
                            </p>
                        </div>
                        <Badge variant="outline">{{
                            statusLabels[post.status] ?? post.status
                        }}</Badge>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>

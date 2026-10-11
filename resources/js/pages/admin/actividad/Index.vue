<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import actividad from '@/routes/admin/actividad';
import type { BreadcrumbItem } from '@/types';

interface LogRow {
    id: number;
    userName: string;
    actionKey: string;
    actionLabel: string;
    subjectLabel: string | null;
    subjectId: number | null;
    summary: string[];
    result: string;
    createdAt: string | null;
}

const props = defineProps<{
    logs: { data: LogRow[]; currentPage: number; lastPage: number; total: number };
    filters: { usuario?: string; accion?: string; desde?: string; hasta?: string; q?: string };
    actions: { value: string; label: string }[];
    users: { value: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Actividad', href: actividad.index() },
        ] as BreadcrumbItem[],
    },
});

const search = ref(props.filters.q ?? '');
const action = ref(props.filters.accion ?? '');
const user = ref(props.filters.usuario ?? '');
const desde = ref(props.filters.desde ?? '');
const hasta = ref(props.filters.hasta ?? '');

function applyFilters() {
    router.get(actividad.index().url, {
        q: search.value || undefined,
        accion: action.value || undefined,
        usuario: user.value || undefined,
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
    }, { preserveState: true, replace: true });
}

function goToPage(page: number) {
    router.get(actividad.index().url, { ...props.filters, page }, { preserveState: true });
}

function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}
</script>

<template>
    <Head title="Actividad" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Actividad</h1>
            <p class="text-sm text-muted-foreground">Bitácora de cambios realizados en el CMS.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-lg border p-3">
            <div class="grid gap-1.5">
                <Label class="text-xs">Buscar</Label>
                <div class="relative">
                    <Search class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" class="w-48 pl-8" placeholder="Usuario o acción" @keyup.enter="applyFilters" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Usuario</Label>
                <Select v-model="user" @update:model-value="applyFilters">
                    <SelectTrigger class="w-44"><SelectValue placeholder="Todos" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Todos</SelectItem>
                        <SelectItem v-for="u in props.users" :key="u.value" :value="String(u.value)">{{ u.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Acción</Label>
                <Select v-model="action" @update:model-value="applyFilters">
                    <SelectTrigger class="w-56"><SelectValue placeholder="Todas" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Todas</SelectItem>
                        <SelectItem v-for="a in props.actions" :key="a.value" :value="a.value">{{ a.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Desde</Label>
                <Input v-model="desde" type="date" class="w-36" @change="applyFilters" />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Hasta</Label>
                <Input v-model="hasta" type="date" class="w-36" @change="applyFilters" />
            </div>
            <Button size="sm" @click="applyFilters">Filtrar</Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Usuario</TableHead>
                        <TableHead>Acción</TableHead>
                        <TableHead>Entidad</TableHead>
                        <TableHead>Resumen</TableHead>
                        <TableHead>Resultado</TableHead>
                        <TableHead>Fecha</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="props.logs.data.length === 0" :colspan="6">Sin actividad registrada.</TableEmpty>
                    <TableRow v-for="log in props.logs.data" :key="log.id">
                        <TableCell class="font-medium">{{ log.userName }}</TableCell>
                        <TableCell>{{ log.actionLabel }}</TableCell>
                        <TableCell class="text-muted-foreground">
                            <span v-if="log.subjectLabel">{{ log.subjectLabel }} #{{ log.subjectId }}</span>
                        </TableCell>
                        <TableCell>
                            <ul v-if="log.summary.length" class="flex flex-col gap-0.5 text-xs text-muted-foreground">
                                <li v-for="(line, i) in log.summary" :key="i">{{ line }}</li>
                            </ul>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="log.result === 'success' ? 'outline' : 'destructive'">{{ log.result }}</Badge>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">{{ formatDate(log.createdAt) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="props.logs.lastPage > 1" class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{{ props.logs.total }} registros</span>
            <div class="flex items-center gap-2">
                <Button size="sm" variant="outline" :disabled="props.logs.currentPage <= 1" @click="goToPage(props.logs.currentPage - 1)">
                    Anterior
                </Button>
                <span>Página {{ props.logs.currentPage }} de {{ props.logs.lastPage }}</span>
                <Button size="sm" variant="outline" :disabled="props.logs.currentPage >= props.logs.lastPage" @click="goToPage(props.logs.currentPage + 1)">
                    Siguiente
                </Button>
            </div>
        </div>
    </div>
</template>

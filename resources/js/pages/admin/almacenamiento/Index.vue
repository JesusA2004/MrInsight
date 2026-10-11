<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Database, HardDrive, Loader2, Server, XCircle } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import almacenamiento from '@/routes/admin/almacenamiento';
import type { BreadcrumbItem } from '@/types';

interface TestResult {
    status: 'ok' | 'error' | 'pending';
    message: string;
    connect: boolean;
    read: boolean;
    write: boolean;
    checkedAt: string;
}

interface Provider {
    id: number;
    key: 'local' | 'synology';
    label: string;
    isActive: boolean;
    lastTestedAt: string | null;
    lastTestResult: TestResult | null;
    filesCount: number;
    filesBytes: number;
    space: { totalBytes: number; freeBytes: number } | null;
    config: {
        host: string | null;
        port: number;
        username: string | null;
        authMethod: 'password' | 'private_key';
        hasPassword: boolean;
        hasPrivateKey: boolean;
        transferLimitKbps: number | null;
        rootPath: string | null;
    } | null;
}

const props = defineProps<{ providers: Provider[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Almacenamiento', href: almacenamiento.index() },
        ] as BreadcrumbItem[],
    },
});

function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** exp).toFixed(exp === 0 ? 0 : 1)} ${units[exp]}`;
}

function formatDate(iso: string | null): string {
    if (!iso) return 'Nunca';
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

const testing = ref<number | null>(null);
function testProvider(provider: Provider) {
    testing.value = provider.id;
    router.post(almacenamiento.probar(provider.id).url, {}, {
        preserveScroll: true,
        onFinish: () => (testing.value = null),
    });
}

function activateProvider(provider: Provider) {
    router.post(almacenamiento.activar(provider.id).url, {}, { preserveScroll: true });
}

const synologyForms = new Map<number, ReturnType<typeof useForm>>();
function synologyForm(provider: Provider) {
    if (!synologyForms.has(provider.id)) {
        synologyForms.set(
            provider.id,
            useForm({
                host: provider.config?.host ?? '',
                port: provider.config?.port ?? 22,
                username: provider.config?.username ?? '',
                auth_method: provider.config?.authMethod ?? 'password',
                password: '',
                private_key_path: '',
                transfer_limit_kbps: provider.config?.transferLimitKbps ?? null,
            }),
        );
    }
    return synologyForms.get(provider.id)!;
}

function saveSynology(provider: Provider) {
    const form = synologyForm(provider);
    form.put(almacenamiento.update(provider.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            form.private_key_path = '';
        },
    });
}
</script>

<template>
    <Head title="Almacenamiento" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Almacenamiento</h1>
            <p class="text-sm text-muted-foreground">
                Proveedores de almacenamiento multimedia. El almacenamiento local sigue operativo; Synology se conecta a futuro vía Tailscale.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="provider in props.providers" :key="provider.id" class="flex flex-col">
                <CardHeader class="flex flex-row items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <component :is="provider.key === 'local' ? HardDrive : Server" class="size-5 text-muted-foreground" />
                        <CardTitle>{{ provider.label }}</CardTitle>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="provider.isActive" variant="default">Activo</Badge>
                        <Badge v-else variant="outline">Inactivo</Badge>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-1 flex-col gap-4">
                    <div class="flex items-center gap-2 text-sm">
                        <template v-if="provider.lastTestResult?.status === 'ok'">
                            <CheckCircle2 class="size-4 text-emerald-500" />
                            <span>{{ provider.lastTestResult.message }}</span>
                        </template>
                        <template v-else-if="provider.lastTestResult?.status === 'error'">
                            <XCircle class="size-4 text-destructive" />
                            <span>{{ provider.lastTestResult.message }}</span>
                        </template>
                        <template v-else-if="provider.key === 'synology'">
                            <Database class="size-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Synology pendiente de configuración.</span>
                        </template>
                        <template v-else>
                            <span class="text-muted-foreground">Sin probar todavía.</span>
                        </template>
                    </div>
                    <p class="text-xs text-muted-foreground">Última verificación: {{ formatDate(provider.lastTestedAt) }}</p>

                    <div v-if="provider.space" class="flex flex-col gap-1">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${Math.min(100, (1 - provider.space.freeBytes / provider.space.totalBytes) * 100)}%` }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatBytes(provider.space.totalBytes - provider.space.freeBytes) }} usados de {{ formatBytes(provider.space.totalBytes) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4 text-sm">
                        <span>{{ provider.filesCount }} archivos</span>
                        <span class="text-muted-foreground">{{ formatBytes(provider.filesBytes) }}</span>
                    </div>

                    <template v-if="provider.key === 'synology'">
                        <div class="grid gap-3 rounded-lg border p-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Host (Tailscale)</Label>
                                    <Input v-model="synologyForm(provider).host" placeholder="nas.tailnet.ts.net" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Puerto</Label>
                                    <Input v-model="synologyForm(provider).port" type="number" />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Usuario</Label>
                                <Input v-model="synologyForm(provider).username" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Autenticación</Label>
                                    <Select v-model="synologyForm(provider).auth_method">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="password">Contraseña</SelectItem>
                                            <SelectItem value="private_key">Llave privada</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Límite de transferencia (KB/s)</Label>
                                    <Input v-model="synologyForm(provider).transfer_limit_kbps" type="number" placeholder="Sin límite" />
                                </div>
                            </div>
                            <div v-if="synologyForm(provider).auth_method === 'password'" class="grid gap-1.5">
                                <Label class="text-xs">Contraseña {{ provider.config?.hasPassword ? '(guardada — deja en blanco para conservarla)' : '' }}</Label>
                                <Input v-model="synologyForm(provider).password" type="password" />
                            </div>
                            <div v-else class="grid gap-1.5">
                                <Label class="text-xs">
                                    Ruta de la llave privada en el servidor
                                    {{ provider.config?.hasPrivateKey ? '(guardada — deja en blanco para conservarla)' : '' }}
                                </Label>
                                <Input v-model="synologyForm(provider).private_key_path" placeholder="/etc/mrinsight/nas_id_ed25519" />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Ruta raíz autorizada: <code>{{ provider.config?.rootPath }}</code> (se define por variable de entorno del servidor, no desde aquí).
                            </p>
                            <Button size="sm" :disabled="synologyForm(provider).processing" @click="saveSynology(provider)">
                                Guardar configuración
                            </Button>
                        </div>
                    </template>

                    <div class="mt-auto flex items-center gap-2 pt-2">
                        <Button size="sm" variant="outline" :disabled="testing === provider.id" @click="testProvider(provider)">
                            <Loader2 v-if="testing === provider.id" class="size-4 animate-spin" />
                            Probar conexión
                        </Button>
                        <Button v-if="!provider.isActive" size="sm" variant="secondary" @click="activateProvider(provider)">
                            Activar proveedor
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

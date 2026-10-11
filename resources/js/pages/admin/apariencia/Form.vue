<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Monitor, Smartphone, Tablet } from '@lucide/vue';
import { computed, ref } from 'vue';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import apariencia from '@/routes/admin/apariencia';
import type { BreadcrumbItem } from '@/types';

interface ThemeState {
    colors: { background: string; foreground: string; accent: string; muted: string };
    fonts: { display: string; body: string };
    buttons: { style: 'solid' | 'outline'; radius: 'none' | 'sm' | 'md' | 'lg' | 'full' };
    cards: { radius: 'none' | 'sm' | 'md' | 'lg' | 'xl' };
    logo: { logo_media_id: number | null; mark_media_id: number | null; favicon_media_id: number | null };
    site: { name: string; subtitle: string };
}

interface MediaRef {
    id: number;
    url: string | null;
}

const props = defineProps<{
    published: ThemeState;
    draft: ThemeState;
    hasDraft: boolean;
    media: { logo: MediaRef | null; mark: MediaRef | null; favicon: MediaRef | null };
    presets: { key: string; label: string; colors: ThemeState['colors']; fonts: ThemeState['fonts'] }[];
    defaults: ThemeState;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Apariencia', href: apariencia.edit() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm<ThemeState>({
    colors: { ...props.draft.colors },
    fonts: { ...props.draft.fonts },
    buttons: { ...props.draft.buttons },
    cards: { ...props.draft.cards },
    logo: { ...props.draft.logo },
    site: { ...props.draft.site },
});

const logoUrl = ref(props.media.logo?.url ?? null);
const markUrl = ref(props.media.mark?.url ?? null);
const faviconUrl = ref(props.media.favicon?.url ?? null);

const logoPickerOpen = ref(false);
const markPickerOpen = ref(false);
const faviconPickerOpen = ref(false);

const discardOpen = ref(false);
const resetOpen = ref(false);

function onLogo(media: PickedMedia) {
    form.logo.logo_media_id = media.id;
    logoUrl.value = media.url;
}
function onMark(media: PickedMedia) {
    form.logo.mark_media_id = media.id;
    markUrl.value = media.url;
}
function onFavicon(media: PickedMedia) {
    form.logo.favicon_media_id = media.id;
    faviconUrl.value = media.url;
}

function applyPreset(preset: { colors: ThemeState['colors']; fonts: ThemeState['fonts'] }) {
    form.colors = { ...preset.colors };
    form.fonts = { ...preset.fonts };
}

function saveDraft() {
    form.put(apariencia.update().url, { preserveScroll: true });
}

// `publicar`/`descartar`/`restablecer` reenvían los valores actuales del
// formulario para publicar exactamente lo que se ve en pantalla.
function doPublish() {
    router.post(apariencia.publicar().url, { ...form.data() }, { preserveScroll: true });
}

function doDiscard() {
    router.post(apariencia.descartar().url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            form.colors = { ...props.published.colors };
            form.fonts = { ...props.published.fonts };
            form.buttons = { ...props.published.buttons };
            form.cards = { ...props.published.cards };
            form.logo = { ...props.published.logo };
            form.site = { ...props.published.site };
            discardOpen.value = false;
        },
    });
}

function doReset() {
    router.post(apariencia.restablecer().url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            form.colors = { ...props.defaults.colors };
            form.fonts = { ...props.defaults.fonts };
            form.buttons = { ...props.defaults.buttons };
            form.cards = { ...props.defaults.cards };
            form.logo = { ...props.defaults.logo };
            form.site = { ...props.defaults.site };
            logoUrl.value = null;
            markUrl.value = null;
            faviconUrl.value = null;
            resetOpen.value = false;
        },
    });
}

// --- Vista previa ---
const viewport = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const viewportWidth = computed(() => ({ desktop: '100%', tablet: '768px', mobile: '390px' })[viewport.value]);

const radiusRem: Record<string, number> = { none: 0, sm: 0.25, md: 0.5, lg: 0.75, xl: 1, full: 9999 };

const previewStyle = computed(() => {
    const cardRem = radiusRem[form.cards.radius] ?? 0.75;
    const buttonRem = radiusRem[form.buttons.radius] ?? 0.5;

    return {
        '--p-bg': form.colors.background,
        '--p-fg': form.colors.foreground,
        '--p-accent': form.colors.accent,
        '--p-muted': form.colors.muted,
        '--p-card-radius': cardRem >= 9999 ? '9999px' : `${cardRem}rem`,
        '--p-button-radius': buttonRem >= 9999 ? '9999px' : `${buttonRem}rem`,
        fontFamily: `"${form.fonts.body}", sans-serif`,
    } as Record<string, string>;
});

// --- Contraste (WCAG, aproximado) ---
function luminance(hex: string): number {
    const n = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(n.slice(i, i + 2), 16) / 255);
    const lin = [r, g, b].map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
}
function contrast(hexA: string, hexB: string): number {
    const [la, lb] = [luminance(hexA), luminance(hexB)].sort((a, b) => b - a);
    return (la + 0.05) / (lb + 0.05);
}
const textContrast = computed(() => contrast(form.colors.background, form.colors.foreground));
const accentContrast = computed(() => contrast(form.colors.background, form.colors.accent));
</script>

<template>
    <Head title="Apariencia" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Apariencia</h1>
                <p class="text-sm text-muted-foreground">
                    Identidad visual del sitio público. Los cambios se publican por separado de la vista previa.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Badge v-if="hasDraft" variant="outline">Borrador sin publicar</Badge>
                <Button variant="outline" size="sm" @click="resetOpen = true">Restablecer original</Button>
                <Button variant="outline" size="sm" :disabled="!hasDraft" @click="discardOpen = true">Descartar borrador</Button>
                <Button size="sm" variant="secondary" :disabled="form.processing" @click="saveDraft">Guardar borrador</Button>
                <Button size="sm" :disabled="form.processing" @click="doPublish">Publicar</Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <!-- Controles -->
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader><CardTitle>Plantillas premium</CardTitle></CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="flex items-center gap-2 rounded-lg border p-2 text-xs hover:border-primary"
                            @click="applyPreset(preset)"
                        >
                            <span class="flex size-6 overflow-hidden rounded-full border">
                                <span class="flex-1" :style="{ background: preset.colors.background }" />
                                <span class="flex-1" :style="{ background: preset.colors.accent }" />
                            </span>
                            {{ preset.label }}
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Colores</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div v-for="key in (['background', 'foreground', 'accent', 'muted'] as const)" :key="key" class="grid gap-2">
                            <Label class="capitalize">{{ key }}</Label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.colors[key]" type="color" class="size-9 shrink-0 rounded border" />
                                <Input v-model="form.colors[key]" class="font-mono text-xs" maxlength="7" />
                            </div>
                        </div>
                        <div class="col-span-2 flex flex-wrap gap-3 pt-1">
                            <Badge :variant="textContrast >= 4.5 ? 'default' : 'destructive'">
                                Texto/fondo {{ textContrast.toFixed(2) }}:1 {{ textContrast >= 4.5 ? '(AA)' : '(bajo)' }}
                            </Badge>
                            <Badge :variant="accentContrast >= 3 ? 'default' : 'destructive'">
                                Acento/fondo {{ accentContrast.toFixed(2) }}:1 {{ accentContrast >= 3 ? '(AA)' : '(bajo)' }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Tipografías</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Titulares</Label>
                            <Select v-model="form.fonts.display">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Fraunces">Fraunces</SelectItem>
                                    <SelectItem value="Playfair Display">Playfair Display</SelectItem>
                                    <SelectItem value="Cormorant Garamond">Cormorant Garamond</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Cuerpo</Label>
                            <Select v-model="form.fonts.body">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Instrument Sans">Instrument Sans</SelectItem>
                                    <SelectItem value="Inter">Inter</SelectItem>
                                    <SelectItem value="Work Sans">Work Sans</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Botones y tarjetas</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label>Estilo de botón</Label>
                            <Select v-model="form.buttons.style">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="solid">Sólido</SelectItem>
                                    <SelectItem value="outline">Contorno</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Radio de botón</Label>
                            <Select v-model="form.buttons.radius">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Ninguno</SelectItem>
                                    <SelectItem value="sm">Pequeño</SelectItem>
                                    <SelectItem value="md">Medio</SelectItem>
                                    <SelectItem value="lg">Grande</SelectItem>
                                    <SelectItem value="full">Total</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Radio de tarjeta</Label>
                            <Select v-model="form.cards.radius">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Ninguno</SelectItem>
                                    <SelectItem value="sm">Pequeño</SelectItem>
                                    <SelectItem value="md">Medio</SelectItem>
                                    <SelectItem value="lg">Grande</SelectItem>
                                    <SelectItem value="xl">Extra grande</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Logotipo, isotipo y favicon</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label>Logotipo</Label>
                            <button type="button" class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary" @click="logoPickerOpen = true">
                                <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-contain p-2" />
                                <ImagePlus v-else class="size-5 text-muted-foreground" />
                            </button>
                        </div>
                        <div class="grid gap-2">
                            <Label>Isotipo</Label>
                            <button type="button" class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary" @click="markPickerOpen = true">
                                <img v-if="markUrl" :src="markUrl" alt="" class="size-full object-contain p-2" />
                                <ImagePlus v-else class="size-5 text-muted-foreground" />
                            </button>
                        </div>
                        <div class="grid gap-2">
                            <Label>Favicon</Label>
                            <button type="button" class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary" @click="faviconPickerOpen = true">
                                <img v-if="faviconUrl" :src="faviconUrl" alt="" class="size-full object-contain p-2" />
                                <ImagePlus v-else class="size-5 text-muted-foreground" />
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Identidad</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Nombre del sitio</Label>
                            <Input v-model="form.site.name" maxlength="60" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Subtítulo</Label>
                            <Input v-model="form.site.subtitle" maxlength="80" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Vista previa -->
            <div class="flex flex-col gap-3 lg:sticky lg:top-6 lg:self-start">
                <div class="flex items-center gap-2">
                    <Button :variant="viewport === 'desktop' ? 'default' : 'outline'" size="icon-sm" @click="viewport = 'desktop'"><Monitor class="size-4" /></Button>
                    <Button :variant="viewport === 'tablet' ? 'default' : 'outline'" size="icon-sm" @click="viewport = 'tablet'"><Tablet class="size-4" /></Button>
                    <Button :variant="viewport === 'mobile' ? 'default' : 'outline'" size="icon-sm" @click="viewport = 'mobile'"><Smartphone class="size-4" /></Button>
                    <span class="text-xs text-muted-foreground">Vista previa en vivo</span>
                </div>

                <div class="overflow-hidden rounded-xl border bg-muted/30 p-6">
                    <div
                        class="mx-auto flex flex-col gap-4 overflow-hidden rounded-lg p-6 shadow-xl transition-all"
                        :style="[previewStyle, { background: 'var(--p-bg)', color: 'var(--p-fg)', maxWidth: viewportWidth }]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img v-if="logoUrl" :src="logoUrl" alt="" class="size-6 object-contain" />
                                <span class="font-display text-sm font-semibold" :style="{ fontFamily: `&quot;${form.fonts.display}&quot;, serif` }">{{ form.site.name }}</span>
                            </div>
                            <span class="text-[10px] uppercase tracking-widest" :style="{ color: 'var(--p-muted)' }">{{ form.site.subtitle }}</span>
                        </div>

                        <div class="rounded-[var(--p-card-radius)] p-5" :style="{ background: 'color-mix(in srgb, var(--p-bg) 85%, white 8%)' }">
                            <p class="text-[11px] uppercase tracking-widest" :style="{ color: 'var(--p-accent)' }">Negocios. Hábitos. Vida.</p>
                            <p class="mt-2 text-lg font-semibold" :style="{ fontFamily: `&quot;${form.fonts.display}&quot;, serif` }">Sin tapujos.</p>
                            <button
                                type="button"
                                class="mt-4 inline-flex h-9 items-center px-4 text-xs font-medium"
                                :style="{
                                    borderRadius: 'var(--p-button-radius)',
                                    background: form.buttons.style === 'solid' ? 'var(--p-accent)' : 'transparent',
                                    color: form.buttons.style === 'solid' ? 'var(--p-bg)' : 'var(--p-accent)',
                                    border: form.buttons.style === 'outline' ? '1px solid var(--p-accent)' : 'none',
                                }"
                            >
                                Leer el tip
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="aspect-square rounded-[var(--p-card-radius)]" :style="{ background: 'color-mix(in srgb, var(--p-bg) 80%, white 12%)' }" />
                            <div class="aspect-square rounded-[var(--p-card-radius)]" :style="{ background: 'color-mix(in srgb, var(--p-bg) 80%, white 12%)' }" />
                        </div>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Esta vista previa es una maqueta ilustrativa; "Publicar" aplica los cambios al sitio real.
                </p>
            </div>
        </div>
    </div>

    <MediaPickerDialog v-model:open="logoPickerOpen" type="image" @select="onLogo" />
    <MediaPickerDialog v-model:open="markPickerOpen" type="image" @select="onMark" />
    <MediaPickerDialog v-model:open="faviconPickerOpen" type="image" @select="onFavicon" />

    <AlertDialog v-model:open="discardOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Descartar el borrador?</AlertDialogTitle>
                <AlertDialogDescription>
                    Se perderán los cambios sin publicar y el formulario volverá a la última versión publicada.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="doDiscard">Descartar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="resetOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Restablecer a los valores originales?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esto publica de inmediato la paleta carbón y dorado original, reemplazando lo que esté publicado ahora.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="doReset">Restablecer</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

import {
    AlertTriangle,
    CircleDashed,
    CircleOff,
    FileWarning,
    ShieldAlert,
    Wifi,
} from '@lucide/vue';
import type { Component } from 'vue';

export type HealthStatus =
    | 'available'
    | 'disconnected'
    | 'permission_error'
    | 'write_error'
    | 'error'
    | 'untested';

export type CheckKey = 'mounted' | 'source' | 'write' | 'read' | 'delete';

export type Checks = Record<CheckKey, boolean | null>;

export interface TestResult {
    status: HealthStatus | 'ok' | 'pending';
    message: string;
    checks?: Partial<Checks>;
    checkedAt: string;
    // Filas antiguas (SFTP) guardaban booleanos sueltos.
    connect?: boolean;
    read?: boolean;
    write?: boolean;
}

export interface StorageSpace {
    totalBytes: number;
    freeBytes: number;
    usedBytes: number;
}

export interface StorageProvider {
    id: number;
    key: 'local' | 'synology';
    label: string;
    isActive: boolean;
    lastTestedAt: string | null;
    lastTestResult: TestResult | null;
    filesCount: number;
    filesBytes: number;
    space: StorageSpace | null;
    status: HealthStatus;
    canActivate: boolean;
    details: {
        name: string;
        address: string;
        protocol: string;
        share: string;
        mountPath: string;
    } | null;
}

export type Tone = 'success' | 'destructive' | 'warning' | 'muted';

export interface StatusMeta {
    label: string;
    hint: string;
    icon: Component;
    tone: Tone;
}

/** Un solo vocabulario de estados para todo el módulo. */
export const STATUS: Record<HealthStatus, StatusMeta> = {
    available: {
        label: 'Disponible',
        hint: 'Lectura, escritura y eliminación correctas.',
        icon: Wifi,
        tone: 'success',
    },
    disconnected: {
        label: 'Desconectado',
        hint: 'El servidor no detecta la carpeta compartida montada.',
        icon: CircleOff,
        tone: 'destructive',
    },
    permission_error: {
        label: 'Error de permisos',
        hint: 'La plataforma no tiene permiso para escribir en la carpeta.',
        icon: ShieldAlert,
        tone: 'warning',
    },
    write_error: {
        label: 'Error de escritura',
        hint: 'No se pudieron guardar o verificar archivos nuevos.',
        icon: FileWarning,
        tone: 'warning',
    },
    error: {
        label: 'Error',
        hint: 'La prueba falló por una causa inesperada.',
        icon: AlertTriangle,
        tone: 'destructive',
    },
    untested: {
        label: 'Sin probar',
        hint: 'Ejecuta una prueba para conocer el estado.',
        icon: CircleDashed,
        tone: 'muted',
    },
};

export const TONE_TEXT: Record<Tone, string> = {
    success: 'text-success',
    destructive: 'text-destructive',
    warning: 'text-warning',
    muted: 'text-muted-foreground',
};

export const TONE_SOFT: Record<Tone, string> = {
    success: 'bg-success/12 text-success',
    destructive: 'bg-destructive/10 text-destructive',
    warning: 'bg-warning/15 text-warning',
    muted: 'bg-muted text-muted-foreground',
};

export const TONE_DOT: Record<Tone, string> = {
    success: 'bg-success',
    destructive: 'bg-destructive',
    warning: 'bg-warning',
    muted: 'bg-muted-foreground/60',
};

export function normalizeStatus(
    status: string | null | undefined,
): HealthStatus {
    if (status === 'ok') return 'available';
    if (status === 'pending' || !status) return 'untested';

    return status in STATUS ? (status as HealthStatus) : 'error';
}

/** Convierte cualquier resultado (nuevo o heredado) en el juego completo de verificaciones. */
export function normalizeChecks(result: TestResult | null): Checks | null {
    if (!result) return null;

    if (result.checks) {
        return {
            mounted: result.checks.mounted ?? null,
            source: result.checks.source ?? null,
            write: result.checks.write ?? null,
            read: result.checks.read ?? null,
            delete: result.checks.delete ?? null,
        };
    }

    if (
        result.connect === undefined &&
        result.read === undefined &&
        result.write === undefined
    )
        return null;

    return {
        mounted: result.connect ?? null,
        source: null,
        write: result.write ?? null,
        read: result.read ?? null,
        delete: null,
    };
}

export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const exp = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** exp;

    return `${value.toFixed(exp === 0 ? 0 : value >= 100 ? 0 : 1)} ${units[exp]}`;
}

const DATE = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const RELATIVE = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });

export function formatDate(iso: string | null): string {
    if (!iso) return 'Nunca';

    return DATE.format(new Date(iso));
}

export function formatRelative(iso: string | null): string {
    if (!iso) return 'Nunca se ha probado';
    const diff = (new Date(iso).getTime() - Date.now()) / 1000;
    const abs = Math.abs(diff);
    if (abs < 60) return 'Hace un momento';
    if (abs < 3600) return RELATIVE.format(Math.round(diff / 60), 'minute');
    if (abs < 86400) return RELATIVE.format(Math.round(diff / 3600), 'hour');

    return RELATIVE.format(Math.round(diff / 86400), 'day');
}

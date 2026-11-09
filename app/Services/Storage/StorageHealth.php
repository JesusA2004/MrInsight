<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderKey;
use App\Exceptions\StorageUnavailableException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Estado real de los proveedores de almacenamiento de la biblioteca
 * multimedia.
 *
 * El NAS Synology se monta en el VPS por SMB/CIFS (fstab, credenciales
 * solo en el sistema operativo) y Laravel lo usa como un disco "local" con
 * raíz en el punto de montaje. Un punto de montaje vacío NO es el NAS: si
 * el montaje se cae, el directorio sigue existiendo y escribir ahí dejaría
 * archivos en el disco del VPS. Por eso "conectado" significa que la tabla
 * de montajes del kernel (/proc/mounts) lista ese punto de montaje con un
 * sistema de archivos CIFS/SMB3 y con el origen esperado, nunca solo que
 * la carpeta exista.
 *
 * Los mensajes devueltos y registrados son siempre seguros: nunca incluyen
 * credenciales ni el texto de excepciones del sistema.
 */
class StorageHealth
{
    public const SYNOLOGY_DISK = 'media_synology';

    public const LOCAL_DISK = 'media_local';

    public const AVAILABLE = 'available';

    public const DISCONNECTED = 'disconnected';

    public const PERMISSION_ERROR = 'permission_error';

    public const WRITE_ERROR = 'write_error';

    public const ERROR = 'error';

    /** Sistemas de archivos que aceptamos como montaje SMB real. */
    private const SMB_FSTYPES = ['cifs', 'smb3'];

    /**
     * Estado del montaje del NAS según la tabla de montajes.
     *
     * @return array{entry: bool, mounted: bool, source: bool, pathExists: bool}
     */
    public function synologyMountState(): array
    {
        $mountPath = $this->synologyMountPath();
        $pathExists = $mountPath !== '' && is_dir($mountPath);
        $entry = $this->findMountEntry($mountPath);

        if ($entry === null) {
            return ['entry' => false, 'mounted' => false, 'source' => false, 'pathExists' => $pathExists];
        }

        $expectedFstype = strtolower((string) config('filesystems.disks.media_synology.mount_fstype', 'cifs'));
        $fstypeOk = in_array($entry['fstype'], array_unique([...self::SMB_FSTYPES, $expectedFstype]), true);

        $expectedSource = $this->normalizeSource((string) config('filesystems.disks.media_synology.mount_source', ''));
        $sourceOk = $expectedSource !== '' && $this->normalizeSource($entry['source']) === $expectedSource;

        return [
            'entry' => true,
            'mounted' => $fstypeOk && $pathExists,
            'source' => $sourceOk,
            'pathExists' => $pathExists,
        ];
    }

    /**
     * Comprobación barata: el NAS está montado con el origen correcto.
     */
    public function isSynologyMounted(): bool
    {
        $state = $this->synologyMountState();

        return $state['mounted'] && $state['source'];
    }

    /**
     * Comprobación barata antes de cada escritura nueva en `$disk`. Para
     * cualquier disco que no sea el NAS no hace nada (el disco local
     * reporta sus fallos con el valor de retorno de put()).
     *
     * @throws StorageUnavailableException
     */
    public function assertWritable(string $disk): void
    {
        if ($disk !== self::SYNOLOGY_DISK) {
            return;
        }

        if (! $this->isSynologyMounted()) {
            Log::warning('Escritura rechazada: el NAS Synology no está montado o el origen no coincide.', [
                'mount_path' => $this->synologyMountPath(),
            ]);

            throw new StorageUnavailableException;
        }

        if (! is_writable($this->synologyMountPath())) {
            Log::warning('Escritura rechazada: el punto de montaje del NAS Synology no tiene permisos de escritura.', [
                'mount_path' => $this->synologyMountPath(),
            ]);

            throw new StorageUnavailableException('SynologyExtra está montado, pero no permite escribir. No se guardó ningún archivo; avisa al administrador.');
        }
    }

    /**
     * Comprobación barata antes de leer de `$disk` (entrega pública,
     * procesamiento en cola).
     */
    public function isReadable(string $disk): bool
    {
        return $disk !== self::SYNOLOGY_DISK || $this->isSynologyMounted();
    }

    /**
     * Estado en vivo (barato, sin escribir nada) para pintar el panel.
     */
    public function liveStatus(StorageProviderKey $key): string
    {
        if ($key === StorageProviderKey::Local) {
            $root = (string) config('filesystems.disks.media_local.root');
            $target = is_dir($root) ? $root : dirname($root);

            return is_writable($target) ? self::AVAILABLE : self::PERMISSION_ERROR;
        }

        if (! $this->isSynologyMounted()) {
            return self::DISCONNECTED;
        }

        return is_writable($this->synologyMountPath()) ? self::AVAILABLE : self::PERMISSION_ERROR;
    }

    /**
     * Prueba completa del NAS: montaje, origen y escritura/lectura/borrado
     * real de un archivo temporal.
     *
     * @return array{status: string, message: string, checks: array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}, checkedAt: string}
     */
    public function checkSynology(): array
    {
        $checks = ['mounted' => null, 'source' => null, 'write' => null, 'read' => null, 'delete' => null];
        $state = $this->synologyMountState();

        if (! $state['pathExists']) {
            $checks['mounted'] = false;

            return $this->result(self::DISCONNECTED, 'SynologyExtra: la ruta de montaje no existe en el servidor.', $checks);
        }

        $checks['mounted'] = $state['mounted'];

        if (! $state['mounted']) {
            return $this->result(
                self::DISCONNECTED,
                $state['entry']
                    ? 'SynologyExtra: la ruta de montaje no corresponde a un recurso SMB/CIFS.'
                    : 'SynologyExtra: la carpeta compartida no está montada. Verifica Tailscale y el montaje en el servidor.',
                $checks,
            );
        }

        $checks['source'] = $state['source'];

        if (! $state['source']) {
            return $this->result(self::DISCONNECTED, 'SynologyExtra: la ruta de montaje apunta a una carpeta compartida distinta a la esperada.', $checks);
        }

        if (! is_writable($this->synologyMountPath())) {
            $checks['write'] = false;

            return $this->result(self::PERMISSION_ERROR, 'SynologyExtra: la carpeta está montada, pero la plataforma no tiene permiso de escritura.', $checks);
        }

        return $this->probe(self::SYNOLOGY_DISK, 'SynologyExtra', $checks);
    }

    /**
     * Prueba completa del disco local del VPS.
     *
     * @return array{status: string, message: string, checks: array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}, checkedAt: string}
     */
    public function checkLocal(): array
    {
        return $this->probe(self::LOCAL_DISK, 'Servidor principal', [
            'mounted' => true, 'source' => true, 'write' => null, 'read' => null, 'delete' => null,
        ]);
    }

    /**
     * Capacidad del proveedor. Para Synology solo se calcula con el NAS
     * montado: con el montaje caído, disk_total_space() reportaría el disco
     * del VPS como si fuera el NAS.
     *
     * @return array{totalBytes: int, freeBytes: int, usedBytes: int}|null
     */
    public function space(StorageProviderKey $key): ?array
    {
        if ($key === StorageProviderKey::Synology) {
            if (! $this->isSynologyMounted()) {
                return null;
            }
            $path = $this->synologyMountPath();
        } else {
            $path = (string) config('filesystems.disks.media_local.root');
            if (! is_dir($path)) {
                return null;
            }
        }

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false) {
            return null;
        }

        return [
            'totalBytes' => (int) $total,
            'freeBytes' => (int) $free,
            'usedBytes' => max(0, (int) $total - (int) $free),
        ];
    }

    /**
     * Datos descriptivos del NAS para el panel (sin credenciales).
     *
     * @return array{name: string, address: string, protocol: string, share: string, mountPath: string}
     */
    public function synologyDetails(): array
    {
        $config = config('filesystems.disks.media_synology', []);

        return [
            'name' => (string) ($config['display_name'] ?? 'SynologyExtra'),
            'address' => (string) ($config['display_address'] ?? '100.67.89.20'),
            'protocol' => (string) ($config['display_protocol'] ?? 'SMB/CIFS 3.0'),
            'share' => (string) ($config['display_share'] ?? 'MrInsight'),
            'mountPath' => $this->synologyMountPath(),
        ];
    }

    public function synologyMountPath(): string
    {
        return (string) config('filesystems.disks.media_synology.root', '');
    }

    /**
     * Escribe, lee y borra un archivo temporal en `.storage-check/`. El
     * archivo se limpia siempre, incluso si alguna etapa falla.
     *
     * @param  array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}  $checks
     * @return array{status: string, message: string, checks: array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}, checkedAt: string}
     */
    private function probe(string $diskName, string $label, array $checks): array
    {
        $probePath = '.storage-check/'.Str::uuid().'.tmp';
        $probeContent = (string) Str::uuid();
        $disk = null;

        try {
            $disk = Storage::disk($diskName);

            try {
                $written = $disk->put($probePath, $probeContent);
            } catch (Throwable $e) {
                $written = false;
                $this->logSafe($label, 'write', $e);
                if ($this->looksLikePermissionError($e)) {
                    $checks['write'] = false;

                    return $this->result(self::PERMISSION_ERROR, "{$label}: sin permiso de escritura.", $checks);
                }
            }

            $checks['write'] = $written !== false;
            if ($written === false) {
                return $this->result(self::WRITE_ERROR, "{$label}: no se pudo escribir el archivo de prueba.", $checks);
            }

            try {
                $read = $disk->get($probePath);
            } catch (Throwable $e) {
                $this->logSafe($label, 'read', $e);
                $read = null;
            }

            $checks['read'] = $read === $probeContent;
            if (! $checks['read']) {
                return $this->result(self::WRITE_ERROR, "{$label}: la lectura no coincidió con lo escrito.", $checks);
            }

            try {
                $deleted = $disk->delete($probePath) && ! $disk->exists($probePath);
            } catch (Throwable $e) {
                $this->logSafe($label, 'delete', $e);
                $deleted = false;
            }

            $checks['delete'] = $deleted;
            if (! $deleted) {
                return $this->result(self::WRITE_ERROR, "{$label}: no se pudo eliminar el archivo de prueba.", $checks);
            }

            return $this->result(self::AVAILABLE, "{$label}: escritura, lectura y eliminación correctas.", $checks);
        } catch (Throwable $e) {
            $this->logSafe($label, 'probe', $e);

            return $this->result(self::ERROR, "{$label}: error inesperado al probar el almacenamiento.", $checks);
        } finally {
            if ($disk !== null) {
                try {
                    if ($disk->exists($probePath)) {
                        $disk->delete($probePath);
                    }
                } catch (Throwable) {
                    // La limpieza es de mejor esfuerzo: nunca oculta el resultado real.
                }
            }
        }
    }

    /**
     * @param  array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}  $checks
     * @return array{status: string, message: string, checks: array{mounted: bool|null, source: bool|null, write: bool|null, read: bool|null, delete: bool|null}, checkedAt: string}
     */
    private function result(string $status, string $message, array $checks): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'checks' => $checks,
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    private function looksLikePermissionError(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $message = strtolower($current->getMessage());
            if (str_contains($message, 'permission denied') || str_contains($message, 'eacces')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra solo la clase de la excepción y la etapa: el texto original
     * podría contener rutas o datos del sistema.
     */
    private function logSafe(string $label, string $stage, Throwable $e): void
    {
        Log::warning('Prueba de almacenamiento fallida', [
            'provider' => $label,
            'stage' => $stage,
            'exception' => $e::class,
            'permission' => $this->looksLikePermissionError($e),
        ]);
    }

    /**
     * Busca el punto de montaje en la tabla de montajes. Si aparece varias
     * veces (montajes apilados), gana la última entrada, igual que el kernel.
     *
     * @return array{source: string, fstype: string}|null
     */
    private function findMountEntry(string $mountPath): ?array
    {
        if ($mountPath === '') {
            return null;
        }

        $table = (string) config('filesystems.disks.media_synology.mounts_table', '/proc/mounts');
        if (! @is_file($table) || ! @is_readable($table)) {
            return null;
        }

        $lines = @file($table, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        $target = $this->normalizePath($mountPath);
        $found = null;

        foreach ($lines as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if ($fields === false || count($fields) < 3) {
                continue;
            }

            if ($this->normalizePath($this->unescape($fields[1])) === $target) {
                $found = [
                    'source' => $this->unescape($fields[0]),
                    'fstype' => strtolower($fields[2]),
                ];
            }
        }

        return $found;
    }

    /**
     * /proc/mounts escapa espacios, tabuladores, saltos de línea y barras
     * invertidas como secuencias octales (\040, \011, \012, \134).
     */
    private function unescape(string $value): string
    {
        return (string) preg_replace_callback('/\\\\([0-7]{3})/', fn (array $m) => chr((int) octdec($m[1])), $value);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * "//100.67.89.20/MrInsight/", "\\100.67.89.20\mrinsight" y
     * "//100.67.89.20/MrInsight" se consideran el mismo origen.
     */
    private function normalizeSource(string $source): string
    {
        return strtolower(rtrim(str_replace('\\', '/', trim($source)), '/'));
    }
}

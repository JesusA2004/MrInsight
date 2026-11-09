<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Nombres y rutas legibles para los archivos de la biblioteca, pensados para
 * que el contenido del NAS (o del servidor) se pueda explorar a mano:
 *
 *   mr-insight/general/fotos/portada-evento-cdmx-20261109-a7k2q9.jpg
 *   mr-insight/general/fotos/_variantes/portada-evento-cdmx-20261109-a7k2q9.webp
 *   mr-insight/general/fotos/_variantes/portada-evento-cdmx-20261109-a7k2q9-miniatura.webp
 *
 * Cada colección es una carpeta; los originales quedan a la vista y lo que
 * genera la plataforma (webp, miniaturas, portadas de video) vive aparte en
 * `_variantes/`. El sufijo de fecha + código corto evita colisiones sin
 * volver ilegible el nombre. Solo aplica a archivos nuevos: los existentes
 * conservan su ruta guardada en la base de datos.
 */
final class MediaPaths
{
    public const VARIANTS_DIR = '_variantes';

    private const MAX_SLUG_LENGTH = 60;

    /**
     * Nombre de archivo legible y único dentro de `$directory` en `$disk`.
     */
    public static function uniqueFilename(Filesystem $disk, string $directory, string $originalFilename): string
    {
        $extension = self::extension($originalFilename);
        $base = self::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'-'.now()->format('Ymd');

        do {
            $filename = $base.'-'.Str::lower(Str::random(6)).'.'.$extension;
        } while ($disk->exists(trim($directory, '/').'/'.$filename));

        return $filename;
    }

    /**
     * Ruta de una variante derivada del original: misma base, carpeta
     * `_variantes/` junto al original. `$suffix` vacío = versión principal.
     */
    public static function variantPath(string $originalPath, string $suffix, string $extension): string
    {
        $directory = dirname($originalPath);
        $basename = pathinfo($originalPath, PATHINFO_FILENAME);
        $name = $suffix === '' ? $basename : "{$basename}-{$suffix}";

        return ($directory === '.' ? '' : $directory.'/').self::VARIANTS_DIR."/{$name}.{$extension}";
    }

    public static function slug(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));
        $slug = rtrim(Str::limit($slug, self::MAX_SLUG_LENGTH, ''), '-');

        return $slug !== '' ? $slug : 'archivo';
    }

    private static function extension(string $originalFilename): string
    {
        $extension = Str::lower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        return $extension !== '' ? Str::limit($extension, 10, '') : 'bin';
    }
}

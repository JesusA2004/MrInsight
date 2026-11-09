<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Simula el NAS Synology montado por SMB: un directorio temporal como raíz
 * del disco media_synology y una tabla de montajes falsa (formato
 * /proc/mounts) que lo lista, o no, como CIFS con el origen indicado.
 *
 * @param  bool  $mounted  false: la carpeta existe pero no hay montaje (NAS caído).
 * @return string Ruta del directorio raíz del disco.
 */
function fakeSynologyMount(bool $mounted = true, string $source = '//100.67.89.20/MrInsight', string $fstype = 'cifs', ?string $root = null): string
{
    $root ??= sys_get_temp_dir().DIRECTORY_SEPARATOR.'mrinsight-nas-'.Str::uuid();
    if (! is_dir($root)) {
        mkdir($root, 0777, true);
    }

    $table = $root.'.mounts';
    setSynologyMountTable($table, $mounted ? [[$source, $root, $fstype]] : []);

    config([
        'filesystems.disks.media_synology.root' => $root,
        'filesystems.disks.media_synology.mounts_table' => $table,
        'filesystems.disks.media_synology.mount_source' => '//100.67.89.20/MrInsight',
    ]);
    Storage::forgetDisk('media_synology');

    register_shutdown_function(function () use ($root, $table) {
        File::deleteDirectory($root);
        @unlink($table);
    });

    return $root;
}

/**
 * Reescribe la tabla de montajes falsa (para simular que el NAS se cae o
 * vuelve). Escapa espacios como \040, igual que el kernel.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $entries  [origen, punto de montaje, fstype]
 */
function setSynologyMountTable(string $table, array $entries): void
{
    $escape = fn (string $value) => str_replace(' ', '\040', $value);
    $lines = ['proc /proc proc rw,nosuid,nodev,noexec 0 0', '/dev/sda1 / ext4 rw,relatime 0 0'];

    foreach ($entries as [$source, $mountPoint, $fstype]) {
        $lines[] = $escape($source).' '.$escape($mountPoint).' '.$fstype.' rw,relatime,vers=3.0,cache=strict,uid=33 0 0';
    }

    file_put_contents($table, implode("\n", $lines)."\n");
}

/**
 * Archivos (recursivo) que quedaron dentro de un directorio.
 *
 * @return list<string>
 */
function filesUnder(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    return array_map(fn ($file) => $file->getPathname(), File::allFiles($directory, true));
}

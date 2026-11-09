<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Disco físico para el proveedor de almacenamiento "local" de la
        // biblioteca multimedia de MR. INSIGHT. La entrega al navegador no
        // usa el symlink público de Laravel: pasa por un controlador propio
        // (ver MediaStreamController, Fase 6) para poder soportar peticiones
        // Range en video y, más adelante, servir igual desde el NAS Synology.
        'media_local' => [
            'driver' => 'local',
            'root' => storage_path('app/media'),
            'url' => '/media',
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Proveedor Synology NAS ("SynologyExtra"), alcanzable solo por la
        // red privada Tailscale (100.67.89.20). El VPS monta el recurso SMB
        // //100.67.89.20/MrInsight en /mnt/mrinsight-storage vía fstab; las
        // credenciales SMB viven únicamente en el sistema operativo, nunca
        // en Laravel ni en la base de datos. Para Laravel es un disco local
        // cuya raíz es el punto de montaje.
        //
        // Antes de escribir, App\Services\Storage\StorageHealth verifica en
        // la tabla de montajes (mounts_table) que el punto de montaje sea un
        // CIFS real con el origen mount_source: un directorio vacío (montaje
        // caído) nunca cuenta como conectado. lazy_root_creation evita que
        // instanciar el disco cree la carpeta raíz por su cuenta, y throw
        // hace que cualquier fallo de escritura/lectura sea una excepción.
        'media_synology' => [
            'driver' => 'local',
            'root' => env('NAS_MOUNT_PATH', '/mnt/mrinsight-storage'),
            'url' => '/media',
            'visibility' => 'private',
            'lazy_root_creation' => true,
            'throw' => true,
            'report' => false,
            'mount_source' => env('NAS_MOUNT_SOURCE', '//100.67.89.20/MrInsight'),
            'mount_fstype' => 'cifs',
            'mounts_table' => env('NAS_MOUNTS_TABLE', '/proc/mounts'),
            'display_name' => 'SynologyExtra',
            'display_address' => '100.67.89.20',
            'display_protocol' => 'SMB/CIFS 3.0',
            'display_share' => 'MrInsight',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

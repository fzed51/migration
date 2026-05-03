<?php

/**
 * User: fabien.sanchez
 * Date: 14/09/2018
 * Time: 09:35
 */

namespace Migration;

/**
 * Class MigrationConfig
 * @package Migration
 */
class MigrationConfig
{
    public readonly string $migration_directory;

    public function __construct(
        string $migration_directory,
        public readonly string $provider,
        public readonly string $host,
        public readonly int    $port,
        public readonly string $name,
        public readonly string $user,
        public readonly string $pass,
    ) {
        $migration_directory = str_replace('\\', '/', $migration_directory);
        $this->migration_directory = substr($migration_directory, -1) === '/'
            ? $migration_directory
            : $migration_directory . '/';
    }
}

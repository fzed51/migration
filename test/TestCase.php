<?php

namespace Migration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{

    protected const CONFIGFILE = "./migration-config.json";

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteConfigFile();
    }

    protected function deleteConfigFile(): void
    {
        if (is_file(self::CONFIGFILE)) {
            unlink(self::CONFIGFILE);
        }
    }
}

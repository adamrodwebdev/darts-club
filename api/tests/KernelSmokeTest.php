<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelSmokeTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        self::assertTrue(self::getContainer()->has('router'));
    }
}

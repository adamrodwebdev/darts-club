<?php

namespace App;

use App\Doctrine\EncryptedStringType;
use App\Security\Crypto\SecretCipher;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        $cipher = $this->getContainer()->get(SecretCipher::class);
        \assert($cipher instanceof SecretCipher);
        EncryptedStringType::useCipher($cipher);
    }
}

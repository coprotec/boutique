<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Fuseau métier de SmartOF et de la boutique, quel que soit le php.ini de la machine.
        date_default_timezone_set('Europe/Paris');

        parent::boot();
    }
}

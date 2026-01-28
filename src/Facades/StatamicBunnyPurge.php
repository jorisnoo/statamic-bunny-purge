<?php

namespace Noo\StatamicBunnyPurge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Noo\StatamicBunnyPurge\StatamicBunnyPurge
 */
class StatamicBunnyPurge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Noo\StatamicBunnyPurge\StatamicBunnyPurge::class;
    }
}

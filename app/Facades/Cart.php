<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Cart\Cart session($key)
 * @method static bool isEmpty()
 *
 * @see \App\Services\Cart\Cart
 */
class Cart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Cart\Cart::class;
    }
}

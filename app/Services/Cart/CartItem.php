<?php

namespace App\Services\Cart;

/**
 * A cart line. Any extra keys passed to Cart::add() (unit, costprice, ...) are
 * readable as properties; missing ones read as null.
 */
#[\AllowDynamicProperties]
class CartItem
{
    public array $attributes = [];

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function __get(string $name): mixed
    {
        return null;
    }

    public function getPriceSum(): float
    {
        return $this->price * $this->quantity;
    }
}

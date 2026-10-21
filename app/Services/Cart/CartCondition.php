<?php

namespace App\Services\Cart;

/**
 * A cart-wide adjustment such as tax or discount. Value is a signed amount or
 * percentage: "+7%", "-10%", "+50", "-20".
 */
class CartCondition
{
    public function __construct(protected array $args) {}

    public function getName(): string
    {
        return $this->args['name'];
    }

    public function getOrder(): int
    {
        return (int) ($this->args['order'] ?? 0);
    }

    public function getValue(): string
    {
        return (string) $this->args['value'];
    }

    /** The absolute amount this condition adds or removes from $total. */
    public function getCalculatedValue($total): float
    {
        $value = ltrim($this->getValue(), '+-');

        if (str_ends_with($value, '%')) {
            return abs($total * ((float) rtrim($value, '%') / 100));
        }

        return abs((float) $value);
    }

    public function applyCondition($total): float
    {
        $amount = $this->getCalculatedValue($total);

        return str_starts_with($this->getValue(), '-') ? $total - $amount : $total + $amount;
    }

    public function toArray(): array
    {
        return $this->args;
    }
}

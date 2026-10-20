<?php

namespace App\Services\Cart;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Session-backed cart covering the subset of darryldecode/cart's API the POS uses.
 * That package doesn't support Laravel 13, so this keeps the same call style:
 * Cart::session($userId)->add([...]), ->update($id, [...]), ->getTotal(), etc.
 */
class Cart
{
    protected string $sessionKey = 'default';

    public function session($key): static
    {
        $this->sessionKey = (string) $key;

        return $this;
    }

    public function add(array $item): static
    {
        if (! isset($item['id'], $item['name'], $item['price'], $item['quantity'])) {
            throw new InvalidArgumentException('Cart item requires id, name, price and quantity.');
        }

        $items = $this->items();
        $id = $item['id'];

        if (isset($items[$id])) {
            $items[$id]['quantity'] += (int) $item['quantity'];
        } else {
            $items[$id] = array_merge(['attributes' => []], $item, [
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ]);
        }

        $this->put('items', $items);

        return $this;
    }

    /**
     * Quantity changes are relative by default (like darryldecode/cart): pass
     * ['quantity' => ['relative' => false, 'value' => 5]] to set an absolute value.
     */
    public function update($id, array $data): bool
    {
        $items = $this->items();

        if (! isset($items[$id])) {
            return false;
        }

        foreach ($data as $key => $value) {
            if ($key === 'quantity') {
                $relative = is_array($value) ? ($value['relative'] ?? true) : true;
                $amount = (int) (is_array($value) ? $value['value'] : $value);
                $quantity = $relative ? $items[$id]['quantity'] + $amount : $amount;

                if ($quantity > 0) {
                    $items[$id]['quantity'] = $quantity;
                }
            } elseif ($key === 'price') {
                $items[$id]['price'] = (float) $value;
            } else {
                $items[$id][$key] = $value;
            }
        }

        $this->put('items', $items);

        return true;
    }

    public function remove($id): void
    {
        $items = $this->items();
        unset($items[$id]);

        $this->put('items', $items);
    }

    /** @return Collection<int|string, CartItem> */
    public function getContent(): Collection
    {
        return collect($this->items())->map(fn (array $item) => new CartItem($item));
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }

    public function clear(): void
    {
        $this->put('items', []);
    }

    public function getSubTotal(): float
    {
        return $this->getContent()->sum(fn (CartItem $item) => $item->getPriceSum());
    }

    public function getTotal(): float
    {
        return $this->conditions()
            ->sortBy(fn (CartCondition $condition) => $condition->getOrder())
            ->reduce(fn ($total, CartCondition $condition) => $condition->applyCondition($total), $this->getSubTotal());
    }

    public function condition(CartCondition $condition): static
    {
        $conditions = session($this->key('conditions'), []);
        $conditions[$condition->getName()] = $condition->toArray();

        $this->put('conditions', $conditions);

        return $this;
    }

    public function getCondition(string $name): ?CartCondition
    {
        return $this->conditions()->get($name);
    }

    /** @return Collection<string, CartCondition> */
    protected function conditions(): Collection
    {
        return collect(session($this->key('conditions'), []))
            ->map(fn (array $args) => new CartCondition($args));
    }

    protected function items(): array
    {
        return session($this->key('items'), []);
    }

    protected function put(string $part, array $value): void
    {
        session()->put($this->key($part), $value);
    }

    protected function key(string $part): string
    {
        return "cart.{$this->sessionKey}.{$part}";
    }
}

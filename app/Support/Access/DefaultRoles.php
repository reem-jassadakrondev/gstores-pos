<?php

namespace App\Support\Access;

use App\Support\Access\Permission as P;

/** Store-level role templates copied into every new organization (editable afterwards). */
final class DefaultRoles
{
    /** @return array<string, array{name: string, permissions: list<string>}> */
    public static function all(): array
    {
        return [
            'store_manager' => [
                'name' => 'ผู้จัดการร้าน',
                'permissions' => P::storeKeys(),
            ],
            'cashier' => [
                'name' => 'แคชเชียร์',
                'permissions' => [
                    P::SALES_SELL, P::SALES_OPEN_ORDER, P::SALES_DISCOUNT, P::SHIFT_OWN,
                    P::CATALOG_VIEW, P::CUSTOMERS_VIEW, P::REPORTS_OWN_SHIFT,
                ],
            ],
            'waiter' => [
                'name' => 'พนักงานเสิร์ฟ',
                'permissions' => [P::SALES_OPEN_ORDER, P::KITCHEN_VIEW, P::CATALOG_VIEW],
            ],
            'kitchen' => [
                'name' => 'ครัว',
                'permissions' => [P::KITCHEN_VIEW],
            ],
            'stock_keeper' => [
                'name' => 'พนักงานสต็อก',
                'permissions' => [
                    P::CATALOG_VIEW, P::STOCK_VIEW, P::STOCK_RECEIVE, P::STOCK_ADJUST, P::STOCK_TRANSFER, P::PURCHASING,
                ],
            ],
        ];
    }
}

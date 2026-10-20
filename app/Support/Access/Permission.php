<?php

namespace App\Support\Access;

/**
 * Catalog of every permission key. Store-level keys can be granted through roles;
 * org-level keys belong to org roles (owner/admin/accountant) only.
 */
final class Permission
{
    // ขาย
    public const SALES_SELL = 'sales.sell';

    public const SALES_OPEN_ORDER = 'sales.open_order';

    public const SALES_DISCOUNT = 'sales.discount';

    public const SALES_DISCOUNT_OVERRIDE = 'sales.discount_override';

    public const SALES_VOID = 'sales.void';

    public const SALES_REFUND = 'sales.refund';

    // กะ / ลิ้นชัก
    public const SHIFT_OWN = 'shift.own';

    public const SHIFT_ALL = 'shift.all';

    // ครัว
    public const KITCHEN_VIEW = 'kitchen.view';

    // สินค้า / สต็อก
    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_MANAGE = 'catalog.manage';

    public const STOCK_VIEW = 'stock.view';

    public const STOCK_RECEIVE = 'stock.receive';

    public const STOCK_ADJUST = 'stock.adjust';

    public const STOCK_TRANSFER = 'stock.transfer';

    // ลูกค้า
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    // รายงาน
    public const REPORTS_OWN_SHIFT = 'reports.own_shift';

    public const REPORTS_SALES = 'reports.sales';

    public const REPORTS_FINANCE = 'reports.finance';

    // จัดการร้าน
    public const STORE_SETTINGS = 'store.settings';

    public const STORE_STAFF = 'store.staff';

    public const STORE_TERMINALS = 'store.terminals';

    // ระดับ Organization (ให้ผ่าน role ไม่ได้)
    public const ORG_BILLING = 'org.billing';

    public const ORG_DELETE = 'org.delete';

    public const ORG_MEMBERS = 'org.members';

    public const ORG_ROLES = 'org.roles';

    public const ORG_ENTITIES = 'org.entities';

    public const ORG_STORES = 'org.stores';

    public const AUDIT_VIEW = 'audit.view';

    /** @return array<string, array<string, string>> group label => [key => label] */
    public static function storeGroups(): array
    {
        return [
            'การขาย' => [
                self::SALES_SELL => 'ขายสินค้า / รับชำระเงิน',
                self::SALES_OPEN_ORDER => 'เปิดออเดอร์ / สั่งอาหาร',
                self::SALES_DISCOUNT => 'ให้ส่วนลดภายในเพดาน',
                self::SALES_DISCOUNT_OVERRIDE => 'อนุมัติส่วนลดเกินเพดาน (PIN ผู้จัดการ)',
                self::SALES_VOID => 'ยกเลิกบิล',
                self::SALES_REFUND => 'คืนเงิน / ใบลดหนี้',
            ],
            'กะและลิ้นชัก' => [
                self::SHIFT_OWN => 'เปิด/ปิดกะของตัวเอง',
                self::SHIFT_ALL => 'จัดการกะของทุกคน',
            ],
            'ครัว' => [
                self::KITCHEN_VIEW => 'ดูและอัปเดตออเดอร์ครัว',
            ],
            'สินค้าและสต็อก' => [
                self::CATALOG_VIEW => 'ดูสินค้า',
                self::CATALOG_MANAGE => 'จัดการสินค้าและราคา',
                self::STOCK_VIEW => 'ดูสต็อก',
                self::STOCK_RECEIVE => 'รับสินค้าเข้า',
                self::STOCK_ADJUST => 'ปรับสต็อก / ตรวจนับ',
                self::STOCK_TRANSFER => 'โอนสต็อก',
            ],
            'ลูกค้า' => [
                self::CUSTOMERS_VIEW => 'ดูลูกค้า',
                self::CUSTOMERS_MANAGE => 'จัดการลูกค้า / สมาชิก',
            ],
            'รายงาน' => [
                self::REPORTS_OWN_SHIFT => 'ดูยอดขายกะของตัวเอง',
                self::REPORTS_SALES => 'ดูรายงานยอดขาย',
                self::REPORTS_FINANCE => 'ดูต้นทุนและกำไร',
            ],
            'จัดการร้าน' => [
                self::STORE_SETTINGS => 'ตั้งค่าร้านและสาขา',
                self::STORE_STAFF => 'จัดการพนักงานในร้าน',
                self::STORE_TERMINALS => 'จัดการเครื่อง POS',
            ],
        ];
    }

    /** @return list<string> */
    public static function storeKeys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::storeGroups())));
    }

    /** @return list<string> */
    public static function orgKeys(): array
    {
        return [
            self::ORG_BILLING, self::ORG_DELETE, self::ORG_MEMBERS, self::ORG_ROLES,
            self::ORG_ENTITIES, self::ORG_STORES, self::AUDIT_VIEW,
        ];
    }

    /** Read-only access the accountant org role gets in every store. */
    public static function accountantKeys(): array
    {
        return [
            self::CATALOG_VIEW, self::STOCK_VIEW, self::CUSTOMERS_VIEW,
            self::REPORTS_SALES, self::REPORTS_FINANCE, self::AUDIT_VIEW,
        ];
    }

    /** Keys even an org admin doesn't get. */
    public static function ownerOnlyKeys(): array
    {
        return [self::ORG_BILLING, self::ORG_DELETE];
    }
}

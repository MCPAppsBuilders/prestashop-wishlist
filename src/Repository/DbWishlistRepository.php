<?php

namespace Summit\Wishlist\Repository;

use Db;
use RuntimeException;

class DbWishlistRepository implements WishlistRepositoryInterface
{
    private const LISTS = 'summitwishlist';
    private const ITEMS = 'summitwishlist_product';

    /** @var Db */
    private $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function findDefault(int $shopId, string $ownerKey): ?array
    {
        $row = $this->db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::LISTS . '`
            WHERE `id_shop` = ' . $shopId . "
              AND `owner_key` = '" . pSQL($ownerKey) . "'
              AND `default_marker` = 1",
            false
        );

        return $row ?: null;
    }

    public function createDefault(int $shopId, string $ownerKey, ?int $customerId, string $name): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert(self::LISTS, [
            'id_shop' => $shopId,
            'owner_key' => pSQL($ownerKey),
            'id_customer' => $customerId,
            'name' => pSQL($name),
            'default_marker' => 1,
            'date_add' => $now,
            'date_upd' => $now,
        ], true, false, Db::INSERT_IGNORE);

        // INSERT IGNORE also hides unrelated failures, so re-read and fail loudly.
        $row = $this->findDefault($shopId, $ownerKey);
        if ($row === null) {
            throw new RuntimeException('Could not create or find the default wishlist.');
        }

        return $row;
    }

    public function find(int $wishlistId): ?array
    {
        $row = $this->db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::LISTS . '` WHERE `id_wishlist` = ' . $wishlistId,
            false
        );

        return $row ?: null;
    }

    public function getItems(int $wishlistId): array
    {
        return $this->db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::ITEMS . '`
            WHERE `id_wishlist` = ' . $wishlistId . '
            ORDER BY `id_wishlist_product` ASC',
            true,
            false
        ) ?: [];
    }

    public function getItemsByIds(int $wishlistId, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if (!$itemIds) {
            return [];
        }

        return $this->db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::ITEMS . '`
            WHERE `id_wishlist` = ' . $wishlistId . '
              AND `id_wishlist_product` IN (' . implode(',', $itemIds) . ')
            ORDER BY `id_wishlist_product` ASC',
            true,
            false
        ) ?: [];
    }

    public function countItems(int $wishlistId): int
    {
        return (int) $this->db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::ITEMS . '` WHERE `id_wishlist` = ' . $wishlistId,
            false
        );
    }

    public function addItem(int $wishlistId, int $productId, int $productAttributeId, string $fallbackName): bool
    {
        $this->db->insert(self::ITEMS, [
            'id_wishlist' => $wishlistId,
            'id_product' => $productId,
            'id_product_attribute' => $productAttributeId,
            'fallback_name' => pSQL(mb_substr($fallbackName, 0, 255)),
            'date_add' => date('Y-m-d H:i:s'),
        ], false, false, Db::INSERT_IGNORE);

        if ($this->db->Affected_Rows() > 0) {
            $this->touch($wishlistId);

            return true;
        }

        $exists = (bool) $this->db->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . self::ITEMS . '`
            WHERE `id_wishlist` = ' . $wishlistId . '
              AND `id_product` = ' . $productId . '
              AND `id_product_attribute` = ' . $productAttributeId,
            false
        );
        if (!$exists) {
            throw new RuntimeException('Could not add the wishlist item.');
        }

        return false;
    }

    public function removeItem(int $wishlistId, int $itemId): bool
    {
        $this->db->delete(self::ITEMS, '`id_wishlist` = ' . $wishlistId . ' AND `id_wishlist_product` = ' . $itemId);
        $removed = $this->db->Affected_Rows() > 0;
        if ($removed) {
            $this->touch($wishlistId);
        }

        return $removed;
    }

    public function deleteByCustomer(int $customerId): void
    {
        $this->db->execute(
            'DELETE i FROM `' . _DB_PREFIX_ . self::ITEMS . '` i
            INNER JOIN `' . _DB_PREFIX_ . self::LISTS . '` w ON w.`id_wishlist` = i.`id_wishlist`
            WHERE w.`id_customer` = ' . $customerId
        );
        $this->db->delete(self::LISTS, '`id_customer` = ' . $customerId);
    }

    private function touch(int $wishlistId): void
    {
        $this->db->update(self::LISTS, ['date_upd' => date('Y-m-d H:i:s')], '`id_wishlist` = ' . $wishlistId);
    }
}

<?php

namespace Summit\Wishlist\Repository;

interface WishlistRepositoryInterface
{
    public function findDefault(int $shopId, string $ownerKey): ?array;

    /**
     * Creates the owner's default list if missing and returns it. Race-safe via the unique key.
     */
    public function createDefault(int $shopId, string $ownerKey, ?int $customerId, string $name): array;

    public function find(int $wishlistId): ?array;

    /**
     * @return array[] items ordered by insertion
     */
    public function getItems(int $wishlistId): array;

    /**
     * @param int[] $itemIds
     *
     * @return array[] only items that belong to the wishlist
     */
    public function getItemsByIds(int $wishlistId, array $itemIds): array;

    public function countItems(int $wishlistId): int;

    /**
     * @return bool true when a new row was inserted, false when it already existed
     */
    public function addItem(int $wishlistId, int $productId, int $productAttributeId, string $fallbackName): bool;

    public function removeItem(int $wishlistId, int $itemId): bool;

    public function deleteByCustomer(int $customerId): void;
}

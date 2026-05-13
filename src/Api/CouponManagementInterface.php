<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Api;

interface CouponManagementInterface
{
    /**
     * Update a coupon code by its ID.
     *
     * @param int $couponId
     * @param array<string, mixed> $couponData
     *
     * @return array<string, mixed>
     */
    public function updateCoupon(int $couponId, array $couponData): array;
}

<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Model;

use Ayasoftware\McpApi\Api\CouponManagementInterface;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class CouponManagement implements CouponManagementInterface
{
    public function __construct(
        private readonly CouponRepositoryInterface $couponRepository
    ) {
    }

    public function updateCoupon(int $couponId, array $couponData): array
    {
        try {
            // Get the existing coupon
            $coupon = $this->couponRepository->getById($couponId);

            // Update allowed fields
            if (isset($couponData['code'])) {
                $coupon->setCode((string) $couponData['code']);
            }

            if (isset($couponData['usage_limit'])) {
                $coupon->setUsageLimit((int) $couponData['usage_limit']);
            }

            if (isset($couponData['usage_per_customer'])) {
                $coupon->setUsagePerCustomer((int) $couponData['usage_per_customer']);
            }

            if (isset($couponData['times_used'])) {
                $coupon->setTimesUsed((int) $couponData['times_used']);
            }

            if (isset($couponData['expiration_date'])) {
                $coupon->setExpirationDate((string) $couponData['expiration_date']);
            }

            if (isset($couponData['is_primary'])) {
                $coupon->setIsPrimary((bool) $couponData['is_primary']);
            }

            // Save the updated coupon
            $updatedCoupon = $this->couponRepository->save($coupon);

            return [
                'success' => true,
                'message' => sprintf('Coupon ID %d updated successfully.', $couponId),
                'coupon' => [
                    'coupon_id' => (int) $updatedCoupon->getCouponId(),
                    'rule_id' => (int) $updatedCoupon->getRuleId(),
                    'code' => (string) $updatedCoupon->getCode(),
                    'usage_limit' => (int) $updatedCoupon->getUsageLimit(),
                    'usage_per_customer' => (int) $updatedCoupon->getUsagePerCustomer(),
                    'times_used' => (int) $updatedCoupon->getTimesUsed(),
                    'expiration_date' => (string) $updatedCoupon->getExpirationDate(),
                    'is_primary' => (bool) $updatedCoupon->getIsPrimary(),
                    'created_at' => (string) $updatedCoupon->getCreatedAt(),
                ],
            ];
        } catch (NoSuchEntityException $exception) {
            return [
                'success' => false,
                'message' => sprintf('Coupon ID %d not found.', $couponId),
                'error' => $exception->getMessage(),
            ];
        } catch (LocalizedException $exception) {
            return [
                'success' => false,
                'message' => sprintf('Failed to update coupon ID %d.', $couponId),
                'error' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => sprintf('An unexpected error occurred while updating coupon ID %d.', $couponId),
                'error' => $exception->getMessage(),
            ];
        }
    }
}

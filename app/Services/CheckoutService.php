<?php

namespace App\Services;

use App\Constants\OrderStatus;
use App\Constants\PlanType;
use App\Dto\CartDto;

class CheckoutService
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private OrderService $orderService,
        private TenantCreationService $tenantCreationService,
    ) {}

    public function initSubscriptionCheckout(string $planSlug, ?string $tenantUuid, int $quantity = 1)
    {
        $tenant = $this->tenantCreationService->findUserTenantForNewSubscriptionByUuid(auth()->user(), $tenantUuid);

        if ($tenant === null) {
            $tenant = $this->tenantCreationService->createTenant(auth()->user());
        }

        $subscription = $this->subscriptionService->findNewByPlanSlugAndTenant($planSlug, $tenant);
        if ($subscription === null) {
            $subscription = $this->subscriptionService->create(
                planSlug: $planSlug,
                userId: auth()->id(),
                quantity: $quantity,
                tenant: $tenant,
            );
        }

        $plan = $subscription->plan;

        if ($plan->type === PlanType::SEAT_BASED->value) { // in case tenant already had users inside
            $subscription->update(['quantity' => max($tenant->users->count(), $quantity)]);
        }

        return $subscription;
    }

    public function initLocalSubscriptionCheckout(string $planSlug, ?string $tenantUuid, int $quantity = 1)
    {
        $tenant = $this->tenantCreationService->findUserTenantForNewSubscriptionByUuid(auth()->user(), $tenantUuid);

        if ($tenant === null) {
            $tenant = $this->tenantCreationService->createTenant(auth()->user());
        }

        $subscription = $this->subscriptionService->findNewByPlanSlugAndTenant($planSlug, $tenant);
        if ($subscription === null) {
            $subscription = $this->subscriptionService->create(
                $planSlug,
                auth()->id(),
                quantity: $quantity,
                tenant: $tenant,
                localSubscription: true);
        }

        $plan = $subscription->plan;

        if ($plan->type === PlanType::SEAT_BASED->value) { // in case tenant already had users inside
            $subscription->update(['quantity' => max($tenant->users->count(), $quantity)]);
        }

        return $subscription;
    }

    public function initProductCheckout(CartDto $cartDto, ?string $tenantUuid)
    {
        $user = auth()->user();

        $tenant = $this->tenantCreationService->findUserTenantForNewOrderByUuid($user, $tenantUuid);

        if ($tenant === null) {
            $tenant = $this->tenantCreationService->createTenant($user);
        }

        $order = null;
        if ($cartDto->orderId !== null) {
            $order = $this->orderService->findNewByIdForUser($cartDto->orderId, $user);
        }

        if ($order === null) {
            $order = $this->orderService->create($user, $tenant);
        }

        $this->orderService->refreshOrder($cartDto, $order);

        $order->status = OrderStatus::PENDING->value;
        $order->save();

        return $order;
    }
}

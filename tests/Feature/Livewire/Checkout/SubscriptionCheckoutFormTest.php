<?php

namespace Tests\Feature\Livewire\Checkout;

use App\Constants\PlanPriceType;
use App\Constants\PlanType;
use App\Constants\SessionConstants;
use App\Constants\SubscriptionStatus;
use App\Constants\TenancyPermissionConstants;
use App\Dto\SubscriptionCheckoutDto;
use App\Livewire\Checkout\SubscriptionCheckoutForm;
use App\Models\Currency;
use App\Models\Interval;
use App\Models\PaymentProvider;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSubscriptionTrial;
use App\Services\PaymentProviders\PaymentProviderInterface;
use App\Services\PaymentProviders\PaymentService;
use Exception;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class SubscriptionCheckoutFormTest extends FeatureTest
{
    public function test_can_checkout_new_user()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);

        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();
        $tenantsBefore = Tenant::count();
        $email = 'something+'.rand(1, 1000000).'@gmail.com';

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    public function test_can_checkout_existing_user()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();
        $tenantsBefore = Tenant::count();

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    public function test_can_checkout_one_subscription_per_tenant_when_multiple_subscriptions_is_disabled()
    {
        config()->set('app.tenant_multiple_subscriptions_enabled', false);

        $planSlug = 'plan-slug-'.rand(1, 1000000);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_CREATE_SUBSCRIPTIONS,
        ], [
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;
        $sessionDto->tenantUuid = $tenant->uuid;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $existingSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'plan_id' => $plan->id,
            'ends_at' => now(),
            'tenant_id' => $tenant->id,
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();
        $tenantsBefore = Tenant::count();

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());

        $sessionDto = session(SessionConstants::SUBSCRIPTION_CHECKOUT_DTO);

        $this->assertNotEquals($existingSubscription->tenant_id, Subscription::find($sessionDto->subscriptionId)->tenant_id);
    }

    public function test_can_checkout_multiple_subscriptions_per_tenant_when_multiple_subscriptions_is_enabled()
    {
        config()->set('app.tenant_multiple_subscriptions_enabled', true);

        $planSlug = 'plan-slug-'.rand(1, 1000000);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_CREATE_SUBSCRIPTIONS,
        ], [
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;
        $sessionDto->tenantUuid = $tenant->uuid;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $existingSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'plan_id' => $plan->id,
            'ends_at' => now(),
            'tenant_id' => $tenant->id,
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();
        $tenantsBefore = Tenant::count();

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
        $this->assertEquals($tenantsBefore, Tenant::count());

        $sessionDto = session(SessionConstants::SUBSCRIPTION_CHECKOUT_DTO);

        $this->assertEquals($existingSubscription->tenant_id, Subscription::find($sessionDto->subscriptionId)->tenant_id);
    }

    public function test_can_checkout_existing_user_no_trial_if_user_is_not_eligible()
    {
        config()->set('app.limit_user_trials.enabled', true);
        config()->set('app.limit_user_trials.max_count', 1);

        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
            'has_trial' => true,
            'trial_interval_count' => 1,
            'trial_interval_id' => Interval::where('slug', 'week')->first()->id,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        $tenant = $this->createTenant();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'plan_id' => $plan->id,
            'ends_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'tenant_id' => $tenant->id,
        ]);

        UserSubscriptionTrial::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->actingAs($user);

        Livewire::test(SubscriptionCheckoutForm::class)
            ->assertDontSeeHtml('trial');
    }

    public function test_can_not_checkout_if_payment_does_not_support_plan_type()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
            'type' => PlanType::USAGE_BASED->value,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
            'price_per_unit' => 20,
            'type' => PlanPriceType::USAGE_BASED_PER_UNIT->value,
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldNotReceive('initSubscriptionCheckout');

        $this->expectException(Exception::class);

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout');
    }

    public function test_checkout_success_if_plan_type_is_usage_based()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
            'type' => PlanType::USAGE_BASED->value,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
            'price_per_unit' => 20,
            'type' => PlanPriceType::USAGE_BASED_PER_UNIT->value,
        ]);

        $paymentProvider = $this->addPaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();

        $email = 'existing+'.rand(1, 1000000).'@gmail.com';

        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
    }

    public function test_can_checkout_overlay_payment()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $paymentProvider = $this->addPaymentProvider(false);

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::USAGE_BASED->value,
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();
        $tenantsBefore = Tenant::count();

        $email = 'something+'.rand(1, 1000000).'@gmail.com';

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertDispatched('start-overlay-checkout');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    public function test_can_checkout_offline_payment()
    {
        $planSlug = 'plan-slug-'.rand(1, 1000000);
        $sessionDto = new SubscriptionCheckoutDto;
        $sessionDto->planSlug = $planSlug;

        $this->withSession([SessionConstants::SUBSCRIPTION_CHECKOUT_DTO => $sessionDto]);

        $plan = Plan::factory()->create([
            'slug' => $planSlug,
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $paymentProvider = $this->addOfflinePaymentProvider();

        $paymentProvider->shouldReceive('getSupportedPlanTypes')
            ->andReturn([
                PlanType::FLAT_RATE->value,
            ]);

        $paymentProvider->shouldReceive('initSubscriptionCheckout')
            ->once()
            ->andReturn([]);

        // get number of subscriptions before checkout
        $subscriptionsBefore = Subscription::count();

        $email = 'something+'.rand(1, 1000000).'@gmail.com';

        Livewire::test(SubscriptionCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', $email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore-offline')
            ->call('checkout')
            ->assertRedirectToRoute('checkout.subscription.success');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($subscriptionsBefore + 1, Subscription::count());
    }

    private function addOfflinePaymentProvider()
    {
        // find or create payment provider
        PaymentProvider::updateOrCreate([
            'slug' => 'paymore-offline',
        ], [
            'name' => 'Paymore Offline',
            'is_active' => true,
            'type' => 'any',
        ]);

        $mock = \Mockery::mock(PaymentProviderInterface::class);

        $mock->shouldReceive('isRedirectProvider')
            ->andReturn(false);

        $mock->shouldReceive('getSlug')
            ->andReturn('paymore-offline');

        $mock->shouldReceive('getName')
            ->andReturn('Paymore Offline');

        $mock->shouldReceive('isOverlayProvider')
            ->andReturn(false);

        $this->app->instance(PaymentProviderInterface::class, $mock);

        $this->app->bind(PaymentService::class, function () use ($mock) {
            return new PaymentService($mock);
        });

        return $mock;
    }

    private function addPaymentProvider(bool $isRedirect = true)
    {
        // find or create payment provider
        PaymentProvider::updateOrCreate([
            'slug' => 'paymore',
        ], [
            'name' => 'Paymore',
            'is_active' => true,
            'type' => 'any',
        ]);

        $mock = \Mockery::mock(PaymentProviderInterface::class);

        $mock->shouldReceive('isRedirectProvider')
            ->andReturn($isRedirect);

        $mock->shouldReceive('getSlug')
            ->andReturn('paymore');

        $mock->shouldReceive('getName')
            ->andReturn('Paymore');

        $mock->shouldReceive('isOverlayProvider')
            ->andReturn(! $isRedirect);

        if ($isRedirect) {
            $mock->shouldReceive('createSubscriptionCheckoutRedirectLink')
                ->andReturn('http://paymore.com/checkout');
        }

        $this->app->instance(PaymentProviderInterface::class, $mock);

        $this->app->bind(PaymentService::class, function () use ($mock) {
            return new PaymentService($mock);
        });

        return $mock;
    }
}

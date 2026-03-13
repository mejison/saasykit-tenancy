<?php

namespace Database\Seeders\VvvebTenancy;

use App\Constants\PlanType;
use App\Constants\TenantDomainMetadata;
use App\Models\Currency;
use App\Models\Interval;
use App\Models\Plan;
use App\Models\Product;
use Database\Seeders\CurrenciesSeeder;
use Database\Seeders\IntervalsSeeder;
use Illuminate\Database\Seeder;

class VvvebTenancyPlansSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IntervalsSeeder::class,
            CurrenciesSeeder::class,
        ]);

        $usd = Currency::query()->where('code', 'USD')->first();
        $month = Interval::query()->where('slug', 'month')->first();
        $year = Interval::query()->where('slug', 'year')->first();
        $week = Interval::query()->where('slug', 'week')->first();

        if (! $usd || ! $month || ! $year || ! $week) {
            $missing = collect([
                'USD' => (bool) $usd,
                'month' => (bool) $month,
                'year' => (bool) $year,
                'week' => (bool) $week,
            ])->filter(fn (bool $ok) => ! $ok)->keys()->implode(', ');

            $this->command?->warn('VvvebTenancyPlansSeeder skipped (missing seeds): '.$missing);

            return;
        }

        $this->seedPlan(
            name: 'Plan 1',
            slug: 'plan-1',
            description: 'Plan 1 (path-based URL only)',
            productDefaults: ['is_default' => true, 'is_popular' => false],
            metadata: [
                TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
                TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
                TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
            ],
            usdId: (int) $usd->id,
            monthId: (int) $month->id,
            yearId: (int) $year->id,
            weekId: (int) $week->id,
            priceMonthly: 1000,
            priceYearly: 10000,
        );

        $this->seedPlan(
            name: 'Plan 2',
            slug: 'plan-2',
            description: 'Plan 2 (path-based URL + subdomain)',
            productDefaults: ['is_default' => false, 'is_popular' => true],
            metadata: [
                TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
                TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
                TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
            ],
            usdId: (int) $usd->id,
            monthId: (int) $month->id,
            yearId: (int) $year->id,
            weekId: (int) $week->id,
            priceMonthly: 2500,
            priceYearly: 25000,
        );

        $this->seedPlan(
            name: 'Plan 3',
            slug: 'plan-3',
            description: 'Plan 3 (path-based URL + subdomain + custom domain)',
            productDefaults: ['is_default' => false, 'is_popular' => false],
            metadata: [
                TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
                TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
                TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
            ],
            usdId: (int) $usd->id,
            monthId: (int) $month->id,
            yearId: (int) $year->id,
            weekId: (int) $week->id,
            priceMonthly: 5000,
            priceYearly: 50000,
        );
    }

    private function seedPlan(
        string $name,
        string $slug,
        string $description,
        array $productDefaults,
        array $metadata,
        int $usdId,
        int $monthId,
        int $yearId,
        int $weekId,
        int $priceMonthly,
        int $priceYearly,
    ): void {
        $product = Product::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'features' => $this->buildFeatures($metadata),
                'is_default' => (bool) ($productDefaults['is_default'] ?? false),
                'is_popular' => (bool) ($productDefaults['is_popular'] ?? false),
                'metadata' => $metadata,
            ],
        );

        $this->seedIntervalPlan(
            productId: (int) $product->id,
            name: $name.' Monthly',
            slug: $slug.'-monthly',
            intervalId: $monthId,
            trialIntervalId: $weekId,
            usdId: $usdId,
            price: $priceMonthly,
        );

        $this->seedIntervalPlan(
            productId: (int) $product->id,
            name: $name.' Yearly',
            slug: $slug.'-yearly',
            intervalId: $yearId,
            trialIntervalId: $weekId,
            usdId: $usdId,
            price: $priceYearly,
        );
    }


    private function buildFeatures(array $metadata): array
    {
        $features = [
            ['feature' => 'Path-based URL'],
        ];

        if (! empty($metadata[TenantDomainMetadata::SUBDOMAIN_ENABLED])) {
            $features[] = ['feature' => 'Subdomain URL'];
        }

        if (! empty($metadata[TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED])) {
            $features[] = ['feature' => 'Custom domain'];
        }

        return $features;
    }

    private function seedIntervalPlan(
        int $productId,
        string $name,
        string $slug,
        int $intervalId,
        int $trialIntervalId,
        int $usdId,
        int $price,
    ): void {
        $plan = Plan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $name,
                'product_id' => $productId,
                'interval_id' => $intervalId,
                'interval_count' => 1,
                'has_trial' => true,
                'trial_interval_id' => $trialIntervalId,
                'trial_interval_count' => 1,
                'is_active' => true,
                'type' => PlanType::SEAT_BASED->value,
                'max_users_per_tenant' => 0,
            ],
        );

        $plan->prices()->updateOrCreate(
            ['currency_id' => $usdId],
            ['price' => $price],
        );
    }
}

<?php

namespace Database\Seeders\Testing;

use App\Constants\TenantDomainMetadata;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // run only in testing environment
        if (app()->environment() !== 'testing') {
            return;
        }

        DB::table('products')->upsert([
            [
                'name' => 'Product 1',
                'slug' => 'product-1',
                'description' => 'Product 1 description',
                'metadata' => json_encode([
                    TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                    TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
                    TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
                    TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
                ]),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Product 2',
                'slug' => 'product-2',
                'description' => 'Product 2 description',
                'metadata' => json_encode([
                    TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                    TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
                    TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
                    TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
                ]),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ], ['slug'], ['name', 'description', 'metadata']);
    }
}

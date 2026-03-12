<?php

use App\Constants\TenantSiteProvisioningStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('external_site_id')->nullable();
            $table->string('external_site_uuid')->nullable();
            $table->string('builder_url')->nullable();
            $table->string('provisioning_status')->default(TenantSiteProvisioningStatus::PENDING->value);
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
            $table->unique(['provider', 'external_site_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_sites');
    }
};
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
        Schema::create('tenant_onboarding_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->onDelete('cascade');
            $table->string('brand_name')->nullable();
            $table->string('username_slug')->nullable()->unique();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->string('site_provisioning_status')->default(TenantSiteProvisioningStatus::DRAFT->value);
            $table->text('site_provisioning_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_profiles');
    }
};
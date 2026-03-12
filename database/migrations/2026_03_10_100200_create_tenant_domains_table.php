<?php

use App\Constants\TenantDomainStatus;
use App\Constants\TenantDomainType;
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
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('host');
            $table->string('path')->nullable();
            $table->string('type')->default(TenantDomainType::PATH->value);
            $table->string('status')->default(TenantDomainStatus::PENDING->value);
            $table->string('source')->default('system');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('connected_to_provider_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->unique(['host', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
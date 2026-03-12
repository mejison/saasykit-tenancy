<?php

namespace App\Services;

use App\Constants\TenantDomainMetadata;
use App\Models\Tenant;

class TenantDomainEntitlementService
{
    public function getCapabilities(Tenant $tenant): array
    {
        $metadata = $this->normalizeMetadata($tenant->subscriptionProductMetadata());

        return [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => $this->resolveBooleanCapability($metadata, TenantDomainMetadata::PATH_DOMAIN_ENABLED, true),
            TenantDomainMetadata::SUBDOMAIN_ENABLED => $this->resolveBooleanCapability($metadata, TenantDomainMetadata::SUBDOMAIN_ENABLED, false),
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => $this->resolveBooleanCapability($metadata, TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED, false),
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => $this->resolveIntegerCapability($metadata, TenantDomainMetadata::MAX_CUSTOM_DOMAINS, 0),
        ];
    }

    public function allowsPathDomain(Tenant $tenant): bool
    {
        return (bool) $this->getCapabilities($tenant)[TenantDomainMetadata::PATH_DOMAIN_ENABLED];
    }

    public function allowsSubdomain(Tenant $tenant): bool
    {
        return (bool) $this->getCapabilities($tenant)[TenantDomainMetadata::SUBDOMAIN_ENABLED];
    }

    public function allowsCustomDomains(Tenant $tenant): bool
    {
        $capabilities = $this->getCapabilities($tenant);

        return (bool) $capabilities[TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED]
            && (int) $capabilities[TenantDomainMetadata::MAX_CUSTOM_DOMAINS] > 0;
    }

    public function getMaxCustomDomains(Tenant $tenant): int
    {
        return (int) $this->getCapabilities($tenant)[TenantDomainMetadata::MAX_CUSTOM_DOMAINS];
    }

    private function normalizeMetadata(array $metadata): array
    {
        if ($this->isAssociative($metadata)) {
            return [$metadata];
        }

        return array_values(array_filter($metadata, 'is_array'));
    }

    private function resolveBooleanCapability(array $metadataList, string $key, bool $default): bool
    {
        if ($metadataList === []) {
            return $default;
        }

        $resolved = [];

        foreach ($metadataList as $metadata) {
            if (array_key_exists($key, $metadata)) {
                $resolved[] = filter_var($metadata[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
            }
        }

        if ($resolved === []) {
            return $default;
        }

        return in_array(true, $resolved, true);
    }

    private function resolveIntegerCapability(array $metadataList, string $key, int $default): int
    {
        if ($metadataList === []) {
            return $default;
        }

        $values = [];
        foreach ($metadataList as $metadata) {
            if (array_key_exists($key, $metadata)) {
                $values[] = max(0, (int) $metadata[$key]);
            }
        }

        if ($values === []) {
            return $default;
        }

        return max($values);
    }

    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
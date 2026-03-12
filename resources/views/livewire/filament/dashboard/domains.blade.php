<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Provisioning Status') }}</div>
            <div class="mt-2 text-lg font-semibold capitalize">{{ str_replace('_', ' ', $provisioningStatus ?: 'draft') }}</div>
            @if ($canRetryProvisioning)
                <div class="mt-3">
                    <x-filament::button size="sm" wire:click="retryProvisioning">
                        {{ __('Retry Provisioning') }}
                    </x-filament::button>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Primary URL') }}</div>
            <div class="mt-2 text-lg font-semibold break-all">
                {{ $primaryDomain ? ($primaryDomain->path ? $primaryDomain->host.'/'.$primaryDomain->path : $primaryDomain->host) : __('Not assigned yet') }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Builder Site') }}</div>
            <div class="mt-2 text-lg font-semibold break-all">{{ $externalSiteId ?? __('Pending') }}</div>
            @if ($builderUrl)
                <div class="mt-3">
                    <a href="{{ $builderUrl }}" class="text-sm font-medium text-primary-600 hover:underline" target="_blank" rel="noreferrer">
                        {{ __('Open Builder') }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    @if ($provisioningError)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 shadow-sm dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-100">
            <div class="font-semibold">{{ __('Last Provisioning Error') }}</div>
            <div class="mt-2 break-words">{{ $provisioningError }}</div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Path Domain') }}</div>
            <div class="mt-2 text-lg font-semibold">{{ !empty($capabilities['path_domain_enabled']) ? __('Enabled') : __('Disabled') }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Subdomain') }}</div>
            <div class="mt-2 text-lg font-semibold">{{ !empty($capabilities['subdomain_enabled']) ? __('Enabled') : __('Not included') }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Custom Domains') }}</div>
            <div class="mt-2 text-lg font-semibold">{{ !empty($capabilities['custom_domain_enabled']) ? __('Enabled') : __('Not included') }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Custom Domain Limit') }}</div>
            <div class="mt-2 text-lg font-semibold">{{ $capabilities['max_custom_domains'] ?? 0 }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="mb-4">
            <h3 class="text-lg font-semibold">{{ __('Add Custom Domain') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Custom domains are limited by the metadata of the active product attached to this workspace.') }}
            </p>
        </div>

        @if ($canAddCustomDomain)
            <form wire:submit="addCustomDomain">
                {{ $this->form }}

                <div class="pt-4 flex gap-4">
                    <x-filament::button type="submit">
                        <x-filament::loading-indicator class="h-5 w-5 inline" wire:loading />
                        {{ __('Add Domain') }}
                    </x-filament::button>
                </div>
            </form>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                {{ __('Your current plan does not allow additional custom domains, or you have reached the current limit.') }}
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="mb-4">
            <h3 class="text-lg font-semibold">{{ __('Attached Domains') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Manage path, subdomain, and custom domain assignments for this workspace.') }}
            </p>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
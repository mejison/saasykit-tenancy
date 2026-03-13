<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantDomainType;
use App\Constants\TenantSiteProvisioningStatus;
use App\Constants\SubscriptionConstants;
use App\Constants\SubscriptionStatus;
use App\Models\TenantDomain;
use App\Services\TenantDomainEntitlementService;
use App\Services\TenantDomainService;
use App\Services\VvvebProvisioningService;
use App\Services\VvvebHandoffService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class Domains extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    private TenantDomainService $tenantDomainService;

    private TenantDomainEntitlementService $tenantDomainEntitlementService;

    private VvvebProvisioningService $vvvebProvisioningService;

    private VvvebHandoffService $vvvebHandoffService;

    public ?array $data = [];

    public function boot(
        TenantDomainService $tenantDomainService,
        TenantDomainEntitlementService $tenantDomainEntitlementService,
        VvvebProvisioningService $vvvebProvisioningService,
        VvvebHandoffService $vvvebHandoffService,
    ): void
    {
        $this->tenantDomainService = $tenantDomainService;
        $this->tenantDomainEntitlementService = $tenantDomainEntitlementService;
        $this->vvvebProvisioningService = $vvvebProvisioningService;
        $this->vvvebHandoffService = $vvvebHandoffService;
    }

    public function mount(): void
    {
        $this->tenantDomainService->syncEntitledDomains(Filament::getTenant());

        $this->form->fill([
            'custom_domain' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('custom_domain')
                    ->label(__('Custom Domain'))
                    ->placeholder('customdomain.com')
                    ->helperText(__('Add a domain you own. Verification and DNS checks will be handled in the next step of the integration.'))
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function addCustomDomain(): void
    {
        $data = $this->form->getState();

        if (blank($data['custom_domain'] ?? null)) {
            throw ValidationException::withMessages([
                'data.custom_domain' => __('Please enter a domain name.'),
            ]);
        }

        $tenant = Filament::getTenant();

        $this->tenantDomainService->addCustomDomain($tenant, $data['custom_domain']);

        $this->form->fill([
            'custom_domain' => null,
        ]);

        Notification::make()
            ->title(__('Custom domain added.'))
            ->body(__('The domain is now attached to your workspace and waiting for verification.'))
            ->success()
            ->send();
    }

    public function retryProvisioning(): void
    {
        $tenant = Filament::getTenant();

        try {
            $this->vvvebProvisioningService->provisionTenantSite($tenant->id, true);

            Notification::make()
                ->title(__('Site provisioning completed.'))
                ->body(__('The workspace was synced with the builder again.'))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title(__('Site provisioning failed.'))
                ->body(Str::limit(strip_tags($throwable->getMessage()), 220))
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return TenantDomain::query()
                    ->where('tenant_id', Filament::getTenant()->id)
                    ->orderByDesc('is_primary')
                    ->latest();
            })
            ->columns([
                TextColumn::make('full_domain')
                    ->label(__('Domain'))
                    ->getStateUsing(function (TenantDomain $record): string {
                        return $record->path ? $record->host.'/'.$record->path : $record->host;
                    })
                    ->searchable(['host', 'path']),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (TenantDomainType|string|null $state) => match ($state instanceof TenantDomainType ? $state : TenantDomainType::from($state)) {
                        TenantDomainType::PATH => __('Path'),
                        TenantDomainType::SUBDOMAIN => __('Subdomain'),
                        TenantDomainType::CUSTOM => __('Custom'),
                    }),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('is_primary')
                    ->label(__('Primary'))
                    ->boolean(),
                TextColumn::make('verified_at')
                    ->label(__('Verified'))
                    ->since()
                    ->placeholder(__('Not yet')),
            ])
            ->recordActions([
                Action::make('makePrimary')
                    ->label(__('Make Primary'))
                    ->visible(fn (TenantDomain $record) => ! $record->is_primary)
                    ->action(function (TenantDomain $record) {
                        $this->tenantDomainService->setPrimaryDomain(Filament::getTenant(), $record);

                        Notification::make()
                            ->title(__('Primary domain updated.'))
                            ->success()
                            ->send();
                    }),
                Action::make('remove')
                    ->label(__('Remove'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TenantDomain $record) => $record->type === TenantDomainType::CUSTOM)
                    ->action(function (TenantDomain $record) {
                        $this->tenantDomainService->removeCustomDomain(Filament::getTenant(), $record);

                        Notification::make()
                            ->title(__('Domain removed.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }

    public function getProvisioningStatus(): string
    {
        return (string) Filament::getTenant()->onboardingProfile?->site_provisioning_status?->value;
    }

    public function getBuilderUrl(): ?string
    {
        return Filament::getTenant()->sites()->where('provider', 'vvveb')->value('builder_url');
    }

    public function getBuilderHandoffUrl(): ?string
    {
        $tenant = Filament::getTenant();

        $siteId = $tenant->sites()->where('provider', 'vvveb')->value('external_site_id');
        if (! $siteId) {
            return null;
        }

                $siteBuilderUrl = (string) ($tenant->sites()->where('provider', 'vvveb')->value('builder_url') ?? '');
        $baseBuilderUrl = (string) config('services.vvveb.builder_url');

        if (str_starts_with($siteBuilderUrl, 'http://') || str_starts_with($siteBuilderUrl, 'https://')) {
            $builderUrl = $siteBuilderUrl;
        } elseif ($siteBuilderUrl !== '') {
            $sitePath = str_starts_with($siteBuilderUrl, '/') ? $siteBuilderUrl : '/'.$siteBuilderUrl;
            $builderUrl = rtrim($baseBuilderUrl, '/').$sitePath;
        } else {
            $builderUrl = $baseBuilderUrl;
        }
        $userId = (int) auth()->id();
        if (! $userId) {
            return null;
        }

        return $this->vvvebHandoffService->buildHandoffUrl(
            (string) $tenant->uuid,
            (string) $siteId,
            $userId,
            $builderUrl,
            'module=settings/site',
        );
    }

    public function getExternalSiteId(): ?string
    {
        return Filament::getTenant()->sites()->where('provider', 'vvveb')->value('external_site_id');
    }

    public function getProvisioningError(): ?string
    {
        $error = Filament::getTenant()->onboardingProfile?->site_provisioning_error;

        if (! $error) {
            return null;
        }

        return Str::limit(trim(strip_tags($error)), 400);
    }

    public function canRetryProvisioning(): bool
    {
        return in_array($this->getProvisioningStatus(), [
            TenantSiteProvisioningStatus::FAILED->value,
            TenantSiteProvisioningStatus::PENDING->value,
            TenantSiteProvisioningStatus::PROCESSING->value,
        ], true);
    }

    public function render(): View
    {
        $tenant = Filament::getTenant();
        $tenant = $tenant?->fresh(['onboardingProfile', 'sites', 'domains']);

        abort_if(! $tenant, 404);
        $primaryDomain = $this->tenantDomainService->getPrimaryDomain($tenant);
        $capabilities = $this->tenantDomainEntitlementService->getCapabilities($tenant);

        $customDomainLimit = (int) ($capabilities['max_custom_domains'] ?? 0);
        $customDomainUsed = (int) ($tenant->domains?->where('type', TenantDomainType::CUSTOM->value)->count() ?? 0);

        $subscriptionNotice = null;

        $hasActiveSubscription = $tenant->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if (! $hasActiveSubscription) {
            $latestSubscription = $tenant->subscriptions()
                ->whereIn('status', SubscriptionConstants::SUBSCRIPTION_STATUS_THAT_ARE_NOT_DEAD)
                ->latest()
                ->first();

            if ($latestSubscription?->status === SubscriptionStatus::PENDING->value) {
                $subscriptionNotice = __('Your subscription is pending activation. Plan features like subdomain/custom domains will be enabled after activation.');
            } elseif ($latestSubscription?->status === SubscriptionStatus::PENDING_USER_VERIFICATION->value) {
                $subscriptionNotice = __('Your subscription is waiting for user verification. Plan features will be enabled after verification completes.');
            } elseif ($latestSubscription?->status === SubscriptionStatus::PAST_DUE->value) {
                $subscriptionNotice = __('Your subscription is past due. Some plan features may be unavailable until payment is resolved.');
            }
        }

        return view('livewire.filament.dashboard.domains', [
            'tenant' => $tenant,
            'primaryDomain' => $primaryDomain,
            'provisioningStatus' => $this->getProvisioningStatus(),
            'provisioningError' => $this->getProvisioningError(),
            'builderUrl' => $this->getBuilderUrl(),
            'builderHandoffUrl' => $this->getBuilderHandoffUrl(),
            'externalSiteId' => $this->getExternalSiteId(),
            'capabilities' => $capabilities,
            'customDomainLimit' => $customDomainLimit,
            'customDomainUsed' => $customDomainUsed,
            'subscriptionNotice' => $subscriptionNotice,
            'canAddCustomDomain' => $this->tenantDomainService->canAddCustomDomain($tenant),
            'canRetryProvisioning' => $this->canRetryProvisioning(),
        ]);
    }
}
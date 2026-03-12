<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantDomainType;
use App\Constants\TenantSiteProvisioningStatus;
use App\Models\TenantDomain;
use App\Services\TenantDomainEntitlementService;
use App\Services\TenantDomainService;
use App\Services\VvvebProvisioningService;
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

    public ?array $data = [];

    public function boot(
        TenantDomainService $tenantDomainService,
        TenantDomainEntitlementService $tenantDomainEntitlementService,
        VvvebProvisioningService $vvvebProvisioningService,
    ): void
    {
        $this->tenantDomainService = $tenantDomainService;
        $this->tenantDomainEntitlementService = $tenantDomainEntitlementService;
        $this->vvvebProvisioningService = $vvvebProvisioningService;
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
        $tenant = Filament::getTenant()->loadMissing(['onboardingProfile', 'sites', 'domains']);
        $primaryDomain = $this->tenantDomainService->getPrimaryDomain($tenant);
        $capabilities = $this->tenantDomainEntitlementService->getCapabilities($tenant);

        return view('livewire.filament.dashboard.domains', [
            'tenant' => $tenant,
            'primaryDomain' => $primaryDomain,
            'provisioningStatus' => $this->getProvisioningStatus(),
            'provisioningError' => $this->getProvisioningError(),
            'builderUrl' => $this->getBuilderUrl(),
            'externalSiteId' => $this->getExternalSiteId(),
            'capabilities' => $capabilities,
            'canAddCustomDomain' => $this->tenantDomainService->canAddCustomDomain($tenant),
            'canRetryProvisioning' => $this->canRetryProvisioning(),
        ]);
    }
}
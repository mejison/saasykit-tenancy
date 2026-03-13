<x-layouts.focus>
    <x-slot name="left">
        <div class="flex flex-col py-2 md:p-10 gap-4 justify-center h-full items-center">
            <div class="card w-full md:max-w-xl bg-base-100 shadow-xl p-4 md:p-8">
                <x-heading.h2 class="text-2xl! md:text-3xl! font-semibold! mb-4">
                    {{ __('Set up your workspace') }}
                </x-heading.h2>

                <p class="text-sm opacity-80 mb-6">
                    {{ __('We need a few details to create your BeatKongs site and reserve your public URL.') }}
                </p>

                <form method="POST" action="{{ route('tenant-onboarding.store') }}">
                    @csrf

                    <x-input.field label="{{ __('Brand Name') }}" type="text" name="brand_name"
                                   value="{{ old('brand_name', $profile->brand_name ?? $tenant->name) }}" required autofocus="true"
                                   max-width="w-full"/>

                    @error('brand_name')
                    <span class="text-xs text-red-500" role="alert">
                        {{ $message }}
                    </span>
                    @enderror

                    <x-input.field label="{{ __('Username Slug') }}" type="text" name="username_slug"
                                   value="{{ old('username_slug', $profile->username_slug) }}" required
                                   max-width="w-full"/>

                    <p class="text-xs opacity-70 -mt-4 mb-2">
                        {{ __('This will be used for URLs like :url', ['url' => ($tenant->domains->first()?->host ?? (parse_url(config('app.url'), PHP_URL_HOST) ?: 'mysite.com')).'/your-name']) }}
                    </p>

                    @error('username_slug')
                    <span class="text-xs text-red-500" role="alert">
                        {{ $message }}
                    </span>
                    @enderror

                    <x-button-link.primary class="inline-block w-full! mt-4 mb-2" elementType="button" type="submit">
                        {{ __('Complete Setup') }}
                    </x-button-link.primary>
                </form>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="py-4 px-4 md:px-12 md:pt-36 h-full">
            <x-heading.h1 class="text-3xl! md:text-4xl! font-semibold!">
                {{ __('Launch your producer website.') }}
            </x-heading.h1>
            <p class="mt-4">
                {{ __('Once you finish onboarding, we will create your builder site automatically and attach it to your workspace.') }}
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
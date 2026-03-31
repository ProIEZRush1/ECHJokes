<x-filament-panels::page>
    <form wire:submit="launchCall">
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                    <input type="text" wire:model="phone_number" placeholder="+525512345678"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Without +52 prefix it will be added automatically</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Voice</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="relative flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                            {{ $voice === 'ash' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="voice" value="ash" class="sr-only" />
                            <span class="text-2xl">&#x1F468;</span>
                            <div>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Male</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Ash - warm voice</span>
                            </div>
                        </label>
                        <label class="relative flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                            {{ $voice === 'coral' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="voice" value="coral" class="sr-only" />
                            <span class="text-2xl">&#x1F469;</span>
                            <div>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Female</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Coral - natural voice</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Character</label>
                <input type="text" wire:model="character" placeholder="administrador del condominio"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prank Scenario</label>
                <textarea wire:model="scenario" rows="3" placeholder="La lavadora hace mucho ruido y los vecinos se quejan..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
            </div>

            <button type="submit"
                class="w-full py-3 px-4 rounded-lg bg-green-600 text-white font-bold text-lg hover:bg-green-700 transition-colors shadow-md">
                Launch Call
            </button>

            @if($result)
                <div class="p-4 rounded-lg border {{ str_contains($result, 'Error') ? 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300' : 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300' }}">
                    {{ $result }}
                </div>
            @endif
        </div>
    </form>
</x-filament-panels::page>

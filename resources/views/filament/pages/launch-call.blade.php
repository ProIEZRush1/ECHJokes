<x-filament-panels::page>
    <form wire:submit="launchCall">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                <input type="text" wire:model="phone_number" placeholder="+525512345678"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prank Scenario</label>
                <textarea wire:model="scenario" rows="4" placeholder="La lavadora hace mucho ruido y los vecinos se quejan..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Character</label>
                <input type="text" wire:model="character" placeholder="administrador del condominio"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>

            <button type="submit"
                class="w-full py-3 px-4 rounded-lg bg-green-600 text-white font-bold hover:bg-green-700 transition-colors">
                📞 Launch Call
            </button>

            @if($result)
                <div class="p-4 rounded-lg {{ str_contains($result, 'Error') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                    {{ $result }}
                </div>
            @endif
        </div>
    </form>
</x-filament-panels::page>

<x-filament::page>
    <div class="p-2 mb-6 border rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
        <h2 class="text-lg font-medium">Crisp Canlı Destek Entegrasyonu</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Bu sayfadan Crisp canlı destek servisini sitenize entegre edebilirsiniz.
            <a href="https://crisp.chat/" target="_blank" class="text-primary-500 hover:underline">
                Crisp hesabınız yoksa buradan oluşturabilirsiniz.
            </a>
        </p>
    </div>

    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-4">
            <x-filament::button type="submit" color="primary">
                Ayarları Kaydet
            </x-filament::button>
        </div>
    </form>
    
    @if($this->data['apiKey'] ?? false)
    <div class="mt-8 p-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-900">
        <h3 class="font-medium text-green-800 dark:text-green-400">Kurulum Tamamlandı!</h3>
        <p class="mt-2 text-sm text-green-700 dark:text-green-300">
            Crisp entegrasyonu sağlandı. Sitenizde Crisp widget'ı görünecek.
        </p>
    </div>
    @endif
</x-filament::page> 
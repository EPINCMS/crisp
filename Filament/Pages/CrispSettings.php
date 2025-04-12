<?php

namespace Modules\Crisp\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Models\Settings;
use Illuminate\Support\Facades\Route;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class CrispSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static string $view = 'crisp::filament.pages.crisp-settings';
    
    protected static ?string $navigationLabel = 'Crisp Canlı Destek';
    
    protected static ?int $navigationSort = 200;
    
    // Route oluşturmada kullanılacak slug
    protected static ?string $slug = 'crisp-settings';
    
    protected static ?string $navigationGroup = 'Entegrasyonlar';
    
    // Form verileri
    public $apiKey = '';
    public $enabled = false;
    
    // Form verilerini taşıyacak data property'si ekleyelim
    public $data = [];
    
    public function mount()
    {
        // Mevcut ayarları yükle
        $settings = Settings::on('tenant')->where('key', 'crisp')->first();
        
        if ($settings) {
            $data = $settings->value;
            
            // Form verilerini data property'sine yükleyelim
            $this->data = [
                'apiKey' => $data['api_key'] ?? '',
                'enabled' => $data['enabled'] ?? false,
            ];
            
            // Debug için
            \Illuminate\Support\Facades\Log::info('Crisp ayarları yüklendi: ', $this->data);
        } else {
            // Default değerler
            $this->data = [
                'apiKey' => '',
                'enabled' => false,
            ];
        }
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('Entegrasyonlar');
    }
    
    public static function getNavigationLabel(): string
    {
        return __('Crisp Canlı Destek');
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Crisp Entegrasyonu')
                    ->description('Crisp canlı destek servisini sitenize entegre edin.')
                    ->schema([
                        TextInput::make('apiKey')
                            ->label('Website ID')
                            ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                            ->helperText('Crisp panelinizden Website ID bilgisini bulabilirsiniz.')
                            ->required(),
                        
                        Toggle::make('enabled')
                            ->label('Aktif')
                            ->helperText('Crisp entegrasyonunu site genelinde aktif etmek için kullanın.')
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }
    
    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Kaydet')
                ->action(function () {
                    $this->save();
                })
        ];
    }
    
    public function save()
    {
        $validated = $this->validate([
            'data.apiKey' => 'required|string',
            'data.enabled' => 'boolean',
        ]);
        
        try {
            // Debug bilgisi
            Log::info('Crisp ayarları kaydediliyor. API Key: ' . substr($this->data['apiKey'], 0, 5) . 
                      '... Enabled: ' . ($this->data['enabled'] ? 'Yes' : 'No'));
            
            // Settings modelinde value field'i array olarak cast edildiği için direkt array kullan
            $settings = Settings::on('tenant')->updateOrCreate(
                ['key' => 'crisp'],
                [
                    'value' => [
                        'api_key' => $this->data['apiKey'],
                        'enabled' => $this->data['enabled'],
                    ],
                    'label' => 'Crisp Chat'
                ]
            );

            // Önbelleği temizle (hook'lar için)
            \Illuminate\Support\Facades\Cache::forget('crisp.api_key_' . config('tenant.domain'));
            
            // Hook mekanizmasını yeniden yükle
            $this->refreshModuleHooks();

            Notification::make()
                ->title('Ayarlar başarıyla kaydedildi')
                ->success()
                ->send();
                
            Log::info('Crisp ayarları kaydedildi. Ayar ID: ' . $settings->key);
        } catch (\Exception $e) {
            Log::error('Crisp ayarları kaydedilirken hata: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            Notification::make()
                ->title('Hata: Ayarlar kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
    
    /**
     * Hook'ları yeniden yükle
     */
    protected function refreshModuleHooks()
    {
        try {
            // Sadece Crisp modülüne ait önbellek anahtarlarını temizle
            \Illuminate\Support\Facades\Cache::forget('crisp.api_key_' . config('tenant.domain'));
            
            // Crisp servis providerını yeniden register et
            if (class_exists('\Modules\Crisp\Providers\CrispServiceProvider')) {
                // Modül hook'larını yeniden register et
                $provider = app('\Modules\Crisp\Providers\CrispServiceProvider');
                if (method_exists($provider, 'registerHooksPublic')) {
                    $provider->registerHooksPublic();
                    Log::info('Crisp hooks re-registered successfully via public method');
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not refresh module hooks: ' . $e->getMessage());
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [];
    }
} 
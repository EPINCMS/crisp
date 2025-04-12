<?php

namespace Modules\Crisp\Providers;

use App\Core\Modules\Abstracts\ModuleServiceProvider;
use App\Core\Modules\Services\Facades\Hook;
use App\Models\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Modules\Crisp\Filament\Pages\CrispSettings;

class CrispServiceProvider extends ModuleServiceProvider
{
    /**
     * Get module name
     * 
     * @return string
     */
    protected function getModuleName(): string
    {
        return 'Crisp';
    }
    
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        parent::register();
        
        // Register module specific services
        $this->registerHooks();
    }
    
    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        parent::boot();
        
        // Make sure views are properly registered for namespace
        $this->registerViews();
        
        // Register Filament pages 
        $this->registerFilamentPages();
    }
    
    /**
     * Register module views with proper namespace
     */
    protected function registerViews(): void
    {
        $viewsPath = $this->modulePath . '/resources/views';
        
        if (File::isDirectory($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'crisp');
        }
    }
    
    /**
     * Register module hooks
     */
    protected function registerHooks(): void
    {
        Hook::register('head', function() {
            try {
                // Settings'den Crisp ayarlarını al
                $settings = Settings::on('tenant')->where('key', 'crisp')->first();
                
                if (!$settings) {
                    \Illuminate\Support\Facades\Log::warning('Crisp settings not found in database');
                    return null;
                }
                
                // JSON decode hatası olmaması için değeri kontrol et
                if (is_string($settings->value)) {
                    $settingsData = json_decode($settings->value, true);
                } else {
                    $settingsData = $settings->value;
                }
                
                $apiKey = $settingsData['api_key'] ?? null;
                $enabled = $settingsData['enabled'] ?? false;
                
                // Debug bilgisi
                \Illuminate\Support\Facades\Log::info('Crisp hook triggered. API Key: ' . substr($apiKey ?? '', 0, 5) . '... Enabled: ' . ($enabled ? 'Yes' : 'No'));
                
                // Eğer API key yoksa veya etkin değilse null döndür
                if (!$apiKey || !$enabled) {
                    return null;
                }
                
                // Crisp entegrasyon kodunu döndür
                return view('crisp::hooks.head', ['apiKey' => $apiKey])->render();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Crisp hook error: ' . $e->getMessage());
                return null;
            }
        }, 10);
    }
    
    /**
     * Register module hooks - public version
     * 
     * @return void
     */
    public function registerHooksPublic(): void
    {
        $this->registerHooks();
    }
    
    /**
     * Register Filament pages
     */
    protected function registerFilamentPages(): void
    {
        if (class_exists('\Filament\Facades\Filament')) {
            try {
                // Rota var mı diye kontrol edelim (CrispSettings sayfası için)
                if (\Illuminate\Support\Facades\Route::has('filament.admin.pages.crisp-settings')) {
                    \Illuminate\Support\Facades\Log::info("Crisp settings route already registered - skipping registration");
                    return;
                }

                // Bütün yöntemleri deneyelim
                try {
                    // Filament 3.x için yeni kayıt yöntemi
                    if (method_exists(\Filament\Facades\Filament::class, 'registerPages')) {
                        \Filament\Facades\Filament::registerPages([
                            CrispSettings::class,
                        ]);
                        
                        \Illuminate\Support\Facades\Log::info("Crisp Filament pages registered with registerPages method");
                    } else {
                        // Panel'e erişmeye çalış
                        try {
                            $adminPanel = \Filament\Facades\Filament::getPanel('admin');
                            
                            if ($adminPanel) {
                                // Sayfayı panele ekleyelim
                                $adminPanel->pages([
                                    CrispSettings::class,
                                ]);
                                
                                \Illuminate\Support\Facades\Log::info("Crisp Filament pages registered via panel->pages method");
                            } else {
                                \Illuminate\Support\Facades\Log::warning("Admin panel not found for Crisp module");
                                
                                // Alternatif yöntem
                                \Filament\Facades\Filament::registerPageClasses([
                                    'admin' => [
                                        'crisp-settings' => CrispSettings::class,
                                    ],
                                ]);
                                
                                \Illuminate\Support\Facades\Log::info("Crisp Filament pages registered via registerPageClasses fallback");
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error getting admin panel: ' . $e->getMessage());
                            
                            // Son çare olarak global register kullan
                            \Filament\Facades\Filament::register([
                                CrispSettings::class,
                            ]);
                            
                            \Illuminate\Support\Facades\Log::info("Crisp Filament pages registered via global register method as last resort");
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Error with nested registration methods: " . $e->getMessage());
                }
            } catch (\Exception $e) {
                // Log the error if something goes wrong
                \Illuminate\Support\Facades\Log::error('Could not register Crisp Filament pages: ' . $e->getMessage());
            }
        }
    }
} 
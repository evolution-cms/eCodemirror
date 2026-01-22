<?php namespace EvolutionCMS\eCodeMirror;

use EvolutionCMS\ServiceProvider;

class eCodeMirrorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/eCodeMirrorCheck.php', 'cms.settings');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'eCodeMirror');

        if ($this->app->runningInConsole()) {
            $this->publishResources();
        }
    }

    public function register(): void
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    protected function publishResources(): void
    {
        $this->publishes([
            dirname(__DIR__) . '/config/eCodeMirrorSettings.php' => config_path('cms/settings/eCodeMirror.php', true),
        ], 'ecodemirror-config');

        $this->publishes([
            dirname(__DIR__) . '/config/which_editor.php' => config_path('cms/settings/which_editor.php', true),
        ], 'ecodemirror-config');

        $this->publishes([
            dirname(__DIR__) . '/public/dist' => public_path('assets/plugins/eCodeMirror/dist'),
        ], 'ecodemirror-assets');
    }
}

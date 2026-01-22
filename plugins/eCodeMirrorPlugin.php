<?php

if (!function_exists('eCodeMirror_log')) {
    function eCodeMirror_log(string $message, int $type = 2): void
    {
        if (function_exists('evo')) {
            evo()->logEvent(0, $type, $message, 'eCodeMirror');
        }
    }
}

if (!function_exists('eCodeMirror_getManagerThemeMode')) {
    function eCodeMirror_getManagerThemeMode(): ?string
    {
        $themeModes = ['', 'lightness', 'light', 'dark', 'darkness'];

        if (isset($_COOKIE['MODX_themeMode'])) {
            $index = (int)$_COOKIE['MODX_themeMode'];
            if (!empty($themeModes[$index])) {
                return $themeModes[$index];
            }
        }

        $configMode = (int)evo()->getConfig('manager_theme_mode');
        if (!empty($themeModes[$configMode])) {
            return $themeModes[$configMode];
        }

        return null;
    }
}

if (!function_exists('eCodeMirror_normalizeThemeMode')) {
    function eCodeMirror_normalizeThemeMode(?string $mode): string
    {
        $mode = $mode ? strtolower(trim($mode)) : '';
        if ($mode === 'lightness' || $mode === 'light') {
            return 'light';
        }
        if ($mode === 'darkness' || $mode === 'dark') {
            return 'dark';
        }
        return '';
    }
}

if (!function_exists('eCodeMirror_isValidSelector')) {
    function eCodeMirror_isValidSelector(string $selector): bool
    {
        return trim($selector) !== '';
    }
}

if (!function_exists('eCodeMirror_normalizeSelector')) {
    function eCodeMirror_normalizeSelector(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return $selector;
        }

        $first = $selector[0] ?? '';
        if ($first === '#' || $first === '.' || $first === '[') {
            return $selector;
        }

        if (strpos($selector, ' ') !== false || strpos($selector, ':') !== false) {
            return $selector;
        }

        return 'textarea[name="' . $selector . '"]';
    }
}

if (!function_exists('eCodeMirror_contentTypeLanguage')) {
    function eCodeMirror_contentTypeLanguage(?string $contentType): ?string
    {
        $contentType = $contentType ? strtolower(trim($contentType)) : '';
        switch ($contentType) {
            case 'text/css':
                return 'css';
            case 'text/javascript':
                return 'javascript';
            case 'application/json':
                return 'json';
            case 'application/x-httpd-php':
                return 'php';
            case 'text/html':
                return 'html';
        }

        return null;
    }
}

if (!function_exists('eCodeMirror_buildEditorConfig')) {
    function eCodeMirror_buildEditorConfig(
        string $selector,
        string $context,
        array $fieldOptions,
        array $settings,
        array $systemOverrides,
        ?string $contentType
    ): ?array {
        $profiles = $settings['profiles'] ?? [];
        $themes = $settings['themes'] ?? [];
        $contexts = $settings['contexts'] ?? [];
        $protected = $settings['protected_keys'] ?? [];

        $defaultProfile = $settings['default_profile'] ?? 'html';
        $defaultThemeMode = $settings['default_theme_mode'] ?? 'auto';
        $defaultTheme = $settings['default_theme'] ?? 'evo-light';

        $profile = $fieldOptions['profile'] ?? ($systemOverrides['profile'] ?? ($contexts[$context] ?? $defaultProfile));
        if (!isset($profiles[$profile])) {
            eCodeMirror_log('Unknown profile: ' . $profile . '. Using default.');
            $profile = $defaultProfile;
        }

        $language = $fieldOptions['language'] ?? null;
        if ($language === null) {
            $language = eCodeMirror_contentTypeLanguage($contentType) ?: ($profiles[$profile]['language'] ?? 'plain');
        }

        $themeMode = $fieldOptions['theme_mode'] ?? ($systemOverrides['theme_mode'] ?? $defaultThemeMode);
        $themeMode = $themeMode ?: $defaultThemeMode;
        if ($themeMode === 'auto') {
            $themeMode = eCodeMirror_normalizeThemeMode(eCodeMirror_getManagerThemeMode());
        }
        if ($themeMode === '') {
            $themeMode = 'light';
        }

        $theme = $fieldOptions['theme'] ?? ($systemOverrides['theme'] ?? ($themes[$themeMode]['theme'] ?? $defaultTheme));
        if (!is_string($theme) || $theme === '') {
            $theme = $defaultTheme;
        }

        $profileOptions = $profiles[$profile]['options'] ?? [];
        $editorOptions = $settings['editor'] ?? [];
        $options = $profileOptions;

        if (isset($editorOptions['line_wrapping'])) {
            $options['lineWrapping'] = (bool)$editorOptions['line_wrapping'];
        }
        if (isset($editorOptions['indent_with_tabs'])) {
            $options['indentWithTabs'] = (bool)$editorOptions['indent_with_tabs'];
        }
        if (isset($editorOptions['font_size'])) {
            $options['fontSize'] = (float)$editorOptions['font_size'];
        }
        if (isset($editorOptions['line_height'])) {
            $options['lineHeight'] = (float)$editorOptions['line_height'];
        }

        $options = array_replace_recursive($options, $systemOverrides['options'] ?? []);

        if ($fieldOptions !== []) {
            foreach ($protected as $key) {
                unset($fieldOptions[$key]);
            }
            unset($fieldOptions['profile'], $fieldOptions['theme_mode'], $fieldOptions['theme'], $fieldOptions['language']);
            $options = array_replace_recursive($options, $fieldOptions);
        }

        $extensions = $profiles[$profile]['extensions'] ?? [];
        $globalExtensions = $settings['extensions'] ?? [];
        $extensions = array_values(array_filter($extensions, function ($ext) use ($globalExtensions, $systemOverrides) {
            if (!is_string($ext) || $ext === '') {
                return false;
            }
            $enabled = $globalExtensions[$ext]['enabled'] ?? true;
            if ($ext === 'emmet' && array_key_exists('emmet', $systemOverrides)) {
                $enabled = (bool)$systemOverrides['emmet'];
            }
            if ($ext === 'search' && array_key_exists('search', $systemOverrides)) {
                $enabled = (bool)$systemOverrides['search'];
            }
            return $enabled;
        }));

        $id = md5($context . '|' . $selector);

        return [
            'id' => $id,
            'selector' => $selector,
            'context' => $context,
            'profile' => $profile,
            'language' => $language,
            'theme_mode' => $themeMode,
            'theme' => $theme,
            'options' => $options,
            'extensions' => $extensions,
            'history' => $editorOptions['history'] ?? [],
            'state' => $editorOptions['state'] ?? [],
            'gutters' => $editorOptions['gutters'] ?? [],
            'keymap' => $editorOptions['keymap'] ?? [],
        ];
    }
}

if (!function_exists('eCodeMirror_getSystemOverrides')) {
    function eCodeMirror_getSystemOverrides(): array
    {
        $overrides = [
            'profile' => evo()->getConfig('ecm_profile') ?: null,
            'theme_mode' => evo()->getConfig('ecm_theme_mode') ?: null,
            'theme' => evo()->getConfig('ecm_theme') ?: null,
            'options' => [],
        ];

        if (evo()->getConfig('ecm_line_wrapping') !== null && evo()->getConfig('ecm_line_wrapping') !== '') {
            $overrides['options']['lineWrapping'] = (bool)evo()->getConfig('ecm_line_wrapping');
        }
        if (evo()->getConfig('ecm_font_size') !== null && evo()->getConfig('ecm_font_size') !== '') {
            $overrides['options']['fontSize'] = (float)evo()->getConfig('ecm_font_size');
        }
        if (evo()->getConfig('ecm_line_height') !== null && evo()->getConfig('ecm_line_height') !== '') {
            $overrides['options']['lineHeight'] = (float)evo()->getConfig('ecm_line_height');
        }
        if (evo()->getConfig('ecm_emmet') !== null && evo()->getConfig('ecm_emmet') !== '') {
            $overrides['emmet'] = (bool)evo()->getConfig('ecm_emmet');
        }
        if (evo()->getConfig('ecm_search') !== null && evo()->getConfig('ecm_search') !== '') {
            $overrides['search'] = (bool)evo()->getConfig('ecm_search');
        }

        return $overrides;
    }
}

if (!function_exists('eCodeMirror_renderEditors')) {
    function eCodeMirror_renderEditors(array $editors, array $settings): string
    {
        if ($editors === []) {
            return '';
        }

        $baseDir = MODX_BASE_PATH . 'assets/plugins/eCodeMirror/dist';
        $baseUrl = MODX_SITE_URL . 'assets/plugins/eCodeMirror/dist';
        $manifestPath = $baseDir . '/manifest.json';

        $jsFile = 'eCodeMirror.js';
        $cssFile = 'eCodeMirror.css';
        $useManifest = false;

        if (is_file($manifestPath)) {
            $manifest = json_decode((string)@file_get_contents($manifestPath), true);
            if (is_array($manifest)) {
                foreach ($manifest as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (!empty($entry['isEntry']) && !empty($entry['file'])) {
                        $jsFile = $entry['file'];
                        if (isset($entry['css'][0])) {
                            $cssFile = $entry['css'][0];
                        }
                        $useManifest = true;
                        break;
                    }
                }
            }
        }

        $jsPath = $baseDir . '/' . $jsFile;
        $cssPath = $baseDir . '/' . $cssFile;

        if (!is_file($jsPath) || !is_file($cssPath)) {
            $fallbackJs = $baseDir . '/eCodeMirror.js';
            $fallbackCss = $baseDir . '/eCodeMirror.css';
            if (is_file($fallbackJs) && is_file($fallbackCss)) {
                $jsFile = 'eCodeMirror.js';
                $cssFile = 'eCodeMirror.css';
                $jsPath = $fallbackJs;
                $cssPath = $fallbackCss;
                $useManifest = false;
            }
        }

        if (!is_file($jsPath) || !is_file($cssPath)) {
            eCodeMirror_log('Missing eCodeMirror assets. Run vendor:publish for eCodeMirror.');
            return '<script>console.warn("eCodeMirror assets are not published.");</script>' .
                '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.querySelector("#main")||document.body;if(el){var d=document.createElement("div");d.className="alert alert-danger";d.textContent="eCodeMirror assets are not published. Run vendor:publish.";el.prepend(d);}});</script>';
        }

        $version = '';
        if (!$useManifest) {
            $mtime = @filemtime($jsPath);
            if (is_int($mtime)) {
                $version = '?v=' . $mtime;
            } else {
                $configVersion = $settings['version'] ?? '';
                if (is_string($configVersion) && $configVersion !== '') {
                    $version = '?v=' . $configVersion;
                }
            }
        }

        $payload = json_encode($editors, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            eCodeMirror_log('Failed to encode editors payload.');
            return '';
        }

        $output = [];
        if (!defined('ECODEMIRROR_ASSETS')) {
            define('ECODEMIRROR_ASSETS', true);
            $output[] = '<link rel="stylesheet" href="' . $baseUrl . '/' . $cssFile . $version . '" />';
            $output[] = '<script src="' . $baseUrl . '/' . $jsFile . $version . '"></script>';
        }

        $output[] = '<script>window.eCodeMirrorQueue=window.eCodeMirrorQueue||[];window.eCodeMirrorQueue.push(' . $payload . ');</script>';
        $output[] = '<script>if(window.eCodeMirror&&typeof window.eCodeMirror.init==="function"){window.eCodeMirror.init(' . $payload . ');}</script>';

        return implode("\n", $output);
    }
}

Event::listen('evolution.OnRichTextEditorRegister', function () {
    return 'eCodeMirror';
});

Event::listen('evolution.OnInterfaceSettingsRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $profiles = $settings['profiles'] ?? [];
    $themes = $settings['themes'] ?? [];

    $profileOptions = [];
    foreach ($profiles as $key => $profile) {
        $label = is_array($profile) && isset($profile['label']) ? $profile['label'] : $key;
        $profileOptions[$key] = $label;
    }

    $themeOptions = [];
    foreach ($themes as $key => $theme) {
        if (is_array($theme) && isset($theme['theme'])) {
            $themeOptions[$theme['theme']] = $theme['label'] ?? $theme['theme'];
        } else {
            $themeOptions[$key] = is_string($theme) ? $theme : $key;
        }
    }
    $themeModes = ['auto', 'light', 'dark'];

    $currentProfile = evo()->getConfig('ecm_profile') ?: ($settings['default_profile'] ?? 'html');
    $currentThemeMode = evo()->getConfig('ecm_theme_mode') ?: ($settings['default_theme_mode'] ?? 'auto');
    $currentTheme = evo()->getConfig('ecm_theme') ?: ($settings['default_theme'] ?? 'evo-light');

    $currentFontSize = evo()->getConfig('ecm_font_size') ?: ($settings['editor']['font_size'] ?? 14);
    $currentLineHeight = evo()->getConfig('ecm_line_height') ?: ($settings['editor']['line_height'] ?? 1.3);
    $currentLineWrapping = evo()->getConfig('ecm_line_wrapping');
    if ($currentLineWrapping === null || $currentLineWrapping === '') {
        $currentLineWrapping = $settings['editor']['line_wrapping'] ?? true;
    } else {
        $currentLineWrapping = (bool)$currentLineWrapping;
    }

    $currentEmmet = evo()->getConfig('ecm_emmet');
    if ($currentEmmet === null || $currentEmmet === '') {
        $currentEmmet = $settings['extensions']['emmet']['enabled'] ?? false;
    } else {
        $currentEmmet = (bool)$currentEmmet;
    }

    $currentSearch = evo()->getConfig('ecm_search');
    if ($currentSearch === null || $currentSearch === '') {
        $currentSearch = $settings['extensions']['search']['enabled'] ?? true;
    } else {
        $currentSearch = (bool)$currentSearch;
    }

    return \View::make('eCodeMirror::settings', [
        'profiles' => $profileOptions,
        'themes' => $themeOptions,
        'themeModes' => $themeModes,
        'currentProfile' => $currentProfile,
        'currentThemeMode' => $currentThemeMode,
        'currentTheme' => $currentTheme,
        'currentFontSize' => $currentFontSize,
        'currentLineHeight' => $currentLineHeight,
        'currentLineWrapping' => $currentLineWrapping,
        'currentEmmet' => $currentEmmet,
        'currentSearch' => $currentSearch,
    ])->toHtml();
});

Event::listen('evolution.OnRichTextEditorInit', function ($params) {
    if (!isset($params['editor']) || $params['editor'] !== 'eCodeMirror') {
        return '';
    }

    $controller = $params['controller'] ?? null;
    $contextOverride = null;
    if ($controller instanceof \EvolutionCMS\Controllers\Chunk) {
        $contextOverride = 'chunk';
    }

    $elements = $params['elements'] ?? [];
    if (!is_array($elements) || $elements === []) {
        return '';
    }

    $settings = config('cms.settings.eCodeMirror', []);
    $optionsByField = $params['options'] ?? [];

    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editors = [];
    foreach ($elements as $element) {
        $selector = is_string($element) ? $element : '';
        if (!eCodeMirror_isValidSelector($selector)) {
            eCodeMirror_log('Invalid editor selector: ' . $selector);
            continue;
        }

        $selector = eCodeMirror_normalizeSelector($selector);

        $fieldOptions = $optionsByField[$element] ?? [];
        if (!is_array($fieldOptions)) {
            $fieldOptions = [];
        }

        $context = $contextOverride;
        if ($context === null) {
            if ($element === 'ta') {
                $context = 'resource';
            } elseif (strpos($element, 'tv') === 0) {
                $context = 'tv';
            } else {
                $context = 'generic';
            }
        }

        $editor = eCodeMirror_buildEditorConfig(
            $selector,
            $context,
            $fieldOptions,
            $settings,
            $systemOverrides,
            $params['contentType'] ?? null
        );

        if ($editor) {
            $editors[] = $editor;
        }
    }

    return eCodeMirror_renderEditors($editors, $settings);
});

Event::listen('evolution.OnTVFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editor = eCodeMirror_buildEditorConfig(
        eCodeMirror_normalizeSelector('properties'),
        'tv_definition',
        [],
        $settings,
        $systemOverrides,
        'application/json'
    );

    return $editor ? eCodeMirror_renderEditors([$editor], $settings) : '';
});

Event::listen('evolution.OnDocFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    if ((string)evo()->getConfig('which_editor') !== 'eCodeMirror') {
        return '';
    }

    $content = $GLOBALS['content'] ?? [];
    if (is_array($content) && ($content['type'] ?? '') === 'reference') {
        return '';
    }

    $selector = eCodeMirror_normalizeSelector('ta');
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editor = eCodeMirror_buildEditorConfig(
        $selector,
        'resource',
        [],
        $settings,
        $systemOverrides,
        is_array($content) ? ($content['contentType'] ?? null) : null
    );

    return $editor ? eCodeMirror_renderEditors([$editor], $settings) : '';
});

Event::listen('evolution.OnTempFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $selector = eCodeMirror_normalizeSelector('post');
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editor = eCodeMirror_buildEditorConfig($selector, 'template', [], $settings, $systemOverrides, null);
    return $editor ? eCodeMirror_renderEditors([$editor], $settings) : '';
});

Event::listen('evolution.OnChunkFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $selector = eCodeMirror_normalizeSelector('post');
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editor = eCodeMirror_buildEditorConfig($selector, 'chunk', [], $settings, $systemOverrides, null);
    return $editor ? eCodeMirror_renderEditors([$editor], $settings) : '';
});

Event::listen('evolution.OnSnipFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editors = [];
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('post'), 'snippet', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('properties'), 'snippet', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors = array_values(array_filter($editors));

    return eCodeMirror_renderEditors($editors, $settings);
});

Event::listen('evolution.OnPluginFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editors = [];
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('post'), 'plugin', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('properties'), 'plugin', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors = array_values(array_filter($editors));

    return eCodeMirror_renderEditors($editors, $settings);
});

Event::listen('evolution.OnModFormRender', function () {
    $settings = config('cms.settings.eCodeMirror', []);
    $systemOverrides = eCodeMirror_getSystemOverrides();

    $editors = [];
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('post'), 'module', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors[] = eCodeMirror_buildEditorConfig(eCodeMirror_normalizeSelector('properties'), 'module', [], $settings, $systemOverrides, 'application/x-httpd-php');
    $editors = array_values(array_filter($editors));

    return eCodeMirror_renderEditors($editors, $settings);
});

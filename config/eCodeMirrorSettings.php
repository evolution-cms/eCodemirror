<?php return [
    'version' => '0.1.0',
    'default_profile' => 'html',
    'default_theme_mode' => 'auto',
    'default_theme' => 'evo-light',
    'themes' => [
        'light' => ['label' => 'Light', 'theme' => 'evo-light'],
        'dark' => ['label' => 'Dark', 'theme' => 'evo-dark'],
    ],
    'profiles' => [
        'html' => [
            'label' => 'HTML',
            'language' => 'html',
            'extensions' => ['basicSetup', 'modxOverlay', 'fold', 'search', 'emmet'],
            'options' => [
                'lineWrapping' => true,
                'indentUnit' => 4,
                'tabSize' => 4,
                'matchBrackets' => true,
                'activeLine' => true,
            ],
        ],
        'css' => [
            'label' => 'CSS',
            'language' => 'css',
            'extensions' => ['basicSetup', 'fold', 'search', 'emmet'],
            'options' => ['lineWrapping' => true],
        ],
        'js' => [
            'label' => 'JavaScript',
            'language' => 'javascript',
            'extensions' => ['basicSetup', 'fold', 'search'],
        ],
        'php' => [
            'label' => 'PHP',
            'language' => 'php',
            'extensions' => ['basicSetup', 'modxOverlay', 'fold', 'search'],
        ],
        'json' => [
            'label' => 'JSON',
            'language' => 'json',
            'extensions' => ['basicSetup', 'lint', 'search'],
        ],
        'sql' => [
            'label' => 'SQL',
            'language' => 'sql',
            'extensions' => ['basicSetup', 'search'],
        ],
        'plain' => [
            'label' => 'Plain Text',
            'language' => 'plain',
            'extensions' => ['basicSetup'],
        ],
    ],
    'contexts' => [
        'resource' => 'html',
        'template' => 'html',
        'chunk' => 'html',
        'snippet' => 'php',
        'plugin' => 'php',
        'module' => 'php',
        'tv' => 'html',
    ],
    'editor' => [
        'font_size' => 14,
        'line_height' => 1.3,
        'line_wrapping' => true,
        'indent_with_tabs' => true,
        'history' => [
            'persist' => false,
            'max_items' => 200,
            'max_kb' => 256,
            'debounce_ms' => 1250,
        ],
        'state' => [
            'persist_fullscreen' => true,
            'persist_cursor' => true,
            'persist_scroll' => true,
            'persist_snapshot' => false,
        ],
        'gutters' => [
            'markers' => true,
            'fold' => true,
        ],
        'keymap' => [
            'save' => 'Mod-s',
            'save_continue' => 'Mod-e',
            'save_new' => 'Mod-b',
            'save_quit' => 'Mod-q',
        ],
    ],
    'extensions' => [
        'search' => ['enabled' => true],
        'emmet' => ['enabled' => false],
        'lint' => ['enabled' => false],
    ],
    'protected_keys' => [
        'selector', 'target', 'language', 'extensions', 'theme', 'theme_mode',
        'history', 'keymap', 'gutters',
    ],
];

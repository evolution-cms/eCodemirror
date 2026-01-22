# eCodeMirror (CodeMirror 6) for Evolution CMS

Modern CodeMirror 6 editor package for Evolution CMS 3.5.x. This package replaces the legacy CM5 plugin with a Composer‑installed, publishable, and extensible CM6 implementation.

## Requirements
- PHP ^8.3
- Evolution CMS ^3.5.2
- Composer 2.2+

## Install
```bash
composer require evolution-cms/ecodemirror "*"
```

Register editor (optional auto‑set):
```bash
php artisan vendor:publish --tag=ecodemirror-config
```

Publish assets:
```bash
php artisan vendor:publish --tag=ecodemirror-assets
```

## What gets published
- Config (single source of truth):
  - `core/custom/config/cms/settings/eCodeMirror.php`
- Assets:
  - `public/assets/plugins/eCodeMirror/dist/eCodeMirror.js`
  - `public/assets/plugins/eCodeMirror/dist/eCodeMirror.css`

## System Settings (Overrides)
These are overrides only. The canonical config is `core/custom/config/cms/settings/eCodeMirror.php`.
- `ecm_profile`
- `ecm_theme_mode` (auto/light/dark)
- `ecm_theme`
- `ecm_font_size`
- `ecm_line_height`
- `ecm_line_wrapping`
- `ecm_emmet`
- `ecm_search`

## Config structure (publish config)
Key sections:
- `profiles` — preset options + extensions
- `contexts` — map context → profile
- `themes` — light/dark theme mapping
- `editor` — base options (font, line height, history/state, gutters, keymap)
- `extensions` — global feature toggles
- `protected_keys` — options not overridable via params

## Manager Events
Editor is wired into manager form events:
- `OnDocFormRender`
- `OnTempFormRender`
- `OnChunkFormRender`
- `OnSnipFormRender`
- `OnPluginFormRender`
- `OnModFormRender`
- `OnTVFormRender`
- `OnRichTextEditorInit`

## Build (development only)
Node is required for development builds. In production, use published `public/dist`.
```bash
npm install
npm run build
```

Build output:
- `public/dist/eCodeMirror.js`
- `public/dist/eCodeMirror.css`

## Notes
- Assets are cache‑busted via `?v=<mtime>` fallback.
- Legacy CM5 plugin must be disabled when enabling eCodeMirror.
- Emmet is enabled via profile extensions (`emmet`), lint currently provides JSON validation when `lint` is enabled.
- For resource content, eCodeMirror activates only when richtext is disabled (no RTE), matching legacy behavior.
- If you previously published `core/custom/config/cms/settings/which_editor.php`, remove it to avoid forcing eCodeMirror as the RTE.

## License
GPL-2.0

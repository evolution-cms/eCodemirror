@php
    $profiles = $profiles ?? [];
    $themes = $themes ?? [];
    $themeModes = $themeModes ?? ['auto', 'light', 'dark'];
    $currentProfile = $currentProfile ?? 'html';
    $currentThemeMode = $currentThemeMode ?? 'auto';
    $currentTheme = $currentTheme ?? 'evo-light';
    $currentFontSize = $currentFontSize ?? 14;
    $currentLineHeight = $currentLineHeight ?? 1.3;
    $currentLineWrapping = $currentLineWrapping ?? true;
    $currentEmmet = $currentEmmet ?? false;
    $currentSearch = $currentSearch ?? true;
@endphp

<div class="row form-row form-element-select">
    <label for="ecm_profile" class="control-label col-5 col-md-3 col-lg-2">
        eCodeMirror Profile:
        <small class="form-text text-muted">[(ecm_profile)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_profile" id="ecm_profile" onchange="documentDirty=true;" size="1">
            @foreach($profiles as $key => $label)
                <option value="{{ $key }}" @if($currentProfile === $key) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_theme_mode" class="control-label col-5 col-md-3 col-lg-2">
        Theme Mode:
        <small class="form-text text-muted">[(ecm_theme_mode)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_theme_mode" id="ecm_theme_mode" onchange="documentDirty=true;" size="1">
            @foreach($themeModes as $mode)
                <option value="{{ $mode }}" @if($currentThemeMode === $mode) selected @endif>{{ $mode }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_theme" class="control-label col-5 col-md-3 col-lg-2">
        Theme Override:
        <small class="form-text text-muted">[(ecm_theme)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_theme" id="ecm_theme" onchange="documentDirty=true;" size="1">
            @foreach($themes as $theme => $label)
                <option value="{{ $theme }}" @if($currentTheme === $theme) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Theme override wins over theme_mode mapping.</small>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_font_size" class="control-label col-5 col-md-3 col-lg-2">
        Font Size:
        <small class="form-text text-muted">[(ecm_font_size)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <input type="number" class="form-control" name="ecm_font_size" id="ecm_font_size" value="{{ $currentFontSize }}" step="1" min="10" max="32" onchange="documentDirty=true;" />
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_line_height" class="control-label col-5 col-md-3 col-lg-2">
        Line Height:
        <small class="form-text text-muted">[(ecm_line_height)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <input type="number" class="form-control" name="ecm_line_height" id="ecm_line_height" value="{{ $currentLineHeight }}" step="0.1" min="1" max="2" onchange="documentDirty=true;" />
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_line_wrapping" class="control-label col-5 col-md-3 col-lg-2">
        Line Wrapping:
        <small class="form-text text-muted">[(ecm_line_wrapping)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_line_wrapping" id="ecm_line_wrapping" onchange="documentDirty=true;" size="1">
            <option value="1" @if($currentLineWrapping) selected @endif>true</option>
            <option value="0" @if(!$currentLineWrapping) selected @endif>false</option>
        </select>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_emmet" class="control-label col-5 col-md-3 col-lg-2">
        Emmet:
        <small class="form-text text-muted">[(ecm_emmet)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_emmet" id="ecm_emmet" onchange="documentDirty=true;" size="1">
            <option value="1" @if($currentEmmet) selected @endif>enabled</option>
            <option value="0" @if(!$currentEmmet) selected @endif>disabled</option>
        </select>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="ecm_search" class="control-label col-5 col-md-3 col-lg-2">
        Search:
        <small class="form-text text-muted">[(ecm_search)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="ecm_search" id="ecm_search" onchange="documentDirty=true;" size="1">
            <option value="1" @if($currentSearch) selected @endif>enabled</option>
            <option value="0" @if(!$currentSearch) selected @endif>disabled</option>
        </select>
    </div>
</div>

<div class="row form-row">
    <div class="col-7 offset-5 col-md-9 offset-md-3 col-lg-10 offset-lg-2">
        <small class="form-text text-muted">Publish config: core/custom/config/cms/settings/eCodeMirror.php</small>
    </div>
</div>

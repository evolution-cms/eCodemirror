import './style.css';

import { EditorState, EditorSelection, RangeSet, RangeSetBuilder, StateEffect, StateField } from '@codemirror/state';
import { EditorView, Decoration, ViewPlugin, keymap, highlightActiveLine, highlightSpecialChars, drawSelection, dropCursor, rectangularSelection, crosshairCursor, lineNumbers, highlightActiveLineGutter, gutter, GutterMarker } from '@codemirror/view';
import { defaultKeymap, indentWithTab, history, historyKeymap } from '@codemirror/commands';
import { indentOnInput, syntaxHighlighting, bracketMatching, foldGutter, foldKeymap, indentUnit, defaultHighlightStyle } from '@codemirror/language';
import { autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { linter, lintGutter, lintKeymap, Diagnostic } from '@codemirror/lint';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { php } from '@codemirror/lang-php';
import { sql } from '@codemirror/lang-sql';
import { oneDark, oneDarkHighlightStyle } from '@codemirror/theme-one-dark';
import { abbreviationTracker, emmetCompletionSource, expandAbbreviation, wrapWithAbbreviation } from '@emmetio/codemirror6-plugin';

type EditorInitConfig = {
  id: string;
  selector: string;
  profile?: string;
  theme?: string;
  theme_mode?: string;
  options?: Record<string, any>;
  extensions?: string[];
  language?: string;
  context?: string;
  history?: Record<string, any>;
  state?: Record<string, any>;
  gutters?: Record<string, any>;
  keymap?: Record<string, string>;
};

type EditorInstance = {
  id: string;
  view: EditorView;
  textarea: HTMLTextAreaElement;
  wrapper: HTMLDivElement;
  config: EditorInitConfig;
  storage: boolean;
  fullscreen: boolean;
  saveTimer: number | null;
  scrollHandler: (() => void) | null;
};

const instances = new Map<string, EditorInstance>();

const toggleBreakpointEffect = StateEffect.define<number>();
const breakpointMarker = new (class extends GutterMarker {
  toDOM(): HTMLElement {
    const dot = document.createElement('div');
    dot.className = 'cm-breakpoint';
    return dot;
  }
})();

function createBreakpointState() {
  return StateField.define({
    create() {
      return RangeSet.empty;
    },
    update(set: any, tr: any) {
      let nextSet = set.map ? set.map(tr.changes) : set;
      for (const effect of tr.effects) {
        if (effect.is(toggleBreakpointEffect)) {
          const pos = effect.value;
          let found = false;
          nextSet = nextSet.update({
            filter: (from: number) => {
              if (from === pos) {
                found = true;
                return false;
              }
              return true;
            },
          });
          if (!found) {
            nextSet = nextSet.update({ add: [breakpointMarker.range(pos)] });
          }
        }
      }
      return nextSet;
    },
  });
}

function breakpointGutterExtension() {
  const breakpoints = createBreakpointState();
  const gutterExt = gutter({
    class: 'cm-breakpoint-gutter',
    markers: (view) => view.state.field(breakpoints),
    initialSpacer: () => breakpointMarker,
    domEventHandlers: {
      mousedown: (view, line) => {
        view.dispatch({
          effects: toggleBreakpointEffect.of(line.from),
        });
        return true;
      },
    },
  });

  return [breakpoints, gutterExt];
}

const modxPatterns: Array<{ regex: RegExp; className: string }> = [
  { regex: /`?\[\[[\s\S]*?\]\]/g, className: 'cm-modx-snippet' },
  { regex: /`?\{\{[\s\S]*?\}\}/g, className: 'cm-modx-chunk' },
  { regex: /`?\[\*[\s\S]*?\*\]/g, className: 'cm-modx-tv' },
  { regex: /`?\[\+[\s\S]*?\+\]/g, className: 'cm-modx-placeholder' },
  { regex: /`?\[![\s\S]*?!\]/g, className: 'cm-modx-snippet-nocache' },
  { regex: /`?\[\([\s\S]*?\)\]/g, className: 'cm-modx-variable' },
  { regex: /`?\[~[\s\S]*?~\]/g, className: 'cm-modx-url' },
  { regex: /`?\[\^[\s\S]*?\^\]/g, className: 'cm-modx-config' },
  { regex: /@[a-zA-Z_][\w-]*/g, className: 'cm-modx-binding' },
  { regex: /&[^\s=]+=?/g, className: 'cm-modx-attribute' },
  { regex: /`[^`\s=]+`/g, className: 'cm-modx-attribute-value' },
];

function buildModxDecorations(doc: string) {
  const builder = new RangeSetBuilder<Decoration>();
  for (const pattern of modxPatterns) {
    pattern.regex.lastIndex = 0;
    let match: RegExpExecArray | null;
    while ((match = pattern.regex.exec(doc))) {
      const from = match.index;
      const to = from + match[0].length;
      if (to > from) {
        builder.add(from, to, Decoration.mark({ class: pattern.className }));
      }
    }
  }
  return builder.finish();
}

function modxOverlayExtension() {
  return ViewPlugin.fromClass(
    class {
      decorations: any;
      constructor(view: EditorView) {
        this.decorations = buildModxDecorations(view.state.doc.toString());
      }
      update(update: any) {
        if (update.docChanged) {
          this.decorations = buildModxDecorations(update.state.doc.toString());
        }
      }
    },
    {
      decorations: (v) => v.decorations,
    }
  );
}

function isTextarea(el: Element | null): el is HTMLTextAreaElement {
  return !!el && el.tagName === 'TEXTAREA';
}

function storageAvailable(): boolean {
  try {
    const key = '__ecm_test__';
    window.localStorage.setItem(key, '1');
    window.localStorage.removeItem(key);
    return true;
  } catch (_err) {
    return false;
  }
}

function getStored(key: string, enabled: boolean): string | null {
  if (!enabled) {
    return null;
  }
  try {
    return window.localStorage.getItem(key);
  } catch (_err) {
    return null;
  }
}

function setStored(key: string, value: string, enabled: boolean): void {
  if (!enabled) {
    return;
  }
  try {
    window.localStorage.setItem(key, value);
  } catch (_err) {
    // ignore storage errors
  }
}

function removeStored(key: string, enabled: boolean): void {
  if (!enabled) {
    return;
  }
  try {
    window.localStorage.removeItem(key);
  } catch (_err) {
    // ignore
  }
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

function isTruthy(value: any): boolean {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function parseJsonErrorPosition(message: string): { line?: number; column?: number; position?: number } {
  const posMatch = message.match(/position\s+(\d+)/i);
  if (posMatch) {
    return { position: Number(posMatch[1]) };
  }
  const lineColMatch = message.match(/line\s+(\d+)\s+column\s+(\d+)/i);
  if (lineColMatch) {
    return { line: Number(lineColMatch[1]), column: Number(lineColMatch[2]) };
  }
  return {};
}

function jsonLinter() {
  return linter((view): Diagnostic[] => {
    const doc = view.state.doc;
    const text = doc.toString();
    if (!text.trim()) {
      return [];
    }
    try {
      JSON.parse(text);
      return [];
    } catch (err: any) {
      const message = err instanceof Error ? err.message : 'Invalid JSON';
      const info = parseJsonErrorPosition(message);
      const length = doc.length;
      let pos = 0;
      if (typeof info.position === 'number' && Number.isFinite(info.position)) {
        pos = clamp(info.position, 0, length);
      } else if (typeof info.line === 'number' && typeof info.column === 'number') {
        const lineNumber = clamp(info.line, 1, doc.lines);
        const line = doc.line(lineNumber);
        pos = clamp(line.from + Math.max(0, info.column - 1), line.from, line.to);
      }
      const from = pos;
      const to = Math.min(pos + 1, length);
      return [
        {
          from,
          to,
          severity: 'error',
          message,
        },
      ];
    }
  });
}

function themeExtension(name: string | undefined, options: Record<string, any>) {
  const fontSize = options.fontSize ? `${options.fontSize}px` : null;
  const lineHeight = options.lineHeight ? `${options.lineHeight}` : null;
  const dynamicTheme = EditorView.theme({
    '&': {
      fontSize: fontSize || undefined,
      lineHeight: lineHeight || undefined,
    },
  });

  const lightTheme = EditorView.theme(
    {
      '&': { color: '#1f2933', backgroundColor: '#ffffff' },
      '.cm-content': { caretColor: '#1f2933' },
      '.cm-gutters': { backgroundColor: '#f8f9fa', color: '#6c757d', border: 'none' },
      '.cm-activeLineGutter': { backgroundColor: '#eef2ff' },
      '.cm-activeLine': { backgroundColor: '#f3f4f6' },
    },
    { dark: false }
  );

  const darkTheme = EditorView.theme(
    {
      '&': { color: '#e5e7eb', backgroundColor: '#1f2937' },
      '.cm-content': { caretColor: '#e5e7eb' },
      '.cm-gutters': { backgroundColor: '#111827', color: '#9ca3af', border: 'none' },
      '.cm-activeLineGutter': { backgroundColor: '#374151' },
      '.cm-activeLine': { backgroundColor: '#111827' },
    },
    { dark: true }
  );

  const themeName = name || 'evo-light';
  if (themeName === 'evo-dark') {
    return [darkTheme, oneDark, syntaxHighlighting(oneDarkHighlightStyle), dynamicTheme];
  }
  return [lightTheme, syntaxHighlighting(defaultHighlightStyle, { fallback: true }), dynamicTheme];
}

function languageExtension(name: string | undefined) {
  switch ((name || '').toLowerCase()) {
    case 'html':
      return html();
    case 'css':
      return css();
    case 'javascript':
    case 'js':
      return javascript();
    case 'json':
      return json();
    case 'php':
      return php();
    case 'sql':
      return sql();
    default:
      return null;
  }
}

function triggerManagerAction(action: string): boolean {
  const buttonIds: Record<string, string[]> = {
    save: ['Button1', 'btnSave', 'save'],
    save_continue: ['Button2', 'btnSaveContinue', 'save_continue'],
    save_new: ['Button3', 'btnSaveNew', 'save_new'],
    save_quit: ['Button4', 'btnSaveQuit', 'save_quit'],
  };

  const candidates = buttonIds[action] || [];
  for (const id of candidates) {
    const byId = document.getElementById(id);
    if (byId) {
      byId.click();
      return true;
    }
    const byName = document.querySelector(`[name="${id}"]`) as HTMLElement | null;
    if (byName) {
      byName.click();
      return true;
    }
  }

  return false;
}

function buildExtensions(cfg: EditorInitConfig, textarea: HTMLTextAreaElement, onFullscreen: (state?: boolean) => boolean) {
  const options = cfg.options || {};
  const extensions: any[] = [];

  extensions.push(
    lineNumbers(),
    highlightActiveLineGutter(),
    highlightSpecialChars(),
    history(),
    drawSelection(),
    dropCursor(),
    indentOnInput(),
    closeBrackets(),
    autocompletion(),
    rectangularSelection(),
    crosshairCursor()
  );

  if (isTruthy(options.lineWrapping)) {
    extensions.push(EditorView.lineWrapping);
  }
  if (isTruthy(options.matchBrackets)) {
    extensions.push(bracketMatching());
  }
  if (isTruthy(options.activeLine)) {
    extensions.push(highlightActiveLine());
  }
  const indentUnitValue = Number(options.indentUnit);
  if (Number.isFinite(indentUnitValue) && indentUnitValue > 0) {
    const unit = ' '.repeat(Math.max(1, indentUnitValue));
    extensions.push(indentUnit.of(unit));
  }
  const tabSizeValue = Number(options.tabSize);
  if (Number.isFinite(tabSizeValue) && tabSizeValue > 0) {
    extensions.push(EditorState.tabSize.of(tabSizeValue));
  }
  if (isTruthy(options.indentWithTabs)) {
    extensions.push(keymap.of([indentWithTab]));
  }

  const language = languageExtension(cfg.language);
  if (language) {
    extensions.push(language);
  }

  const themeExtensions = themeExtension(cfg.theme, options);
  extensions.push(...themeExtensions);

  const featureExtensions = Array.isArray(cfg.extensions) ? cfg.extensions : [];
  if (featureExtensions.includes('search')) {
    extensions.push(highlightSelectionMatches());
  }
  if (featureExtensions.includes('emmet')) {
    extensions.push(abbreviationTracker());
    extensions.push(autocompletion({ override: [emmetCompletionSource] }));
  }
  if (featureExtensions.includes('lint')) {
    extensions.push(lintGutter());
    if ((cfg.language || '').toLowerCase() === 'json') {
      extensions.push(jsonLinter());
    }
  }
  if (featureExtensions.includes('modxOverlay')) {
    extensions.push(modxOverlayExtension());
  }
  if (featureExtensions.includes('fold')) {
    extensions.push(foldGutter());
  }

  if (cfg.gutters && cfg.gutters.markers) {
    extensions.push(...breakpointGutterExtension());
  }
  if (cfg.gutters && cfg.gutters.fold && !featureExtensions.includes('fold')) {
    extensions.push(foldGutter());
  }

  const readOnly = options.readOnly ?? textarea.readOnly;
  if (isTruthy(readOnly)) {
    extensions.push(EditorView.editable.of(false));
  }

  const customKeymap: any[] = [];
  if (cfg.keymap?.save) {
    customKeymap.push({ key: cfg.keymap.save, run: () => triggerManagerAction('save') });
  }
  if (cfg.keymap?.save_continue) {
    customKeymap.push({ key: cfg.keymap.save_continue, run: () => triggerManagerAction('save_continue') });
  }
  if (cfg.keymap?.save_new) {
    customKeymap.push({ key: cfg.keymap.save_new, run: () => triggerManagerAction('save_new') });
  }
  if (cfg.keymap?.save_quit) {
    customKeymap.push({ key: cfg.keymap.save_quit, run: () => triggerManagerAction('save_quit') });
  }

  customKeymap.push(
    {
      key: 'F11',
      run: () => onFullscreen(),
    },
    {
      key: 'Escape',
      run: () => onFullscreen(false),
    }
  );

  const baseKeymap = [
    ...customKeymap,
    ...defaultKeymap,
    ...historyKeymap,
    ...foldKeymap,
    ...completionKeymap,
    ...closeBracketsKeymap,
  ];
  if (featureExtensions.includes('search')) {
    baseKeymap.push(...searchKeymap);
  }
  if (featureExtensions.includes('lint')) {
    baseKeymap.push(...lintKeymap);
  }
  if (featureExtensions.includes('emmet')) {
    baseKeymap.push({ key: 'Mod-e', run: expandAbbreviation });
    baseKeymap.push({ key: 'Mod-Shift-e', run: wrapWithAbbreviation });
  }
  extensions.push(keymap.of(baseKeymap));

  return extensions;
}

function restoreState(cfg: EditorInitConfig, storage: boolean, initialDoc: string) {
  const stateConfig = cfg.state || {};
  const snapshotKey = `ecm_snapshot_${cfg.id}`;
  const cursorKey = `ecm_cursor_${cfg.id}`;
  const scrollKey = `ecm_scroll_${cfg.id}`;

  let doc = initialDoc;
  if (isTruthy(stateConfig.persist_snapshot)) {
    const storedDoc = getStored(snapshotKey, storage);
    if (storedDoc) {
      doc = storedDoc;
    }
  }

  let selection: EditorSelection | undefined;
  if (isTruthy(stateConfig.persist_cursor)) {
    const cursorRaw = getStored(cursorKey, storage);
    if (cursorRaw) {
      try {
        const parsed = JSON.parse(cursorRaw);
        const max = doc.length;
        const anchor = clamp(Number(parsed.anchor ?? 0), 0, max);
        const head = clamp(Number(parsed.head ?? anchor), 0, max);
        selection = EditorSelection.single(anchor, head);
      } catch (_err) {
        // ignore invalid cursor
      }
    }
  }

  let scroll: { top: number; left: number } | null = null;
  if (isTruthy(stateConfig.persist_scroll)) {
    const scrollRaw = getStored(scrollKey, storage);
    if (scrollRaw) {
      try {
        const parsed = JSON.parse(scrollRaw);
        scroll = {
          top: Number(parsed.top || 0),
          left: Number(parsed.left || 0),
        };
      } catch (_err) {
        // ignore
      }
    }
  }

  return { doc, selection, scroll };
}

function schedulePersist(instance: EditorInstance): void {
  const stateConfig = instance.config.state || {};
  if (
    !instance.storage ||
    (!isTruthy(stateConfig.persist_cursor) && !isTruthy(stateConfig.persist_scroll) && !isTruthy(stateConfig.persist_snapshot))
  ) {
    return;
  }

  if (instance.saveTimer !== null) {
    window.clearTimeout(instance.saveTimer);
  }

  const delay = (instance.config.history && instance.config.history.debounce_ms) || 750;
  instance.saveTimer = window.setTimeout(() => {
    const doc = instance.view.state.doc.toString();
    if (isTruthy(stateConfig.persist_snapshot)) {
      setStored(`ecm_snapshot_${instance.id}`, doc, instance.storage);
    }
    if (isTruthy(stateConfig.persist_cursor)) {
      const selection = instance.view.state.selection.main;
      setStored(
        `ecm_cursor_${instance.id}`,
        JSON.stringify({ anchor: selection.anchor, head: selection.head }),
        instance.storage
      );
    }
    if (isTruthy(stateConfig.persist_scroll)) {
      setStored(
        `ecm_scroll_${instance.id}`,
        JSON.stringify({
          top: instance.view.scrollDOM.scrollTop,
          left: instance.view.scrollDOM.scrollLeft,
        }),
        instance.storage
      );
    }
  }, delay);
}

function applyFullscreen(instance: EditorInstance, value?: boolean): boolean {
  const stateConfig = instance.config.state || {};
  const fullscreenKey = `ecm_fullscreen_${instance.id}`;
  const next = value === undefined ? !instance.fullscreen : value;

  if (!isTruthy(stateConfig.persist_fullscreen)) {
    instance.wrapper.classList.toggle('ecm-fullscreen', next);
    document.body.classList.toggle('ecm-body-lock', next);
    instance.fullscreen = next;
    instance.view.requestMeasure();
    return true;
  }

  instance.wrapper.classList.toggle('ecm-fullscreen', next);
  document.body.classList.toggle('ecm-body-lock', next);
  instance.fullscreen = next;
  setStored(fullscreenKey, next ? '1' : '0', instance.storage);
  instance.view.requestMeasure();
  return true;
}

function init(editors: EditorInitConfig[]) {
  if (!Array.isArray(editors)) {
    return;
  }

  const canStore = storageAvailable();

  editors.forEach((cfg) => {
    if (!cfg || !cfg.id || !cfg.selector) {
      return;
    }
    if (instances.has(cfg.id)) {
      return;
    }

    const target = document.querySelector(cfg.selector);
    if (!isTextarea(target)) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'ecm-wrapper';
    target.parentNode?.insertBefore(wrapper, target);
    wrapper.appendChild(target);
    target.classList.add('ecm-hidden-textarea');

    let instance: EditorInstance | null = null;

    const onFullscreen = (state?: boolean) => {
      if (!instance) {
        return false;
      }
      if (state === false && !instance.fullscreen) {
        return false;
      }
      return applyFullscreen(instance, state);
    };

    const extensions = buildExtensions(cfg, target, onFullscreen);

    const restored = restoreState(cfg, canStore, target.value);
    const editorState = EditorState.create({
      doc: restored.doc,
      selection: restored.selection,
      extensions: [
        ...extensions,
        EditorView.updateListener.of((update) => {
          if (update.docChanged) {
            target.value = update.state.doc.toString();
            if (typeof (window as any).documentDirty !== 'undefined') {
              (window as any).documentDirty = true;
            }
            if (instance) {
              schedulePersist(instance);
            }
          } else if (update.selectionSet) {
            if (instance) {
              schedulePersist(instance);
            }
          }
        }),
      ],
    });

    target.value = editorState.doc.toString();

    const view = new EditorView({
      state: editorState,
      parent: wrapper,
    });

    instance = {
      id: cfg.id,
      view,
      textarea: target,
      wrapper,
      config: cfg,
      storage: canStore,
      fullscreen: false,
      saveTimer: null,
      scrollHandler: null,
    };

    if (restored.scroll) {
      requestAnimationFrame(() => {
        view.scrollDOM.scrollTop = restored.scroll?.top || 0;
        view.scrollDOM.scrollLeft = restored.scroll?.left || 0;
      });
    }

    const scrollHandler = () => schedulePersist(instance as EditorInstance);
    instance.scrollHandler = scrollHandler;
    view.scrollDOM.addEventListener('scroll', scrollHandler, { passive: true });

    instances.set(cfg.id, instance);

    const stateConfig = cfg.state || {};
  if (isTruthy(stateConfig.persist_fullscreen)) {
    const stored = getStored(`ecm_fullscreen_${cfg.id}`, canStore);
    if (stored === '1') {
      applyFullscreen(instance, true);
    }
  }
  });
}

function destroy(id: string) {
  const instance = instances.get(id);
  if (!instance) {
    return;
  }

  instance.view.scrollDOM.removeEventListener('scroll', instance.scrollHandler || (() => {}));
  instance.view.destroy();
  instance.textarea.classList.remove('ecm-hidden-textarea');
  instance.textarea.style.display = '';

  if (instance.wrapper.parentNode) {
    instance.wrapper.parentNode.insertBefore(instance.textarea, instance.wrapper);
    instance.wrapper.remove();
  }

  if (instance.saveTimer !== null) {
    window.clearTimeout(instance.saveTimer);
  }

  const stateConfig = instance.config.state || {};
  if (!isTruthy(stateConfig.persist_snapshot)) {
    removeStored(`ecm_snapshot_${instance.id}`, instance.storage);
  }

  instances.delete(id);
}

function refresh(id: string) {
  const instance = instances.get(id);
  if (!instance) {
    return;
  }
  instance.view.requestMeasure();
}

function refreshAll() {
  instances.forEach((instance) => instance.view.requestMeasure());
}

declare global {
  interface Window {
    eCodeMirror?: any;
    eCodeMirrorQueue?: EditorInitConfig[][];
  }
}

window.eCodeMirror = {
  init,
  refresh,
  refreshAll,
  destroy,
};

if (Array.isArray(window.eCodeMirrorQueue)) {
  window.eCodeMirrorQueue.forEach((payload) => init(payload));
  window.eCodeMirrorQueue = [];
}

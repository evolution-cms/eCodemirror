(function () {
  if (typeof console !== 'undefined' && console.warn) {
    console.warn('eCodeMirror: placeholder bundle. Run npm run build to generate CM6 runtime.');
  }
  var instances = new Map();

  function init(editors) {
    if (!Array.isArray(editors)) {
      return;
    }

    editors.forEach(function (cfg) {
      if (!cfg || !cfg.id || !cfg.selector) {
        return;
      }
      if (instances.has(cfg.id)) {
        return;
      }

      var el = document.querySelector(cfg.selector);
      if (!el) {
        return;
      }

      instances.set(cfg.id, cfg);
      el.dataset.ecodemirror = 'pending';
    });
  }

  function destroy(id) {
    if (!id || !instances.has(id)) {
      return;
    }
    instances.delete(id);
  }

  function refresh(id) {
    if (!id || !instances.has(id)) {
      return;
    }
  }

  function refreshAll() {
    instances.forEach(function (_cfg, key) {
      refresh(key);
    });
  }

  window.eCodeMirror = {
    init: init,
    refresh: refresh,
    refreshAll: refreshAll,
    destroy: destroy
  };

  if (Array.isArray(window.eCodeMirrorQueue)) {
    window.eCodeMirrorQueue.forEach(init);
    window.eCodeMirrorQueue = [];
  }
})();

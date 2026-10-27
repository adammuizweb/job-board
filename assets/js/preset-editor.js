(function () {
  'use strict';
  function values() {
    var root = document.querySelector('[data-job-board-preset]');
    var result = {};
    if (!root) return result;
    root.querySelectorAll('[name^="preset_config["]').forEach(function (field) {
      var match = field.name.match(/^preset_config\[([a-z_]+)\]$/);
      if (match) result[match[1]] = field.value;
    });
    return result;
  }
  function sync(source) {
    var root = document.querySelector('[data-job-board-preset]');
    if (!root) return;
    var active = source === 'job_board_jobs';
    root.hidden = !active;
    root.disabled = !active;
  }
  document.addEventListener('shortcode-preset-source-change', function (event) {
    sync(event.detail && event.detail.source ? event.detail.source : '');
  });
  document.addEventListener('shortcode-preset-preview-config', function (event) {
    if (!event.detail || !event.detail.config || event.detail.config.source !== 'job_board_jobs') return;
    Object.assign(event.detail.config, values());
  });
  var source = document.getElementById('filter-source');
  sync(source ? source.value : '');
}());

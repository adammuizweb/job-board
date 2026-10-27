(function () {
  'use strict';

  var root = document.querySelector('.jyb-admin');
  if (!root) return;

  root.querySelectorAll('form[data-jyb-confirm]').forEach(function (form) {
    var submitting = false;
    form.addEventListener('submit', async function (event) {
      if (submitting) {
        submitting = false;
        return;
      }
      event.preventDefault();
      var submitter = event.submitter;
      var message = form.dataset.jybConfirm || 'Continue this action?';
      var options = {
        title: form.dataset.jybConfirmTitle || 'Confirm action',
        message: message,
        confirmText: form.dataset.jybConfirmText || 'Continue',
        focus: 'cancel'
      };
      var confirmApi = window.NewNotifConfirm;
      var method = form.dataset.jybConfirmVariant === 'danger' ? 'danger' : 'warning';
      var accepted = confirmApi && typeof confirmApi[method] === 'function'
        ? await confirmApi[method](options)
        : window.confirm(message);
      if (!accepted || !form.isConnected || !form.reportValidity()) return;
      submitting = true;
      form.requestSubmit(submitter || undefined);
    });
  });
})();

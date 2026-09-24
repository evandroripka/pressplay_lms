(function () {
  'use strict';
  // Details remain readable without JS. Dialogs are a progressive enhancement.
  document.addEventListener('click', function (event) {
    var summary = event.target.closest('.presslms-contract > summary');
    if (!summary || !window.HTMLDialogElement) return;
    event.preventDefault();
    var dialog = document.createElement('dialog');
    dialog.className = 'presslms-contract-dialog';
    dialog.setAttribute('aria-label', summary.textContent);
    var close = document.createElement('button');
    close.type = 'button';
    close.textContent = 'Fechar contrato';
    close.addEventListener('click', function () { dialog.close(); });
    dialog.append(close, summary.parentElement.querySelector('.presslms-contract__text').cloneNode(true));
    dialog.addEventListener('close', function () { dialog.remove(); summary.focus(); });
    document.body.appendChild(dialog);
    dialog.showModal();
    close.focus();
  });

  // Serialize consent writes to avoid one checkbox response overwriting another.
  var queue = Promise.resolve();
  document.addEventListener('change', function (event) {
    var input = event.target;
    var panel = input.closest('[data-terms-session]');
    if (!panel || !input.matches('input[data-course-id]')) return;
    var accepted = input.checked;
    input.disabled = true;
    queue = queue.then(async function () {
      var status = panel.querySelector('[role="status"]');
      status.textContent = 'Salvando sua escolha...';
      try {
        var response = await fetch(panel.dataset.endpoint, {
          method: 'POST', credentials: 'same-origin',
          body: new URLSearchParams({nonce:panel.dataset.nonce,course_id:input.dataset.courseId,version:input.value,accepted:accepted?'1':'0'})
        });
        var result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Nao foi possivel salvar. Tente novamente.');
        status.textContent = accepted ? 'Aceite salvo. Voce pode continuar a compra.' : 'Aceite removido.';
      } catch (error) {
        input.checked = !accepted;
        status.textContent = error.message || 'Falha de conexao. Tente novamente.';
      } finally {
        input.disabled = false;
      }
    });
  });
})();

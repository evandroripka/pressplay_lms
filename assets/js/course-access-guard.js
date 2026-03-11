(function () {
  function getRoot() {
    return document.querySelector('.presslms-course[data-presslms-page="course"]');
  }

  function getConfig() {
    return window.pressLmsCourseAccessGuard || { messages: {} };
  }

  function openDialog(options) {
    if (typeof Swal === 'undefined') {
      var approved = window.confirm(options.text || options.title || 'Confirmar?');
      return Promise.resolve({ isConfirmed: approved });
    }

    return Swal.fire({
      title: options.title,
      text: options.text,
      icon: options.icon || 'warning',
      showCancelButton: !!options.showCancelButton,
      confirmButtonText: options.confirmButtonText,
      cancelButtonText: options.cancelButtonText,
      reverseButtons: true,
      focusCancel: true
    });
  }

  function submitEnrollForm() {
    var form = document.getElementById('presslms-course-enroll-form');
    if (form) {
      form.submit();
    }
  }

  function bindLockedLessons(root) {
    var isLocked = root.getAttribute('data-course-locked') === '1';
    if (!isLocked) {
      return;
    }

    var isPaused = root.getAttribute('data-course-paused') === '1';
    var canEnroll = root.getAttribute('data-course-can-enroll') === '1';
    var cfg = getConfig();
    var lessons = root.querySelectorAll('[data-presslms-lesson-link="1"]');

    lessons.forEach(function (lessonLink) {
      lessonLink.addEventListener('click', function (event) {
        event.preventDefault();

        var dialogOptions = {
          title: cfg.messages.lockedTitle || 'Conteudo restrito',
          text: cfg.messages.lockedText || 'Voce precisa estar logado e matriculado para assistir esta aula.',
          icon: 'warning',
          showCancelButton: canEnroll,
          confirmButtonText: canEnroll
            ? (cfg.messages.confirmEnroll || 'Matricular agora')
            : (cfg.messages.close || 'Fechar'),
          cancelButtonText: cfg.messages.cancel || 'Cancelar'
        };

        if (isPaused) {
          dialogOptions.text = cfg.messages.pausedText || dialogOptions.text;
        } else if (!canEnroll) {
          dialogOptions.text = cfg.messages.unavailableText || dialogOptions.text;
        }

        openDialog(dialogOptions).then(function (result) {
          if (result && result.isConfirmed && canEnroll) {
            submitEnrollForm();
          }
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = getRoot();
    if (!root) {
      return;
    }

    bindLockedLessons(root);
  });
})();

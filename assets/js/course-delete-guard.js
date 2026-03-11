(function ($) {
  function getConfig() {
    return window.pressLmsCourseDeleteGuard || { messages: {} };
  }

  function openDialog(options) {
    if (typeof Swal === 'undefined') {
      return Promise.resolve({
        isConfirmed: window.confirm(options.text || options.title || 'Confirmar?')
      });
    }

    return Swal.fire({
      title: options.title,
      text: options.text,
      icon: options.icon || 'warning',
      showCancelButton: true,
      confirmButtonText: options.confirmButtonText,
      cancelButtonText: options.cancelButtonText,
      reverseButtons: true,
      focusCancel: true
    });
  }

  function bindDeleteGuard($link, payload) {
    if (!$link.length || $link.data('pressLmsDeleteGuardBound')) {
      return;
    }

    $link.data('pressLmsDeleteGuardBound', true);

    if (payload.hasEnrollments) {
      $link.text('Pausar curso');
    }

    $link.on('click', function (event) {
      event.preventDefault();

      var cfg = getConfig();
      var hasEnrollments = !!payload.hasEnrollments;
      var destination = hasEnrollments ? payload.pauseUrl : ($link.attr('href') || '');

      if (!destination) {
        return;
      }

      openDialog({
        title: hasEnrollments ? cfg.messages.pauseTitle : cfg.messages.deleteTitle,
        text: hasEnrollments ? cfg.messages.pauseText : cfg.messages.deleteText,
        confirmButtonText: hasEnrollments ? cfg.messages.confirmPause : cfg.messages.confirmDelete,
        cancelButtonText: cfg.messages.cancel
      }).then(function (result) {
        if (result && result.isConfirmed) {
          window.location.href = destination;
        }
      });
    });
  }

  $(function () {
    var cfg = getConfig();

    $('a.submitdelete[data-press-course-id]').each(function () {
      var $link = $(this);
      bindDeleteGuard($link, {
        hasEnrollments: Number($link.attr('data-press-course-has-enrollments')) === 1,
        pauseUrl: $link.attr('data-press-course-pause-url') || ''
      });
    });

    if (cfg.currentPost && cfg.currentPost.id) {
      bindDeleteGuard($('a.submitdelete'), {
        hasEnrollments: !!cfg.currentPost.hasEnrollments,
        pauseUrl: cfg.currentPost.pauseUrl || ''
      });
    }
  });
})(jQuery);

(function ($) {
  'use strict';

  function getNoticeStack() {
    return $('.presslms-admin-notice-stack').first();
  }

  function showInlineNotice(type, message) {
    var $stack = getNoticeStack();

    if (!$stack.length) {
      window.alert(message);
      return;
    }

    var safeType = String(type || 'info');
    var $notice = $('<div />', {
      class: 'presslms-admin-notice presslms-admin-notice--' + safeType,
      text: message
    });

    $stack.empty().append($notice);
    $('html, body').animate({ scrollTop: $stack.offset().top - 72 }, 180);
  }

  function requestNewPassword(studentName) {
    var nextPassword = window.prompt('Nova senha para ' + studentName + ':', '');

    if (nextPassword === null) {
      return null;
    }

    nextPassword = String(nextPassword).trim();

    if (!nextPassword) {
      showInlineNotice('error', 'Digite uma nova senha.');
      return null;
    }

    if (nextPassword.length < 6) {
      showInlineNotice('error', 'A senha deve ter pelo menos 6 caracteres.');
      return null;
    }

    return nextPassword;
  }

  $(document).on('click', '.js-presslms-change-password', async function () {
    var userId = $(this).data('user-id');
    var studentName = $(this).data('student-name') || 'Aluno';
    var newPassword = requestNewPassword(studentName);

    if (!newPassword) {
      return;
    }

    try {
      var response = await $.post(presslmsAdmin.ajaxUrl, {
        action: 'press_lms_change_student_password',
        nonce: presslmsAdmin.nonce,
        user_id: userId,
        new_password: newPassword
      });

      if (!response || !response.success) {
        throw new Error(response && response.data && response.data.message ? response.data.message : 'Erro ao alterar a senha.');
      }

      showInlineNotice('success', response.data && response.data.message ? response.data.message : 'A senha foi alterada com sucesso.');
    } catch (error) {
      showInlineNotice('error', error.message || 'Não foi possível alterar a senha.');
    }
  });
})(jQuery);

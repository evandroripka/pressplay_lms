(function ($) {
  'use strict';

  $(document).on('click', '.js-presslms-change-password', async function () {
    const userId = $(this).data('user-id');
    const studentName = $(this).data('student-name') || 'Aluno';

    const result = await Swal.fire({
      title: 'Alterar senha',
      html: `
        <div style="text-align:left;">
          <p style="margin:0 0 10px 0;">Nova senha para <strong>${studentName}</strong></p>
          <input id="presslms-new-password" class="swal2-input" type="text" placeholder="Digite a nova senha">
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Salvar',
      cancelButtonText: 'Cancelar',
      focusConfirm: false,
      preConfirm: () => {
        const newPassword = document.getElementById('presslms-new-password').value.trim();

        if (!newPassword) {
          Swal.showValidationMessage('Digite uma nova senha.');
          return false;
        }

        if (newPassword.length < 6) {
          Swal.showValidationMessage('A senha deve ter pelo menos 6 caracteres.');
          return false;
        }

        return newPassword;
      }
    });

    if (!result.isConfirmed) return;

    try {
      const response = await $.post(presslmsAdmin.ajaxUrl, {
        action: 'press_lms_change_student_password',
        nonce: presslmsAdmin.nonce,
        user_id: userId,
        new_password: result.value
      });

      if (!response || !response.success) {
        throw new Error(response?.data?.message || 'Erro ao alterar a senha.');
      }

      await Swal.fire({
        icon: 'success',
        title: 'Senha atualizada',
        text: response.data.message || 'A senha foi alterada com sucesso.'
      });
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: error.message || 'Não foi possível alterar a senha.'
      });
    }
  });

})(jQuery);
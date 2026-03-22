(function ($) {
  'use strict';

  var cssEditor = null;
  var cssPreviewFrame = null;

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

  function getCssEditorField() {
    if (!window.presslmsAdmin || !presslmsAdmin.cssEditor || !presslmsAdmin.cssEditor.fieldId) {
      return null;
    }

    return document.getElementById(String(presslmsAdmin.cssEditor.fieldId));
  }

  function initCssEditor() {
    if (
      !window.presslmsAdmin ||
      !presslmsAdmin.cssEditor ||
      !window.wp ||
      !wp.codeEditor ||
      typeof wp.codeEditor.initialize !== 'function'
    ) {
      return;
    }

    var field = getCssEditorField();
    if (!field) {
      return;
    }

    var instance = wp.codeEditor.initialize(field, presslmsAdmin.cssEditor.settings || {});
    cssEditor = instance && instance.codemirror ? instance.codemirror : null;
  }

  function getCssPreviewFrame() {
    if (cssPreviewFrame && cssPreviewFrame.length) {
      return cssPreviewFrame;
    }

    cssPreviewFrame = $('.js-presslms-css-preview-frame').first();
    return cssPreviewFrame;
  }

  function setActiveCssTab(tabKey) {
    if (!tabKey) {
      return;
    }

    $('.js-presslms-css-tab').each(function () {
      var isActive = String($(this).data('presslmsTab') || '') === String(tabKey);

      $(this)
        .toggleClass('is-active', isActive)
        .attr('aria-selected', isActive ? 'true' : 'false')
        .attr('tabindex', isActive ? '0' : '-1');
    });

    $('.js-presslms-css-pane').each(function () {
      var isActive = String($(this).data('presslmsPane') || '') === String(tabKey);

      $(this)
        .toggleClass('is-active', isActive)
        .prop('hidden', !isActive);
    });
  }

  function initCssTabs() {
    var $tabs = $('.js-presslms-css-tab');

    if (!$tabs.length) {
      return;
    }

    var $activeTab = $tabs.filter('.is-active').first();
    if ($activeTab.length) {
      setActiveCssTab($activeTab.data('presslmsTab'));
      return;
    }

    setActiveCssTab($tabs.first().data('presslmsTab'));
  }

  function isColorValue(value) {
    return /^(#|rgba?\(|hsla?\()/i.test(String(value || '').trim());
  }

  function extractCssVariableName(value) {
    var match = String(value || '').trim().match(/^var\(\s*(--[A-Za-z0-9\-_]+)/i);
    return match && match[1] ? match[1] : '';
  }

  function readCssVariableValue(doc, propertyName) {
    if (!doc || !propertyName || !doc.defaultView || typeof doc.defaultView.getComputedStyle !== 'function') {
      return '';
    }

    var targets = [
      doc.documentElement || null,
      doc.body || null,
      doc.getElementById ? doc.getElementById('presslms-css-preview-probe') : null
    ];

    for (var index = 0; index < targets.length; index += 1) {
      var target = targets[index];

      if (!target) {
        continue;
      }

      var value = doc.defaultView.getComputedStyle(target).getPropertyValue(propertyName);
      if (value) {
        value = String(value).trim();
      }

      if (value) {
        return value;
      }
    }

    return '';
  }

  function resolvePreviewColor(token, doc, visitedVariables) {
    var safeToken = String(token || '').trim();

    if (!safeToken) {
      return '';
    }

    if (isColorValue(safeToken)) {
      return safeToken;
    }

    var propertyName = extractCssVariableName(safeToken);
    if (!propertyName) {
      return '';
    }

    var seen = visitedVariables || {};
    if (seen[propertyName]) {
      return '';
    }

    seen[propertyName] = true;

    var value = readCssVariableValue(doc, propertyName);
    if (!value) {
      return '';
    }

    if (isColorValue(value)) {
      return value;
    }

    return resolvePreviewColor(value, doc, seen);
  }

  function applyResolvedSwatches(doc) {
    $('.js-presslms-css-swatch[data-presslms-preview-token]').each(function () {
      var $swatch = $(this);
      var token = String($swatch.attr('data-presslms-preview-token') || '').trim();

      if (!token || $swatch.attr('data-presslms-resolved') === 'true') {
        return;
      }

      var color = resolvePreviewColor(token, doc, {}) || resolvePreviewColor(token, document, {});
      if (!color) {
        return;
      }

      $swatch
        .css('background', color)
        .removeClass('presslms-css-suggestion__swatch--empty')
        .attr('data-presslms-resolved', 'true');
    });
  }

  function initCssPreviewFrame() {
    var $frame = getCssPreviewFrame();

    if (!$frame.length) {
      applyResolvedSwatches(document);
      return;
    }

    $frame.on('load', function () {
      try {
        var frame = $frame.get(0);
        var frameDocument = frame && frame.contentDocument ? frame.contentDocument : null;
        applyResolvedSwatches(frameDocument || document);
      } catch (error) {
        applyResolvedSwatches(document);
      }
    });

    try {
      var frame = $frame.get(0);
      var frameDocument = frame && frame.contentDocument ? frame.contentDocument : null;

      if (frameDocument && frameDocument.readyState === 'complete') {
        applyResolvedSwatches(frameDocument);
        return;
      }
    } catch (error) {
      applyResolvedSwatches(document);
      return;
    }

    applyResolvedSwatches(document);
  }

  function copyTextToClipboard(value) {
    var safeValue = String(value || '');

    if (!safeValue) {
      return Promise.reject(new Error('Nada para copiar.'));
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
      return navigator.clipboard.writeText(safeValue);
    }

    return new Promise(function (resolve, reject) {
      var field = document.createElement('textarea');
      field.value = safeValue;
      field.setAttribute('readonly', 'readonly');
      field.style.position = 'fixed';
      field.style.top = '-9999px';
      field.style.opacity = '0';

      document.body.appendChild(field);
      field.focus();
      field.select();

      try {
        var copied = document.execCommand('copy');
        document.body.removeChild(field);

        if (!copied) {
          reject(new Error('Não foi possível copiar a variável.'));
          return;
        }

        resolve();
      } catch (error) {
        document.body.removeChild(field);
        reject(error);
      }
    });
  }

  function setSuggestionCopiedState($button) {
    var $action = $button.find('.presslms-css-suggestion__action').first();
    var defaultLabel = String($button.attr('data-presslms-label-default') || 'Copiar');
    var successLabel = String($button.attr('data-presslms-label-success') || 'Copiado');
    var previousTimer = $button.data('presslmsCopyTimer');

    if (previousTimer) {
      window.clearTimeout(previousTimer);
    }

    $button.addClass('is-copied');
    $action.text(successLabel);

    var timer = window.setTimeout(function () {
      $button.removeClass('is-copied');
      $action.text(defaultLabel);
      $button.removeData('presslmsCopyTimer');
    }, 1600);

    $button.data('presslmsCopyTimer', timer);
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

  $(document).on('click', '.js-presslms-css-tab', function () {
    setActiveCssTab($(this).data('presslmsTab'));
  });

  $(document).on('click', '.js-presslms-css-copy', async function () {
    var $button = $(this);
    var value = $(this).data('presslmsInsert') || '';

    if (!value) {
      return;
    }

    try {
      await copyTextToClipboard(String(value));
      setSuggestionCopiedState($button);

      if (cssEditor) {
        cssEditor.focus();
      }
    } catch (error) {
      showInlineNotice('error', error && error.message ? error.message : 'Não foi possível copiar a variável.');
    }
  });

  $(function () {
    initCssEditor();
    initCssTabs();
    initCssPreviewFrame();
  });
})(jQuery);

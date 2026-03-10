document.addEventListener('DOMContentLoaded', function () {
  if (!window.presslmsLessonData) return;
  if (typeof Vimeo === 'undefined') return;

  const data = window.presslmsLessonData;
  const iframe = document.querySelector('.presslms-player__ratio iframe');
  if (!iframe) return;

  const player = new Vimeo.Player(iframe);

  let duration = 0;
  let lastKnownSeconds = 0;
  let markedCompleted = false;
  let isSending = false;

  function shouldMarkCompleted(seconds) {
    if (markedCompleted) return true;
    if (duration <= 0) return false;
    return seconds >= Math.floor(duration * 0.9);
  }

  function buildPayload(seconds, completed = false) {
    const params = new URLSearchParams();
    params.append('action', 'press_lms_track_progress');
    params.append('nonce', data.nonce);
    params.append('course_id', String(data.courseId));
    params.append('lesson_id', String(data.lessonId));
    params.append('watched_seconds', String(Math.floor(seconds)));
    params.append('completed', completed ? '1' : '0');
    return params;
  }

  function sendProgress(seconds, completed = false, useBeacon = false) {
    const payload = buildPayload(seconds, completed);

    if (useBeacon && navigator.sendBeacon) {
      const blob = new Blob([payload.toString()], {
        type: 'application/x-www-form-urlencoded; charset=UTF-8'
      });
      navigator.sendBeacon(data.ajaxUrl, blob);
      return Promise.resolve();
    }

    if (isSending) return Promise.resolve();
    isSending = true;

    return fetch(data.ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      }
    })
      .then(r => r.json())
      .catch(err => {
        console.error('Erro ao salvar progresso:', err);
      })
      .finally(() => {
        isSending = false;
      });
  }

  player.getDuration().then(function (d) {
    duration = Math.floor(d || 0);
  }).catch(function () {
    duration = 0;
  });

  player.on('timeupdate', function (event) {
    lastKnownSeconds = Math.floor(event.seconds || 0);

    if (!markedCompleted && shouldMarkCompleted(lastKnownSeconds)) {
      markedCompleted = true;
    }
  });

  player.on('pause', function (event) {
    const seconds = Math.floor((event && event.seconds) || lastKnownSeconds || 0);
    const completed = shouldMarkCompleted(seconds);

    if (completed) {
      markedCompleted = true;
    }

    sendProgress(seconds, completed, false);
  });

  player.on('ended', function () {
    markedCompleted = true;

    player.getCurrentTime().then(function (seconds) {
      sendProgress(seconds, true, false);
    }).catch(function () {
      sendProgress(lastKnownSeconds || duration || 0, true, false);
    });
  });

  // Salva ao clicar em links de aula / navegação interna
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (!link) return;

    const href = link.getAttribute('href') || '';
    const isLessonLink =
      link.classList.contains('presslms-lessons__item') ||
      href.indexOf('/aula/') !== -1;

    if (!isLessonLink) return;

    player.getCurrentTime().then(function (seconds) {
      const completed = shouldMarkCompleted(seconds);
      if (completed) {
        markedCompleted = true;
      }
      sendProgress(seconds, completed, true);
    }).catch(function () {
      sendProgress(lastKnownSeconds || 0, markedCompleted, true);
    });
  });

  // Melhor que beforeunload para esse caso
  window.addEventListener('pagehide', function () {
    sendProgress(lastKnownSeconds || 0, markedCompleted, true);
  });
});
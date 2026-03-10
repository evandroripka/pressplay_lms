document.addEventListener('DOMContentLoaded', function () {
  if (!window.presslmsLessonData) return;
  if (typeof Vimeo === 'undefined') return;

  const iframe = document.querySelector('.presslms-player__ratio iframe');
  if (!iframe) return;

  const player = new Vimeo.Player(iframe);

  let duration = 0;
  let lastKnownSeconds = 0;
  let markedCompleted = false;

  // evita posts duplicados inúteis, mas sem bloquear ended
  let lastSentSeconds = -1;
  let lastSentCompleted = null;

  function getLessonData() {
    return window.presslmsLessonData || {};
  }

  function shouldMarkCompleted(seconds) {
    if (markedCompleted) return true;
    if (duration <= 0) return false;
    return seconds >= Math.floor(duration * 0.9);
  }

  function buildPayload(seconds, completed = false) {
    const data = getLessonData();

    const params = new URLSearchParams();
    params.append('action', 'press_lms_track_progress');
    params.append('nonce', data.nonce || '');
    params.append('course_id', String(data.courseId || 0));
    params.append('lesson_id', String(data.lessonId || 0));
    params.append('watched_seconds', String(Math.floor(seconds || 0)));
    params.append('completed', completed ? '1' : '0');

    return params;
  }

  function shouldSkipSend(seconds, completed) {
    const normalizedSeconds = Math.floor(seconds || 0);

    return (
      normalizedSeconds === lastSentSeconds &&
      completed === lastSentCompleted
    );
  }

  function markAsSent(seconds, completed) {
    lastSentSeconds = Math.floor(seconds || 0);
    lastSentCompleted = completed;
  }

  function sendProgress(seconds, completed = false, useBeacon = false) {
    const normalizedSeconds = Math.floor(seconds || 0);

    if (shouldSkipSend(normalizedSeconds, completed)) {
      return Promise.resolve();
    }

    const payload = buildPayload(normalizedSeconds, completed);

    if (useBeacon && navigator.sendBeacon) {
      const blob = new Blob([payload.toString()], {
        type: 'application/x-www-form-urlencoded; charset=UTF-8'
      });

      navigator.sendBeacon(getLessonData().ajaxUrl, blob);
      markAsSent(normalizedSeconds, completed);
      return Promise.resolve();
    }

    return fetch(getLessonData().ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      }
    })
      .then(r => r.json())
      .then(res => {
        markAsSent(normalizedSeconds, completed);
        return res;
      })
      .catch(err => {
        console.error('Erro ao salvar progresso:', err);
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

    // salva em toda pausa real acima de 0
    if (seconds > 0) {
      sendProgress(seconds, completed, false);
    }
  });

  player.on('ended', function () {
    markedCompleted = true;

    player.getCurrentTime().then(function (seconds) {
      sendProgress(seconds || duration || lastKnownSeconds || 0, true, false);
    }).catch(function () {
      sendProgress(lastKnownSeconds || duration || 0, true, false);
    });
  });

  // fallback ao sair da página
  window.addEventListener('pagehide', function () {
    sendProgress(lastKnownSeconds || 0, markedCompleted, true);
  });
});
document.addEventListener('DOMContentLoaded', function () {
  const data = window.presslmsLessonData;
  if (!data) return;
  const button = document.getElementById('presslms-complete-lesson');
  const status = document.getElementById('presslms-progress-status');
  const progress = document.getElementById('presslms-course-progress');
  const bar = document.getElementById('presslms-course-progress-bar');
  let duration = Number(data.lessonDuration) || 0;
  let position = Math.max(0, Number(data.resumePosition ?? data.watchedSeconds) || 0);
  let completed = Boolean(data.completed);
  let confirmedCompleted = completed;
  let manual = false;
  let ranges = Array.isArray(data.playedRanges) ? data.playedRanges : [];
  let player = null;
  let sending = false;
  let queued = false;
  let readingRanges = false;
  let lastSaved = '';
  let lastSample = 0;

  function message(text) { if (status) status.textContent = text; }
  function formatTime(value) {
    const seconds = Math.max(0, Math.floor(value));
    const minutes = Math.floor(seconds / 60);
    return (minutes >= 60 ? Math.floor(minutes / 60) + ':' + String(minutes % 60).padStart(2, '0') : minutes) + ':' + String(seconds % 60).padStart(2, '0');
  }
  function showPercent(value) {
    const percent = Math.max(0, Math.min(100, Number(value) || 0));
    if (progress) progress.textContent = percent.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%';
    if (bar) bar.value = percent;
  }
  function mergeRanges(values) {
    const sorted = values.filter(r => Array.isArray(r) && r.length === 2 && Number.isFinite(r[0]) && Number.isFinite(r[1]))
      .map(r => [Math.max(0, r[0]), Math.min(duration, r[1])]).filter(r => r[1] > r[0]).sort((a, b) => a[0] - b[0]);
    return sorted.reduce((merged, range) => {
      const last = merged[merged.length - 1];
      if (last && range[0] <= last[1]) last[1] = Math.max(last[1], range[1]);
      else merged.push(range);
      return merged;
    }, []);
  }
  function watchedSeconds() {
    return Math.min(duration, Math.max(Number(data.watchedSeconds) || 0, Math.floor(ranges.reduce((total, r) => total + r[1] - r[0], 0))));
  }
  function previewProgress() {
    if (!data.durationComplete || !data.courseDuration) return;
    const current = completed ? duration : watchedSeconds();
    const total = Math.max(0, Number(data.otherWatchedSeconds) || 0) + current;
    // Only a confirmed server response may unlock the final 100% state.
    showPercent(Math.min(Number(data.coursePercent) === 100 ? 100 : 99.99, total / data.courseDuration * 100));
  }
  async function readPlayed() {
    if (!player || manual) return;
    ranges = mergeRanges(ranges.concat(await player.getPlayed()));
    previewProgress();
  }
  function payload() {
    const body = new URLSearchParams({
      action: 'press_lms_track_progress', nonce: data.nonce || '',
      course_id: String(data.courseId), lesson_id: String(data.lessonId),
      watched_seconds: String(Math.floor(watchedSeconds())), completed: completed ? '1' : '0'
    });
    if (player && !manual) {
      body.set('played_ranges', JSON.stringify(ranges));
      body.set('position', String(position));
      body.set('video_id', String(data.vimeoId));
    }
    return body;
  }
  function applyResult(result) {
    const saved = result.data;
    if (typeof saved.lesson_completed === 'boolean') completed = saved.lesson_completed;
    confirmedCompleted = completed;
    data.coursePercent = saved.course_progress_percent;
    if (saved.course_duration !== undefined) data.courseDuration = saved.course_duration;
    if (saved.duration_complete !== undefined) data.durationComplete = saved.duration_complete;
    if (saved.course_watched_seconds !== undefined) {
      data.otherWatchedSeconds = Math.max(0, saved.course_watched_seconds - (completed ? duration : watchedSeconds()));
    }
    showPercent(saved.course_progress_percent);
    const courseLabel = document.getElementById('presslms-course-duration');
    if (courseLabel && data.courseDuration > 0) courseLabel.textContent = formatTime(data.courseDuration);
    message(completed ? 'Aula concluida. Progresso salvo.' : 'Progresso salvo.');
    if (button && completed) button.textContent = 'Aula concluida';
  }
  async function save() {
    if (sending) { queued = true; return; }
    sending = true;
    if (button) button.disabled = true;
    try {
      await readPlayed();
      const body = payload();
      const key = body.toString();
      if (key === lastSaved) return;
      const response = await fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.data?.message || 'Tente novamente ou recarregue a pagina.');
      lastSaved = key;
      applyResult(result);
    } catch (error) {
      completed = confirmedCompleted;
      message('Falha ao salvar o progresso. ' + error.message);
      if (button) button.hidden = false;
    } finally {
      sending = false;
      if (button) button.disabled = confirmedCompleted;
      if (queued) { queued = false; save(); }
    }
  }
  if (button) button.addEventListener('click', function () { manual = true; completed = true; save(); });

  const iframe = document.querySelector('.presslms-player__ratio iframe');
  let isVimeo = false;
  try { isVimeo = iframe && new URL(iframe.src, location.href).hostname === 'player.vimeo.com'; } catch (error) { /* Invalid embeds use manual completion. */ }
  if (!isVimeo || !window.Vimeo || typeof window.Vimeo.Player !== 'function') {
    message(completed ? 'Aula concluida.' : 'Ao terminar o conteudo, marque esta aula como concluida.');
    return;
  }
  try { player = new window.Vimeo.Player(iframe); }
  catch (error) { message('Acompanhamento automatico indisponivel. Use a conclusao manual.'); return; }

  player.ready().then(async function () {
    duration = Math.round(await player.getDuration());
    const label = document.getElementById('presslms-lesson-duration');
    if (label && duration > 0) label.textContent = formatTime(duration);
    if (!completed && position > 0 && position < duration - 2) await player.setCurrentTime(position);
    if (button) button.hidden = true;
    await save();
  }).catch(function () {
    message('Acompanhamento automatico indisponivel. Use a conclusao manual.');
    if (button) button.hidden = false;
  });
  player.on('timeupdate', function (event) {
    position = Math.max(0, Number(event.seconds) || 0);
    if (readingRanges || Date.now() - lastSample < 1000) return;
    lastSample = Date.now();
    readingRanges = true;
    readPlayed().catch(() => {}).finally(() => { readingRanges = false; });
  });
  player.on('pause', save);
  player.on('ended', save);
  player.on('error', function () {
    if (button) button.hidden = false;
    message('Player indisponivel. Voce pode tentar novamente ou concluir manualmente.');
  });
  window.setInterval(function () { if (position > 0) save(); }, 10000);
  function flush() {
    const body = payload().toString();
    if (body !== lastSaved && navigator.sendBeacon) {
      navigator.sendBeacon(data.ajaxUrl, new Blob([body], { type: 'application/x-www-form-urlencoded; charset=UTF-8' }));
    }
  }
  document.addEventListener('visibilitychange', function () { if (document.hidden) flush(); });
  window.addEventListener('pagehide', flush);
});

/* Browser QA: public GETs and synthetic screens; no order submission.
 * Optional PRESS_LMS_QA_CART=1 permits only the LMS add-to-cart POST in a guest session.
 * Requires playwright-core; PHP fixtures block database writes and mail.
 */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async function () {
  const outputDir = process.env.PRESS_LMS_QA_OUTPUT || '/tmp/presslms-qa/results';
  fs.mkdirSync(outputDir, { recursive: true });
  const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'runtime.php'), '--fixtures'], { maxBuffer: 16 * 1024 * 1024, timeout: 120000 }).toString());
  const base = fixtures.base;
  const results = [];
  const errors = [];
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  let mockFailures = 0;
  let mockSaves = 0;
  const progressRequests = [];
  let videoState = { ranges: [[0,34]], position: 34 };
  await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: new URL(base).origin });
  await context.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const match = url.pathname.match(/\/__press_lms_qa\/(\w+)\//);
    if (match && match[1] === 'lesson_vimeo' && fixtures.fixtures.lesson) {
      const mock = `<div class="presslms-player__ratio"><iframe src="https://player.vimeo.com/video/1" title="QA mock"></iframe></div>
        <script>
        window.presslmsLessonData.watchedSeconds = ${videoState.ranges.reduce((s,r)=>s+r[1]-r[0],0)};
        window.presslmsLessonData.resumePosition = ${videoState.position};
        window.presslmsLessonData.playedRanges = ${JSON.stringify(videoState.ranges)};
        window.presslmsLessonData.lessonDuration = 120;
        window.presslmsLessonData.courseDuration = 600;
        window.presslmsLessonData.durationComplete = true;
        window.presslmsLessonData.otherWatchedSeconds = 0;
        window.presslmsLessonData.vimeoId = 1;
        window.presslmsLessonData.completed = false;
        window.Vimeo = { Player: class {
          constructor() { window.qaPlayer = this; this.events = {}; this.played = []; }
          ready() { return Promise.resolve(); }
          getDuration() { return Promise.resolve(120); }
          getPlayed() { return Promise.resolve(this.played); }
          setCurrentTime(value) { window.qaSeek = value; return Promise.resolve(value); }
          on(name, callback) { this.events[name] = callback; }
        }};
        </script>`;
      const body = fixtures.fixtures.lesson.replace(/<script src="[^"]*\/assets\/js\/lesson-progress\.js"/, mock + '$&');
      return route.fulfill({contentType:'text/html',body});
    }
    if (url.hostname === 'player.vimeo.com' && /__press_lms_qa/.test(request.frame().url())) {
      return route.fulfill({contentType:'text/html',body:'<!doctype html><title>QA player double</title>'});
    }
    if (match && fixtures.fixtures[match[1]]) {
      return route.fulfill({ contentType: 'text/html', body: fixtures.fixtures[match[1]] });
    }
    if (request.method() === 'POST') {
      const params = new URLSearchParams(request.postData() || '');
      if (url.pathname.endsWith('/admin-ajax.php') && params.get('action') === 'press_lms_track_progress') {
        mockSaves++;
        if (mockFailures-- > 0) return route.fulfill({ status: 500, json: { success: false } });
        progressRequests.push(Object.fromEntries(params));
        if (params.has('played_ranges')) {
          videoState = { ranges: JSON.parse(params.get('played_ranges')), position: Number(params.get('position')) };
          const watched = videoState.ranges.reduce((s,r)=>s+r[1]-r[0],0);
          return route.fulfill({json:{success:true,data:{course_progress_percent:Math.round(watched/600*10000)/100,course_duration:600,course_watched_seconds:watched,duration_complete:true,lesson_duration:120,lesson_completed:watched>=119}}});
        }
        return route.fulfill({ json: { success: true, data: { course_progress_percent: params.get('completed') === '1' ? 100 : 0, lesson_completed: params.get('completed') === '1' } } });
      }
      if (process.env.PRESS_LMS_QA_CART === '1' && url.pathname.endsWith('/admin-post.php') && params.get('action') === 'press_lms_enroll') {
        return route.continue();
      }
      if (process.env.PRESS_LMS_QA_CART === '1' && ['update_order_review','get_refreshed_fragments'].includes(url.searchParams.get('wc-ajax'))) {
        return route.continue();
      }
      return route.abort();
    }
    return route.continue();
  });
  const page = await context.newPage();
  page.setDefaultTimeout(15000);
  page.setDefaultNavigationTimeout(30000);
  page.on('pageerror', error => errors.push({ url: page.url(), message: error.message, stack: error.stack }));
  async function test(name, run) {
    try { await run(); results.push({ name, passed: true }); console.log('PASS: ' + name); }
    catch (error) {
      results.push({ name, passed: false, error: error.message });
      console.log('FAIL: ' + name + ': ' + error.message);
      await page.screenshot({ path: path.join(outputDir, name.replace(/[^a-z0-9]/gi, '-') + '-failure.png'), fullPage: true }).catch(() => {});
    }
  }
  async function fixture(name) {
    await page.goto(new URL('/__press_lms_qa/' + name + '/', base).href, { waitUntil: 'networkidle' });
  }
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const route of ['/cursos/', fixtures.course_path, '/cadastro/', '/meus-cursos/']) {
      await test('public ' + width + ' ' + route, async () => {
        const response = await page.goto(new URL(route,base).href, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(),200);
        await page.locator('.presslms, .press-container').first().waitFor();
        await page.waitForTimeout(700);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
        assert.ok(overflow <= 2, 'horizontal overflow: ' + overflow);
        const titleVisible = await page.locator('.presslms h1, .press-container h1').first().evaluate(el => {
          const box = el.getBoundingClientRect();
          const top = document.elementFromPoint(box.left + 4, box.top + box.height / 2);
          return top === el || el.contains(top);
        });
        assert.equal(titleVisible, true, 'theme header covers the LMS title');
        await page.screenshot({ path: path.join(outputDir, 'public-' + width + '-' + route.replace(/[^a-z0-9]/gi,'') + '.png'), fullPage: true });
      });
    }
  }
  await test('unknown course returns 404', async () => {
    const response = await page.goto(new URL('/curso/presslms-qa-missing/',base).href);
    assert.equal(response.status(),404);
    assert.match(await page.locator('main').innerText(), /n.o encontrado/i);
  });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await test('contract readable in dialog with explicit unchecked consent', async () => {
    await fixture('terms');
    const checkbox = page.locator('.presslms-contracts input[type="checkbox"]');
    assert.equal(await checkbox.isChecked(),false);
    assert.equal(await checkbox.evaluate(el=>el.form.checkValidity()),false);
    await page.locator('summary').click();
    assert.equal(await page.locator('dialog:modal').count(),1);
    assert.match(await page.locator('dialog').innerText(),/Contrato ficticio/);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog').count(),0);
    assert.equal(await page.locator('summary').evaluate(el=>el===document.activeElement),true);
    await checkbox.check();
    assert.equal(await checkbox.evaluate(el=>el.form.checkValidity()),true);
  });
  await test('course materials editor adds links, files and removes rows', async () => {
    await fixture('materials');
    const before=await page.locator('.press-material-row').count();
    await page.locator('#pressAddLink').click();
    assert.equal(await page.locator('.press-material-row').count(),before+1);
    const link=page.locator('.press-material-row').last();
    await link.locator('.press-material-name').fill('Apostila do curso');
    await link.locator('.press-material-link-url').fill('https://example.test/apostila.pdf');
    await page.locator('#pressAddFile').click();
    assert.equal(await page.locator('.press-material-row').last().locator('.press-material-type').inputValue(),'file');
    await page.locator('.press-material-row').last().locator('.press-material-remove').click();
    assert.equal(await page.locator('.press-material-row').count(),before+1);
  });
  await test('manual access form supports existing users and unlimited duration', async () => {
    await fixture('manual_access');
    const form=page.locator('form[method="post"]');
    assert.equal(await form.locator('input[name="action"]').inputValue(),'press_lms_grant_access');
    await form.locator('[name="user_id"]').selectOption('999990');
    const courseValue=await form.locator('[name="course_id"] option').nth(1).getAttribute('value');
    await form.locator('[name="course_id"]').selectOption(courseValue);
    await form.locator('[name="access_type"]').selectOption('lifetime');
    assert.equal(await form.locator('[name="notify"]').isChecked(),false);
    assert.equal(await form.evaluate(el=>el.checkValidity()),true);
    // Never submit: granting real access is outside browser fixture testing.
  });
  if (fixtures.fixtures.sample) {
    await test('sample plays without progress UI or tracking requests', async () => {
      const before = mockSaves;
      await fixture('sample');
      assert.equal(await page.locator('.presslms-player iframe').count(),1);
      assert.equal(await page.locator('#presslms-complete-lesson').count(),0);
      assert.equal(await page.evaluate(()=>window.presslmsLessonData===undefined),true);
      assert.ok(await page.locator('.presslms-sample-label').count()>0);
      assert.equal(await page.locator('.presslms-materials__link').count(),0);
      assert.equal(mockSaves,before);
    });
    await test('sample link bypasses purchase guard and trailer uses native controls', async () => {
      await fixture('sample_course');
      let dialogSeen = false;
      const dismiss = async dialog => { dialogSeen=true; await dialog.dismiss(); };
      page.on('dialog',dismiss);
      await page.locator('[data-free-preview="0"]').first().click();
      assert.equal(dialogSeen,true);
      page.off('dialog',dismiss);
      assert.equal(await page.locator('[data-trailer-expand]').count(),0);
      assert.match(await page.locator('#presslms-trailer-player iframe').getAttribute('src'),/fullscreen=1/);
      assert.equal(await page.locator('#presslms-trailer-player iframe').getAttribute('allowfullscreen'),'');
      const link=page.locator('[data-free-preview="1"]').first();
      const target=await link.getAttribute('href');
      await Promise.all([page.waitForURL(target),link.click()]);
    });
  }
  await test('course editor tabs and keyboard', async () => {
    await fixture('editor');
    for (const tab of ['details','includes','certificate','lessons']) {
      await page.locator('[data-tab-target="' + tab + '"]').click();
      assert.equal(await page.locator('.press-course-tab:visible').count(),1);
      assert.equal(await page.locator('[data-tab-panel="' + tab + '"]').isVisible(),true);
    }
    assert.ok(await page.locator('input[name^="press_lesson_order["]').count()>0);
    assert.equal((await page.locator('[data-tab-panel="lessons"] tbody tr').first().locator('td').first().innerText()).trim(),'01');
    await page.locator('[data-tab-target="lessons"]').press('Home');
    assert.equal(await page.locator('[data-tab-target="details"]').getAttribute('aria-selected'),'true');
  });
  await test('certificate CSS editor and live preview', async () => {
    await page.locator('[data-tab-target="certificate"]').click();
    await page.locator('.CodeMirror:visible').waitFor();
    await page.evaluate(() => {
      document.querySelector('.CodeMirror').CodeMirror.setValue('body { background: rgb(1, 2, 3); }');
    });
    await page.waitForTimeout(700);
    const frame = page.frameLocator('#press_course_certificate_preview');
    assert.equal(await frame.locator('body').evaluate(el => getComputedStyle(el).backgroundColor),'rgb(1, 2, 3)');
    await page.screenshot({ path: path.join(outputDir,'certificate-editor.png'),fullPage:true });
  });
  await test('CSS categories, clipboard and keyboard', async () => {
    await fixture('settings');
    for (const tab of ['elementor','wordpress','theme']) {
      await page.locator('[data-presslms-tab="' + tab + '"]').click();
      assert.equal(await page.locator('.js-presslms-css-pane:visible').count(),1);
    }
    const copy = page.locator('.js-presslms-css-pane:visible .js-presslms-css-copy').first();
    const value = await copy.getAttribute('data-presslms-insert');
    await copy.click();
    assert.equal(await page.evaluate(() => navigator.clipboard.readText()),value);
    await page.locator('[data-presslms-tab="theme"]').press('Home');
    assert.equal(await page.locator('[data-presslms-tab="elementor"]').getAttribute('aria-selected'),'true');
    await page.screenshot({ path:path.join(outputDir,'css-settings.png'),fullPage:true });
  });
  if (fixtures.fixtures.lesson) {
    await test('manual completion retries storage failure', async () => {
      mockFailures = 1;
      mockSaves = 0;
      await fixture('lesson');
      const button = page.locator('#presslms-complete-lesson');
      await button.click();
      await page.waitForFunction(() => document.getElementById('presslms-progress-status').textContent.includes('Falha'));
      assert.equal(await button.isEnabled(),true);
      await button.click();
      await page.waitForFunction(() => document.getElementById('presslms-course-progress').textContent === '100%');
      assert.equal(mockSaves,2);
      assert.equal(await button.isDisabled(),true);
      assert.equal(await page.getByText('[Curso 1]',{exact:true}).count(),0);
    });
    await test('Vimeo adapter resumes and saves progress with SDK double', async () => {
      progressRequests.length = 0;
      await fixture('lesson_vimeo');
      await page.waitForFunction(() => window.qaSeek === 34);
      assert.equal(await page.locator('#presslms-complete-lesson').isHidden(),true);
      await page.evaluate(() => {
        window.qaPlayer.played = [[0,60]];
        window.qaPlayer.events.timeupdate({seconds:60});
        window.qaPlayer.events.pause();
      });
      await page.waitForFunction(() => document.getElementById('presslms-course-progress').textContent === '10%');
      assert.equal(progressRequests.at(-1).watched_seconds,'60');
      await page.evaluate(() => { window.qaPlayer.events.timeupdate({seconds:119}); window.qaPlayer.events.ended(); });
      await page.waitForTimeout(300);
      assert.equal(await page.locator('#presslms-course-progress').innerText(),'10%');
      assert.equal(progressRequests.at(-1).completed,'0');
      await fixture('lesson_vimeo');
      await page.waitForFunction(() => window.qaPlayer !== undefined);
      // Resume at 60 instead of near the end to exercise the resume guard.
      videoState.position = 60;
      await fixture('lesson_vimeo');
      await page.waitForFunction(() => window.qaSeek === 60);
      await page.evaluate(() => { window.qaPlayer.played = [[0,120]]; window.qaPlayer.events.ended(); });
      await page.waitForFunction(() => document.getElementById('presslms-course-progress').textContent === '20%');
      assert.equal(await page.locator('#presslms-course-progress-bar').getAttribute('value'),'20');
      assert.equal(await page.locator('#presslms-lesson-duration').innerText(),'2:00');
      await page.evaluate(() => window.qaPlayer.events.error());
      assert.equal(await page.locator('#presslms-complete-lesson').isVisible(),true);
    });
    for (const width of [1440,390]) {
      await test('lesson responsive ' + width,async () => {
        await page.setViewportSize({width,height:1000});
        await fixture('lesson');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
        await page.screenshot({path:path.join(outputDir,'lesson-'+width+'.png'),fullPage:true});
      });
    }
  }
  if (process.env.PRESS_LMS_QA_CART === '1') {
    await test('guest purchase reaches checkout without creating order', async () => {
      await page.goto(new URL(fixtures.course_path,base).href);
      const buy = page.locator('button[form="presslms-course-enroll-form"]');
      await buy.waitFor();
      assert.equal(await buy.isEnabled(),true);
      await Promise.all([page.waitForURL(/checkout|finalizar/),buy.click()]);
      assert.equal(await page.locator('form.checkout, .wc-block-checkout').count() > 0,true);
      await page.waitForTimeout(3000);
      await page.screenshot({path:path.join(outputDir,'guest-checkout.png'),fullPage:true});
      // Never submit the checkout form or payment controls.
    });
  }
  fs.writeFileSync(path.join(outputDir,'results.json'),JSON.stringify({runtime:fixtures.checks,results,pageErrors:errors},null,2));
  await browser.close();
  console.log(JSON.stringify({passed:results.filter(x=>x.passed).length,failed:results.filter(x=>!x.passed).length,pageErrors:errors.length,outputDir}));
  if (results.some(x=>!x.passed)) process.exitCode=1;
})().catch(error => { console.error(error); process.exitCode=1; });

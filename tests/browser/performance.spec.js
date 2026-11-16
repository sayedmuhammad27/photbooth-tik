import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const fixtures = 'storage/app/runtime/browser';
const jpeg = fs.readFileSync(`${fixtures}/frame.jpg`);

async function installServer(context) {
  const state = { pushes: 0, frames: 0, captures: 0, snapshots: 0, processes: 0,
    lastRelay: 0, relayReads: 0, dropAnswers: false, trigger: null, ready: false, host: false, pending: new Set(), offers: {}, answers: {}, errors: [] };
  context.on('page', page => page.on('pageerror', error => state.errors.push(error.message)));
  await context.addInitScript(() => {
    window.jpegEncodes = 0;
    const original = HTMLCanvasElement.prototype.toBlob;
    HTMLCanvasElement.prototype.toBlob = function (...args) {
      window.jpegEncodes++;
      return original.apply(this, args);
    };
  });
  await context.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const pathname = url.pathname;
    const json = (data, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });
    const photo = () => route.fulfill({ contentType: 'image/jpeg', body: jpeg });
    const pages = { '/admin/camera-hub': 'hub', '/test-photo/session/1': 'booth', '/': 'welcome' };
    if (pages[pathname]) {
      return route.fulfill({ contentType: 'text/html', body: fs.readFileSync(`${fixtures}/${pages[pathname]}.html`) });
    }
    if (pathname.startsWith('/assets/') || pathname.startsWith('/fonts/')) {
      const file = path.join('public', pathname);
      if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
      const contentType = file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : undefined;
      return route.fulfill({ path: file, contentType });
    }
    if (pathname === '/digicam/liveview') { state.frames++; return photo(); }
    if (pathname === '/digicam/capture') { state.captures++; return photo(); }
    if (pathname.startsWith('/digicam/')) return json({ ok: true, cameras: 'Test DSLR' });
    if (pathname === '/camera-hub/status') return json({ hub_active: state.host });
    if (pathname === '/camera-hub/frame') { state.relayReads++; state.lastRelay = Date.now(); return photo(); }
    if (pathname === '/camera-hub/push') { state.pushes++; return json({ ok: true }); }
    if (pathname === '/camera-hub/trigger') {
      state.trigger = `shot_${state.captures + 1}`;
      state.ready = false;
      return json({ capture_id: state.trigger });
    }
    if (pathname === '/camera-hub/snapshot/push') { state.ready = true; return json({ ok: true }); }
    if (pathname === '/camera-hub/snapshot') { state.snapshots++; return state.ready ? photo() : json({}, 404); }
    if (pathname === '/camera-hub/signal') {
      const data = request.method() === 'POST' ? request.postDataJSON() : Object.fromEntries(url.searchParams);
      if (data.action === 'register_host') state.host = true;
      if (data.action === 'join') state.pending.add(data.viewer_id);
      if (data.action === 'check_viewers') {
        const capture_id = state.trigger;
        state.trigger = null;
        return json({ ok: true, viewers: [...state.pending], relay_needed: Date.now() - state.lastRelay < 4000, capture_id });
      }
      if (data.action === 'send_offer') { state.offers[data.viewer_id] = data.offer; state.pending.delete(data.viewer_id); }
      if (data.action === 'get_offer') return json({ ok: !!state.offers[data.viewer_id], offer: state.offers[data.viewer_id] });
      if (data.action === 'send_answer' && !state.dropAnswers) state.answers[data.viewer_id] = data.answer;
      if (data.action === 'get_answer') return json({ ok: !!state.answers[data.viewer_id], answer: state.answers[data.viewer_id] });
      return json({ ok: true });
    }
    if (pathname.endsWith('/process')) {
      state.processes++;
      expect(request.postDataBuffer().includes(Buffer.from('photos[]'))).toBeTruthy();
      return state.processes === 1 ? json({ message: 'Retry test' }, 503) : json({ redirect: '/result/test' });
    }
    if (pathname === '/result/test') return route.fulfill({ contentType: 'text/html', body: 'Hasil foto' });
    if (pathname === '/queue/status') return json({ current_serving: 1, waiting_count: 0, active_ticket: null });
    return json({ ok: true });
  });
  return state;
}

async function captureAndRetry(page, state) {
  await expect(page.locator('#btn-main')).toHaveText('Mulai foto');
  await expect(page.locator('#btn-main')).toBeEnabled();
  await page.locator('#btn-main').click();
  await expect(page.locator('#btn-main')).toHaveText('Simpan & lihat hasil', { timeout: 15000 });
  await page.locator('#btn-main').click();
  await expect(page.locator('#btn-main')).toBeEnabled();
  await expect(page.locator('#btn-main')).toHaveText('Simpan & lihat hasil');
  await page.locator('#btn-main').click();
  await expect(page).toHaveURL(/\/result\/test$/);
  expect(state.processes).toBe(2);
  expect(state.errors).toEqual([]);
}

test('DSLR hub does not encode or upload JPEG previews without an HTTP viewer', async ({ context }) => {
  const state = await installServer(context);
  const page = await context.newPage();
  await page.goto('http://localhost/admin/camera-hub');
  await page.locator('#btn-toggle-broadcast').click();
  await expect.poll(() => state.frames).toBeGreaterThan(3);
  expect(state.pushes).toBe(0);
  expect(await page.evaluate(() => window.jpegEncodes)).toBe(0);
  state.lastRelay = Date.now();
  await expect.poll(() => state.pushes).toBeGreaterThan(1);
  state.lastRelay = 0;
  await page.waitForTimeout(1300);
  const uploads = state.pushes;
  await page.waitForTimeout(500);
  expect(state.pushes).toBe(uploads);
  expect(state.errors).toEqual([]);
});

test('direct DSLR capture keeps photos on upload failure and can retry', async ({ context }) => {
  const state = await installServer(context);
  const page = await context.newPage();
  await page.goto('http://localhost/test-photo/session/1');
  await expect(page.locator('#source-select')).toHaveValue('digicam');
  await captureAndRetry(page, state);
  expect(state.captures).toBe(1);
});

test('webcam capture and retry work without a DSLR', async ({ context }) => {
  const state = await installServer(context);
  const page = await context.newPage();
  await page.addInitScript(() => localStorage.setItem('pb_source', 'webcam'));
  await page.goto('http://localhost/test-photo/session/1');
  await captureAndRetry(page, state);
  expect(state.captures).toBe(0);
});

test('WebRTC hub keeps video alive with zero JPEG uploads and captures via DataChannel', async ({ context }) => {
  const state = await installServer(context);
  const host = await context.newPage();
  await host.goto('http://localhost/admin/camera-hub');
  await host.locator('#btn-toggle-broadcast').click();
  const booth = await context.newPage();
  await booth.addInitScript(() => localStorage.setItem('pb_source', 'hub'));
  await booth.goto('http://localhost/test-photo/session/1');
  await expect(host.locator('#stream-stats')).toContainText('P2P Aktif', { timeout: 15000 });
  await expect.poll(() => booth.locator('#cam-video').evaluate(video => video.videoWidth)).toBeGreaterThan(0);
  await booth.waitForTimeout(5200);
  const uploads = state.pushes;
  const time = await booth.locator('#cam-video').evaluate(video => video.currentTime);
  await booth.waitForTimeout(500);
  expect(state.pushes).toBe(uploads);
  expect(await booth.locator('#cam-video').evaluate(video => video.currentTime)).toBeGreaterThan(time);
  await captureAndRetry(booth, state);
  expect(state.captures).toBe(1);
  expect(state.snapshots).toBeLessThanOrEqual(2);
});

test('HTTP-only hub captures even if WebRTC is unavailable', async ({ context }) => {
  const state = await installServer(context);
  const host = await context.newPage();
  await host.goto('http://localhost/admin/camera-hub');
  await host.locator('#btn-toggle-broadcast').click();
  const booth = await context.newPage();
  await booth.addInitScript(() => { localStorage.setItem('pb_source', 'hub'); window.RTCPeerConnection = undefined; });
  await booth.goto('http://localhost/test-photo/session/1');
  await captureAndRetry(booth, state);
  expect(state.captures).toBe(1);
  expect(state.pushes).toBeGreaterThan(0);
});

test('HTTP preview survives an incomplete WebRTC handshake', async ({ context }) => {
  const state = await installServer(context);
  state.dropAnswers = true;
  const host = await context.newPage();
  await host.goto('http://localhost/admin/camera-hub');
  await host.locator('#btn-toggle-broadcast').click();
  const booth = await context.newPage();
  await booth.addInitScript(() => localStorage.setItem('pb_source', 'hub'));
  await booth.goto('http://localhost/test-photo/session/1');
  await expect.poll(() => Object.keys(state.offers).length).toBe(1);
  await booth.waitForTimeout(2000);
  const reads = state.relayReads;
  await expect.poll(() => state.relayReads).toBeGreaterThan(reads + 3);
  await captureAndRetry(booth, state);
  expect(state.captures).toBe(1);
});

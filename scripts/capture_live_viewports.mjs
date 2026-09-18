import fs from 'node:fs/promises';
import path from 'node:path';

const [, , debugPort, pageUrl, outputDir] = process.argv;
if (!debugPort || !pageUrl || !outputDir) {
  throw new Error('Usage: node capture_live_viewports.mjs <port> <url> <output-dir>');
}

const targets = await fetch(`http://127.0.0.1:${debugPort}/json`).then((response) => response.json());
const page = targets.find((target) => target.type === 'page');
if (!page) throw new Error('No debuggable page target found');

const socket = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
  socket.addEventListener('open', resolve, { once: true });
  socket.addEventListener('error', reject, { once: true });
});

let sequence = 0;
const pending = new Map();
const eventWaiters = new Map();

socket.addEventListener('message', (event) => {
  const message = JSON.parse(event.data);
  if (message.id && pending.has(message.id)) {
    const { resolve, reject } = pending.get(message.id);
    pending.delete(message.id);
    if (message.error) reject(new Error(message.error.message));
    else resolve(message.result || {});
    return;
  }

  const waiters = eventWaiters.get(message.method) || [];
  eventWaiters.delete(message.method);
  waiters.forEach((resolve) => resolve(message.params || {}));
});

function command(method, params = {}) {
  const id = ++sequence;
  socket.send(JSON.stringify({ id, method, params }));
  return new Promise((resolve, reject) => pending.set(id, { resolve, reject }));
}

function once(method) {
  return new Promise((resolve) => {
    const waiters = eventWaiters.get(method) || [];
    waiters.push(resolve);
    eventWaiters.set(method, waiters);
  });
}

await fs.mkdir(outputDir, { recursive: true });
await command('Page.enable');

const viewports = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 900 },
  { width: 1440, height: 1000 },
];

for (const viewport of viewports) {
  const mobile = viewport.width < 768;
  await command('Emulation.setDeviceMetricsOverride', {
    width: viewport.width,
    height: viewport.height,
    deviceScaleFactor: 1,
    mobile,
    screenWidth: viewport.width,
    screenHeight: viewport.height,
  });
  await command('Emulation.setTouchEmulationEnabled', { enabled: mobile });

  const loaded = once('Page.loadEventFired');
  await command('Page.navigate', { url: pageUrl });
  await loaded;
  await command('Runtime.evaluate', {
    expression: 'document.fonts && document.fonts.ready',
    awaitPromise: true,
  });
  await new Promise((resolve) => setTimeout(resolve, 1000));

  const result = await command('Page.captureScreenshot', {
    format: 'png',
    fromSurface: true,
    captureBeyondViewport: false,
  });
  const filename = `login-${viewport.width}.png`;
  await fs.writeFile(path.join(outputDir, filename), Buffer.from(result.data, 'base64'));
  process.stdout.write(`${filename} ${viewport.width}x${viewport.height}\n`);
}

socket.close();

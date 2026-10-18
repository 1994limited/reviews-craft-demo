// Screenshots of Dog-ear Books for Reviews for Craft's docs, after
// `php craft demo/content --fresh`.
//
//   node scripts/screenshots.mjs
//
// DEMO_URL     the demo's address (default http://reviews-craft-demo.test)
// SCREENSHOTS  where to write them (default ../reviews-craft/docs/images)
// CHROME       a Chrome or Chromium to drive (default: Google Chrome on macOS)
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import puppeteer from 'puppeteer';

const base = (process.env.DEMO_URL || 'http://reviews-craft-demo.test').replace(/\/$/, '');
const out = process.env.SCREENSHOTS || new URL('../../reviews-craft/docs/images', import.meta.url).pathname;
const chrome = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const settle = (ms = 600) => new Promise((resolve) => setTimeout(resolve, ms));

mkdirSync(out, { recursive: true });

const browser = await puppeteer.launch({ executablePath: chrome, args: ['--ignore-certificate-errors'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 2 });
await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }, { name: 'prefers-reduced-motion', value: 'reduce' }]);

// An element, cut off at `maxHeight` pixels.
const shot = async (selector, file, maxHeight = 2000) => {
    const box = await (await page.$(selector)).boundingBox();
    await page.screenshot({ path: `${out}/${file}`, clip: { x: box.x, y: box.y, width: box.width, height: Math.min(box.height, maxHeight) }, captureBeyondViewport: true });
    console.log(`${out}/${file}`);
};

// A book's reviews, and the form.
await page.goto(`${base}/books/notes-from-a-lighthouse`, { waitUntil: 'networkidle0' });
await settle();
await shot('.reviews-ui__columns', 'reviews.png', 820);
await shot('#write-a-review', 'form.png');

// The same, on a dark page: the styles follow the text colour.
await page.addStyleTag({ content: 'body { background: #1c1917 !important; color: #f5f5f4 !important; color-scheme: dark; }' });
await settle();
await shot('.reviews-ui__columns', 'reviews-dark.png', 820);

// A review imported from Trustpilot, showing where it came from.
await page.goto(`${base}/books/coral-arithmetic?sort=newest`, { waitUntil: 'networkidle0' });
const imported = await page.evaluateHandle(() => [...document.querySelectorAll('.reviews-ui__review')].find((el) => el.textContent.includes('From Trustpilot')));
await imported.asElement().screenshot({ path: `${out}/imported.png` });
console.log(`${out}/imported.png`);

// The control panel, signed in as the admin.
const link = execFileSync('php', ['craft', 'users/impersonate', 'admin'], { encoding: 'utf8' }).match(/https?:\/\/\S+/)[0];
await page.goto(link, { waitUntil: 'networkidle0' });

await page.goto(`${base}/admin/reviews`, { waitUntil: 'networkidle0' });
await settle(1200);
await page.screenshot({ path: `${out}/cp-reviews.png` });
console.log(`${out}/cp-reviews.png`);

const review = execFileSync('php', ['craft', 'demo/content/jo-review-id'], { encoding: 'utf8' }).trim();
await page.goto(`${base}/admin/reviews/${review}`, { waitUntil: 'networkidle0' });
await settle(1200);
await page.screenshot({ path: `${out}/cp-review.png` });
console.log(`${out}/cp-review.png`);

// Import: an export uploaded and previewed (scripts/sample-export.csv).
await page.goto(`${base}/admin/reviews/import`, { waitUntil: 'networkidle0' });
await (await page.$('input[type=file]')).uploadFile(new URL('./sample-export.csv', import.meta.url).pathname);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button.submit')]);
await settle(800);
await page.screenshot({ path: `${out}/cp-import.png` });
console.log(`${out}/cp-import.png`);

// Sync: the sources reviews are synced from.
await page.goto(`${base}/admin/reviews/sources`, { waitUntil: 'networkidle0' });
await settle(800);
await page.screenshot({ path: `${out}/cp-sync.png` });
console.log(`${out}/cp-sync.png`);

await page.goto(`${base}/admin/reviews/settings`, { waitUntil: 'networkidle0' });
await settle(1200);
await page.screenshot({ path: `${out}/settings.png` });
console.log(`${out}/settings.png`);

await browser.close();

const { chromium } = require('playwright-core');

const SHEET_URL = 'https://docs.google.com/spreadsheets/d/18ZrYDNYGFtVdqjNMPCq8AOIvXsMxSLsgjWBLU_B7B-k/edit?gid=1142984109#gid=1142984109';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage({ viewport: { width: 1800, height: 1100 } });
  await page.goto(SHEET_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(12000);
  const nameBox = page.locator('.waffle-name-box');
  await nameBox.click();
  await nameBox.fill('T27');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(4000);
  await page.mouse.move(1500, 900);
  await page.mouse.wheel(0, 700);
  await page.waitForTimeout(2500);

  const overlays = page.locator('.waffle-borderless-embedded-object-overlay:visible');
  const count = await overlays.count();
  const visible = [];
  for (let i = 0; i < count; i++) {
    const item = overlays.nth(i);
    visible.push({ i, style: await item.getAttribute('style'), box: await item.boundingBox() });
  }
  const target = overlays.filter({ has: page.locator('img') }).first();
  const targetBox = await target.boundingBox();
  if (!targetBox) throw new Error('T27 overlay is not visible');
  await page.mouse.click(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height / 2);
  await page.waitForTimeout(2000);

  const classes = await page.evaluate(() => {
    const results = [];
    for (const el of document.querySelectorAll('*')) {
      const cls = typeof el.className === 'string' ? el.className : '';
      if (!/(embedded|resize|handle|object-selection|object-overlay)/i.test(cls)) continue;
      const rect = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      results.push({
        tag: el.tagName,
        cls,
        aria: el.getAttribute('aria-label'),
        styleAttr: el.getAttribute('style'),
        rect: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
        display: style.display,
        visibility: style.visibility,
        cursor: style.cursor,
      });
    }
    return results.filter((r) => r.rect.width > 0 || r.rect.height > 0);
  });
  await page.screenshot({ path: 'D:\\git\\eform-api\\outputs\\evidence\\issue-tracker-20260803\\google-sheet-resize-selected.png' });
  console.log(JSON.stringify({ visible, targetBox, classes }, null, 2));
  process.exit(0);
})().catch((error) => {
  console.error(error.stack || error);
  process.exit(1);
});

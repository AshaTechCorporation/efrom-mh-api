const { chromium } = require('playwright-core');

const SHEET_URL = 'https://docs.google.com/spreadsheets/d/18ZrYDNYGFtVdqjNMPCq8AOIvXsMxSLsgjWBLU_B7B-k/edit?gid=1142984109#gid=1142984109';

const images = [
  { cell: 'U29', top: 5792, left: 3710, width: 144, height: 422 },
];

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage({ viewport: { width: 2600, height: 2200 } });
  await page.goto(SHEET_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(12000);

  const nameBox = page.locator('.waffle-name-box');
  await nameBox.click();
  await nameBox.fill('U27');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(4000);
  await page.mouse.move(1800, 1200);
  await page.mouse.wheel(400, 1600);
  await page.waitForTimeout(3000);

  for (const image of images) {
    const target = page.locator(
      `.waffle-borderless-embedded-object-overlay[style*="top: ${image.top}px"][style*="left: ${image.left}px"]:visible`,
    ).first();
    const before = await target.boundingBox();
    if (!before) throw new Error(`${image.cell}: image overlay is not visible`);

    await target.evaluate((element) => {
      element.dataset.codexOriginalZ = element.style.zIndex;
      element.style.zIndex = '9999';
    });
    await page.mouse.click(before.x + 20, before.y + 20);
    await page.waitForTimeout(800);

    const focused = page.locator(
      '.waffle-borderless-embedded-object-overlay-focused:visible',
    ).filter({
      has: page.locator('.docs-squarehandleselectionbox-handle[style*="se-resize"]'),
    }).last();
    const focusedBox = await focused.boundingBox();
    if (!focusedBox) throw new Error(`${image.cell}: resize selection was not focused`);
    const seHandle = focused.locator('.docs-squarehandleselectionbox-handle[style*="se-resize"]');
    const handleBox = await seHandle.boundingBox();
    if (!handleBox) throw new Error(`${image.cell}: southeast resize handle is unavailable`);

    const startX = handleBox.x + handleBox.width / 2;
    const startY = handleBox.y + handleBox.height / 2;
    const endX = focusedBox.x + image.width;
    const endY = focusedBox.y + image.height;
    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(endX, endY, { steps: 12 });
    await page.mouse.up();
    await page.waitForTimeout(1200);
    console.log(image.cell, 'before=', before, 'focusedAfterStyle=', await focused.getAttribute('style'));
    await page.keyboard.press('Escape');
    await page.waitForTimeout(2200);
  }

  await page.mouse.click(800, 900);
  await page.waitForTimeout(8000);
  const status = await page.evaluate(() => {
    return [...document.querySelectorAll('.waffle-borderless-embedded-object-overlay')]
      .filter((element) => getComputedStyle(element).display !== 'none')
      .map((element) => ({ style: element.getAttribute('style'), rect: element.getBoundingClientRect().toJSON() }));
  });
  await page.screenshot({
    path: 'D:\\git\\eform-api\\outputs\\evidence\\issue-tracker-20260803\\google-sheet-images-resized-144px.png',
    fullPage: false,
  });
  console.log(JSON.stringify(status, null, 2));
  await Promise.race([browser.close(), sleep(3000)]);
  process.exit(0);
})().catch((error) => {
  console.error(error.stack || error);
  process.exit(1);
});

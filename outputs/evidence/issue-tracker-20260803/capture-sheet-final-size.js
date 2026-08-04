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
  await nameBox.fill('U27');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(3500);
  await page.mouse.move(1400, 900);
  await page.mouse.wheel(250, 700);
  await page.waitForTimeout(3000);
  await page.mouse.click(500, 900);
  await page.waitForTimeout(1500);

  const sizes = await page.evaluate(() => [...document.querySelectorAll(
    '.waffle-borderless-embedded-object-overlay',
  )].filter((element) => getComputedStyle(element).display !== 'none')
    .map((element) => {
      const style = element.style;
      return {
        top: style.top,
        left: style.left,
        width: style.width,
        height: style.height,
      };
    })
    .filter((item) => ['5664px', '5728px', '5792px'].includes(item.top)));

  await page.screenshot({
    path: 'D:\\git\\eform-api\\outputs\\evidence\\issue-tracker-20260803\\google-sheet-final-resized-images.png',
    fullPage: false,
  });
  console.log(JSON.stringify(sizes, null, 2));
  process.exit(0);
})().catch((error) => {
  console.error(error.stack || error);
  process.exit(1);
});

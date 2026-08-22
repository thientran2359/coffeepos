import { chromium } from 'playwright';

const url = 'http://wootenberg.local/pos/cashier';
const results = [];

function record(name, pass, details = '') {
  results.push({ name, pass, details });
}

async function run() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('[data-screen="cashier"]', { timeout: 30000 });

    const categoryButtons = page.locator('[data-action="select-category"]');
    const categoryCount = await categoryButtons.count();
    if (categoryCount >= 2) {
      await categoryButtons.nth(1).click();
      await page.waitForTimeout(500);
      const active = await categoryButtons.nth(1).getAttribute('aria-pressed');
      record('category interaction', active === 'true', `aria-pressed=${active}`);
    } else {
      record('category interaction', false, 'Not enough category buttons rendered');
    }

    const searchInput = page.locator('[data-component="product-search"]');
    await searchInput.fill('latte');
    await page.waitForTimeout(500);
    const searchValue = await searchInput.inputValue();
    record('search input', searchValue === 'latte', `value=${searchValue}`);

    const productButtons = page.locator('[data-component="product-card"] [data-action="select-product"]:not([disabled])');
    const productCount = await productButtons.count();
    if (productCount > 0) {
      await productButtons.first().click();
      await page.waitForTimeout(700);
      const modalOpen = !(await page.locator('[data-component="modal"]').isHidden());
      const cartCountText = await page.locator('[data-component="cart-count"]').innerText();
      const countValue = Number.parseInt(cartCountText, 10) || 0;
      record('product selection preview', modalOpen || countValue > 0, `modalOpen=${modalOpen}, cartCount=${countValue}`);
    } else {
      record('product selection preview', false, 'No selectable product card');
    }

    const dineInButton = page.locator('[data-action="select-order-type"][data-order-type="dine_in"]');
    await dineInButton.click();
    await page.waitForTimeout(500);
    const dineInPressed = await dineInButton.getAttribute('aria-pressed');
    const tablePlaceholderHidden = await page.locator('[data-component="table-placeholder"]').isHidden();
    record('order type toggle', dineInPressed === 'true' && tablePlaceholderHidden === false, `pressed=${dineInPressed}, tableHidden=${tablePlaceholderHidden}`);

    await page.locator('[data-action="clear-cart"]').click();
    await page.waitForTimeout(300);
    const modal = page.locator('[data-component="modal"]');
    const clearModalOpen = !(await modal.isHidden());
    const modalTitle = await page.locator('[data-component="modal-title"]').innerText();
    record('clear-cart confirmation preview', clearModalOpen && /clear cart/i.test(modalTitle), `open=${clearModalOpen}, title=${modalTitle}`);

    await page.locator('[data-action="cancel-modal"]').first().click();
    await page.waitForTimeout(300);
    const modalClosed = await modal.isHidden();
    record('modal open/close', modalClosed, `closed=${modalClosed}`);

    const layout = page.locator('.coffeepos-cashier-layout');
    const desktopTemplate = await layout.evaluate((node) => window.getComputedStyle(node).gridTemplateColumns);
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.waitForTimeout(300);
    const tabletVisible = await page.locator('.coffeepos-cashier').isVisible();
    record('responsive desktop/tablet layout', desktopTemplate !== 'none' && tabletVisible, `desktopTemplate=${desktopTemplate}, tabletVisible=${tabletVisible}`);
  } finally {
    await browser.close();
  }
}

await run();

for (const result of results) {
  const status = result.pass ? 'PASS' : 'FAIL';
  console.log(`[${status}] ${result.name}${result.details ? ` - ${result.details}` : ''}`);
}

const hasFailure = results.some((result) => !result.pass);
process.exit(hasFailure ? 1 : 0);

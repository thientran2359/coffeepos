const { test, expect } = require('@playwright/test');

test('Phase-03 cashier browser verification', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto('http://wootenberg.local/pos/cashier', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('[data-screen="cashier"]', { timeout: 30000 });

  const categoryButtons = page.locator('[data-action="select-category"]');
  await expect(categoryButtons).toHaveCountGreaterThan(1);
  await categoryButtons.nth(1).click();
  await expect(categoryButtons.nth(1)).toHaveAttribute('aria-pressed', 'true');

  const searchInput = page.locator('[data-component="product-search"]');
  await searchInput.fill('latte');
  await expect(searchInput).toHaveValue('latte');

  const productButtons = page.locator('[data-component="product-card"] [data-action="select-product"]:not([disabled])');
  await expect(productButtons.first()).toBeVisible();
  await productButtons.first().click();
  await page.waitForTimeout(700);

  const modal = page.locator('[data-component="modal"]');
  const cartCount = Number.parseInt(await page.locator('[data-component="cart-count"]').innerText(), 10) || 0;
  const modalHidden = await modal.isHidden();
  expect(modalHidden === false || cartCount > 0).toBeTruthy();

  const dineInButton = page.locator('[data-action="select-order-type"][data-order-type="dine_in"]');
  await dineInButton.click();
  await expect(dineInButton).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-component="table-placeholder"]')).toBeVisible();

  await page.locator('[data-action="clear-cart"]').click();
  await expect(modal).toBeVisible();
  await expect(page.locator('[data-component="modal-title"]')).toContainText(/clear cart/i);

  await page.locator('[data-action="cancel-modal"]').first().click();
  await expect(modal).toBeHidden();

  const desktopTemplate = await page.locator('.coffeepos-cashier-layout').evaluate((node) => window.getComputedStyle(node).gridTemplateColumns);
  expect(desktopTemplate).not.toBe('none');

  await page.setViewportSize({ width: 1024, height: 768 });
  await expect(page.locator('.coffeepos-cashier')).toBeVisible();
});

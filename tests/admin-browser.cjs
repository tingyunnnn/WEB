/* Headless only. All input is from the isolated runner over stdin. */
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
  const chunks = [];
  for await (const chunk of process.stdin) chunks.push(chunk);
  const input = JSON.parse(Buffer.concat(chunks).toString());
  const report = {result: 'FAIL', widths: [], checks: [], screenshots: []};
  const options = {headless: true, args: ['--disable-background-networking', '--mute-audio']};
  if (fs.existsSync(input.browser)) options.executablePath = input.browser;
  const browser = await chromium.launch(options);
  report.browserVersion = browser.version();
  try {
    for (const width of [375, 1024, 1366]) {
      const context = await browser.newContext({viewport: {width, height: width === 375 ? 812 : 900}, reducedMotion: 'reduce'});
      const page = await context.newPage();
      page.setDefaultTimeout(10000);
      page.setDefaultNavigationTimeout(15000);
      const pageErrors = [];
      page.on('pageerror', error => pageErrors.push(error.message));
      const check = (name, value) => { assert.ok(value, `${width}: ${name}`); report.checks.push(`${width}: ${name}`); };
      async function screenshot(name) {
        await page.evaluate(() => document.fonts.ready);
        await page.locator('img').evaluateAll(images => Promise.all(images.map(image => {
          image.loading = 'eager';
          return image.decode().catch(() => {});
        })));
        const size = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth}));
        check(name + ' has no horizontal overflow', size.scroll <= size.width);
        const file = `${name}-${width}.png`;
        await page.screenshot({path: path.join(input.artifacts, file), fullPage: true});
        report.screenshots.push(file);
      }
      await page.goto(input.base + '/admin/login.php');
      await screenshot('login');
      await page.keyboard.press('Tab');
      check('keyboard skip link focus', await page.locator('.skip-link').evaluate(el => el === document.activeElement));
      await page.getByLabel('Username', {exact: true}).fill(input.username);
      await page.getByLabel('Password', {exact: true}).fill(input.password);
      await page.getByRole('button', {name: 'Sign In'}).click();
      await page.waitForURL('**/admin/foods/index.php');
      check('ten real seed foods rendered', await page.locator('.food-row').count() === 10);
      await screenshot('food-management');
      await page.goto(input.base + '/admin/orders/index.php');
      check('orders list renders', await page.locator('.order-card').count() === 3);
      await screenshot('orders');
      await page.goto(input.base + '/admin/orders/detail.php?id=' + input.orderId);
      check('order snapshot visible', await page.getByRole('heading', {name: 'Ocean Bowl (ordered)', exact: true}).count() === 1);
      check('order total visible', await page.locator('.grand-total').textContent().then(text => text.includes('NT$322')));
      await screenshot('order-detail');
      await page.goto(input.base + '/admin/foods/index.php');
      if (width === 375) {
        await page.getByRole('button', {name: 'Menu', exact: true}).click();
        check('mobile navigation opens', await page.getByRole('button', {name: 'Logout', exact: true}).isVisible());
        await page.keyboard.press('Escape');
        check('mobile navigation closes on Escape', !(await page.getByRole('button', {name: 'Logout', exact: true}).isVisible()));
      }
      await page.getByLabel('Search food', {exact: true}).fill('Mango');
      await page.getByRole('button', {name: 'Apply Filters'}).click();
      check('search returns matching food', await page.locator('.food-row').count() === 1 && (await page.locator('.food-row h2').textContent()).includes('Mango'));
      await page.getByRole('link', {name: 'Reset', exact: true}).click();
      await page.getByRole('link', {name: 'Add Food Item', exact: true}).click();
      await screenshot('add-food');
      await page.getByLabel('Food Name', {exact: false}).fill(`Browser ocean bowl ${width}`);
      await page.getByLabel('Description', {exact: false}).fill('A temporary item for responsive workflow verification.');
      await page.getByLabel('Price (NT$)', {exact: false}).fill('120');
      await page.getByLabel('Category', {exact: false}).selectOption('desserts');
      await page.locator('#image').setInputFiles(input.image);
      await page.getByLabel('Image Alt Text', {exact: false}).fill('Mango snow ice');
      await page.getByLabel('Display Order', {exact: false}).fill('11');
      await page.waitForFunction(() => document.getElementById('preview-label').textContent === 'New image preview');
      check('image preview uses object URL', (await page.locator('#image-preview').getAttribute('src')).startsWith('blob:'));
      await page.getByRole('button', {name: 'Save Food Item', exact: true}).click();
      await page.waitForURL('**/admin/foods/index.php');
      let row = page.locator('.food-row').filter({hasText: `Browser ocean bowl ${width}`});
      check('new food saved from responsive form', await row.count() === 1);
      await row.getByRole('link', {name: `Edit Browser ocean bowl ${width}`, exact: true}).click();
      await screenshot('edit-food');
      await page.getByLabel('Food Name', {exact: false}).fill(`Updated ocean bowl ${width}`);
      await page.getByLabel('Availability Status', {exact: false}).selectOption('0');
      await page.getByRole('button', {name: 'Save Changes', exact: true}).click();
      await page.waitForURL('**/admin/foods/index.php');
      row = page.locator('.food-row').filter({hasText: `Updated ocean bowl ${width}`});
      check('edit persists with retained image', await row.getByText('Inactive', {exact: true}).count() === 1);
      await row.getByRole('button', {name: `Activate Updated ocean bowl ${width}`, exact: true}).click();
      await page.waitForURL('**/admin/foods/index.php');
      check('activation works', await row.getByText('Active', {exact: true}).count() === 1);
      await row.getByRole('button', {name: `Deactivate Updated ocean bowl ${width}`, exact: true}).click();
      await page.waitForURL('**/admin/foods/index.php');
      check('deactivation works', await row.getByText('Inactive', {exact: true}).count() === 1);
      await row.getByRole('link', {name: `Delete Updated ocean bowl ${width}`, exact: true}).click();
      await screenshot('delete-food');
      await page.getByRole('checkbox').check();
      await page.getByRole('button', {name: 'Delete Food Item', exact: true}).click();
      await page.waitForURL('**/admin/foods/index.php');
      check('delete works', await page.locator('.food-row').count() === 10);
      const storage = await context.storageState();
      if (width === 375) {
        const nojs = await browser.newContext({javaScriptEnabled: false, viewport: {width, height: 812}, storageState: storage});
        const plain = await nojs.newPage();
        await plain.goto(input.base + '/admin/foods/index.php');
        check('navigation works without JavaScript', await plain.getByRole('button', {name: 'Logout', exact: true}).isVisible());
        await nojs.close();
        await page.getByRole('button', {name: 'Menu', exact: true}).click();
      }
      await page.getByRole('button', {name: 'Logout', exact: true}).click();
      await page.waitForURL('**/admin/login.php?logged_out=1');
      check('logout completes', await page.getByText('You have been signed out.', {exact: true}).isVisible());
      check('no browser runtime errors', pageErrors.length === 0);
      const homepage = await context.newPage();
      await homepage.goto(input.base + '/index.html');
      const adminLink = homepage.locator('footer a', {hasText: 'Admin Login'});
      check('footer admin relative link', await adminLink.getAttribute('href') === 'admin/login.php');
      check('footer link stays inside viewport', await adminLink.evaluate(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; }));
      check('footer link supports subdirectory resolution', await adminLink.evaluate(el => new URL(el.getAttribute('href'), 'https://example.test/tidebites/index.html').pathname === '/tidebites/admin/login.php'));
      await homepage.goto(await adminLink.getAttribute('href').then(href => new URL(href, homepage.url()).href));
      check('footer destination loads login', await homepage.getByRole('button', {name: 'Sign In'}).isVisible());
      await homepage.close();
      report.widths.push(width);
      await context.close();
    }
    report.result = 'PASS';
  } catch (error) {
    report.error = error.message;
  } finally { await browser.close(); }
  fs.writeFileSync(path.join(input.artifacts, 'browser-report.json'), JSON.stringify(report, null, 2));
  process.stdout.write(JSON.stringify(report));
})().catch(() => { process.stderr.write('Headless browser setup failed.'); process.exitCode = 1; });

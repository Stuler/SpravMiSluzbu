// UI regression test against the built frontend; API and form responses are mocked.
// Requires Node.js, Playwright and Chrome: node tests/Cases/Frontend/ProviderSignUpTest.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../../..');
const html = '<!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/bundle/front.bundle.css"></head><body><header id="header" class="sticky-top"><nav id="navmenu"><i class="mobile-nav-toggle"></i></nav></header><a class="scroll-top"></a><div class="bripeon-provider-form-panel"><form method="post" id="providerRegistrationForm" action="/provider-sign/up?do=registrationForm-submit"><div id="providerSignUpForm"></div><button type="submit" name="submit" value="register" id="providerRegistrationSubmit" class="bripeon-hidden-submit" aria-hidden="true" tabindex="-1"></button><input type="hidden" name="payload"><input type="hidden" name="_token_" value="test-csrf"></form></div><script src="/assets_front/vendor/aos/aos.js"></script><script src="/assets_front/vendor/glightbox/js/glightbox.min.js"></script><script src="/assets_front/vendor/purecounter/purecounter_vanilla.js"></script><script src="/bundle/front.bundle.js"></script></body></html>';

(async () => {
	const browser = await chromium.launch({channel: 'chrome', headless: true});
	try {
		const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
		page.setDefaultTimeout(10000);
		await page.addInitScript(() => {
			window.reviewCommits = 0;
			window.__REACT_DEVTOOLS_GLOBAL_HOOK__ = {
				supportsFiber: true, inject() { return 1; },
				onCommitFiberRoot() { window.reviewCommits++; }, onCommitFiberUnmount() {},
			};
		});
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		const posts = [];
		let responseMode = 'duplicate';
		await page.route('**/*', async route => {
			const request = route.request();
			const url = new URL(request.url());
			if (url.hostname !== 'provider-review.local') return route.abort();
			if (url.pathname === '/bundle/front.bundle.js' || url.pathname.startsWith('/assets_front/vendor/')) {
				return route.fulfill({contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'www', url.pathname))});
			}
			if (url.pathname === '/bundle/front.bundle.css') return route.fulfill({contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www', url.pathname))});
			if (url.pathname === '/api/categories') return route.fulfill({json: [{label: 'Domacnost', options: [{value: 5, label: 'Malovanie'}]}]});
			if (url.pathname === '/api/regions') return route.fulfill({json: [{id: 1, name: 'Bratislavsky kraj'}]});
			if (url.pathname === '/api/cities') return route.fulfill({json: [
				{id: 1, name: 'Prva obec', zip: '81101'}, {id: 2, name: 'Druha obec', zip: '81101'}, {id: 3, name: 'Jedina obec', zip: '01001'},
			]});
			if (request.method() === 'POST') {
				assert.equal(request.headers()['x-requested-with'], 'XMLHttpRequest');
				assert.match(request.postData(), /test-csrf/);
				const match = request.postData().match(/name="payload"\r\n\r\n([^\r]+)/);
				assert.ok(match, 'A serialized registration payload must be submitted');
				posts.push(JSON.parse(match[1]));
				if (responseMode === 'network') return route.abort();
				if (responseMode === 'success') return route.fulfill({json: {redirect: '/provider-sign/registered'}});
				return route.fulfill({status: 422, json: {errors: ['Poskytovatel s touto adresou uz existuje.']}});
			}
			if (url.pathname === '/provider-sign/registered') return route.fulfill({body: '<h1>Registration complete</h1>'});
			return route.fulfill({contentType: 'text/html', body: html});
		});
		const next = page.locator('.bripeon-wizard-actions .bripeon-primary-button');
		const back = page.locator('.bripeon-wizard-actions .bripeon-secondary-button');
		const waitValid = () => page.waitForFunction(() => !document.querySelector('.bripeon-wizard-actions .bripeon-primary-button').disabled);
		const assertIdle = async () => {
			await page.waitForTimeout(100);
			const before = await page.evaluate(() => window.reviewCommits);
			await page.waitForTimeout(500);
			assert.equal(await page.evaluate(() => window.reviewCommits), before, 'An idle step must not re-render');
		};
		await page.goto('http://provider-review.local/provider-sign/up');
		await page.getByLabel('Aké služby ponúkate?').fill('Malovanie');
		await page.getByRole('option', {name: 'Malovanie', exact: true}).click();
		await page.getByLabel('Kde pôsobíte?').fill('Bratislavsky');
		await page.getByRole('option', {name: 'Bratislavsky kraj', exact: true}).click();
		await next.click();
		await page.getByLabel('Meno', {exact: true}).fill('Janko');
		await page.getByLabel('Priezvisko', {exact: true}).fill('Hrasko');
		await page.getByLabel('Email', {exact: true}).fill('qa@example.test');
		await page.getByLabel('Heslo', {exact: true}).fill('testPass123');
		await page.getByLabel('Potvrďte heslo', {exact: true}).fill('testPass123');
		await page.getByLabel('Telefónne číslo').fill('+421 905 123 456');
		await waitValid();
		assert.equal(await page.locator('form form').count(), 0);
		assert.equal(await page.getByLabel('Telefónne číslo').getAttribute('type'), 'tel');
		assert.equal(await page.getByLabel('Heslo', {exact: true}).getAttribute('autocomplete'), 'new-password');
		await assertIdle();
		await page.getByLabel('Telefónne číslo').fill('invalid');
		await page.waitForFunction(() => document.querySelector('input[name="phone"]').getAttribute('aria-invalid') === 'true');
		assert.equal(await next.isDisabled(), true);
		assert.match(await page.getByLabel('Telefónne číslo').getAttribute('aria-describedby'), /provider-phone-error/);
		await page.getByLabel('Telefónne číslo').fill('02 1234 5678');
		await waitValid();
		await page.getByLabel('Telefónne číslo').press('Enter');
		await page.getByLabel('Názov firmy').waitFor();
		assert.equal(posts.length, 0, 'Navigation must not submit a registration');
		await page.getByLabel('Názov firmy').fill('QA Firma');
		await page.getByLabel('Ulica', {exact: true}).fill('Hlavna');
		await page.getByLabel('Číslo', {exact: true}).fill('12');
		await page.getByLabel('PSČ').fill('81101');
		assert.equal(await page.locator('.react-select__single-value').count(), 0, 'An ambiguous postcode must not select a city');
		await page.getByLabel('PSČ').fill('01001');
		assert.equal(await page.locator('.react-select__single-value').innerText(), 'Jedina obec');
		await page.getByLabel('Mesto', {exact: true}).fill('Druha');
		await page.getByRole('option', {name: 'Druha obec', exact: true}).click();
		await page.getByLabel('PSČ').fill('8110');
		await page.getByLabel('PSČ').fill('81101');
		assert.equal(await page.locator('.react-select__single-value').innerText(), 'Druha obec');
		await page.getByLabel('Použiť údaje z kroku 2').uncheck();
		await page.waitForFunction(() => document.querySelector('.bripeon-wizard-actions .bripeon-primary-button').disabled);
		await page.getByLabel('Meno', {exact: true}).fill('Fero');
		await page.getByLabel('Priezvisko', {exact: true}).fill('Mrkva');
		await page.getByLabel('Použiť údaje z kroku 2').check();
		await back.click();
		assert.equal(await page.getByLabel('Email', {exact: true}).inputValue(), 'qa@example.test');
		await next.click();
		assert.equal(await page.getByLabel('Názov firmy').inputValue(), 'QA Firma');
		assert.equal(posts.length, 0);
		await waitValid();
		await assertIdle();
		await next.click();
		await page.locator('.bripeon-form-error').waitFor();
		assert.equal(posts.length, 1);
		assert.equal(posts[0].phone, '+421212345678');
		assert.equal(posts[0].cityId, '2');
		assert.equal(await page.getByLabel('Názov firmy').inputValue(), 'QA Firma');
		assert.equal(await next.isDisabled(), false);
		assert.equal(await page.locator('.bripeon-form-error').evaluate(el => el === document.activeElement), true);
		responseMode = 'network';
		await next.click();
		await page.waitForFunction(() => document.querySelector('.bripeon-form-error').textContent.includes('Skontrolujte pripojenie'));
		assert.equal(await page.getByLabel('Názov firmy').inputValue(), 'QA Firma');
		await page.setViewportSize({width: 390, height: 844});
		assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
		await back.click();
		assert.equal(await page.getByLabel('Heslo', {exact: true}).inputValue(), 'testPass123', 'Errors must preserve credentials in memory');
		await next.click();
		await waitValid();
		responseMode = 'success';
		await next.evaluate(button => {button.click(); button.click();});
		await page.waitForURL('**/provider-sign/registered');
		assert.equal(posts.length, 3, 'A rapid double click must issue only one request');
		assert.deepEqual(errors, []);
		console.log('PASS: navigation, validation, idle renders, postcode, labels, error recovery, duplicate-submit prevention and mobile layout');
	} finally {
		await browser.close();
	}
})().catch(error => {console.error(error); process.exitCode = 1;});

import netteForms from 'nette-forms';

/**
 * Splits the server-rendered demand form into three steps. Without JavaScript,
 * or if the expected controls are missing, the form stays a single page and the
 * server-side validation remains the source of truth.
 */
const STEPS = [
	['categoryId', 'cityId'],
	['description', 'requestedTerm'],
	['contactName', 'contactEmail', 'contactPhone'],
];

function init() {
	const form = document.getElementById('frm-demandForm');
	const stepper = document.querySelector('[data-demand-stepper]');
	if (!form || !stepper) {
		return;
	}

	const rows = STEPS.map((names) => names.map((name) => {
		const control = form.elements.namedItem(name);
		return control ? control.closest('tr') : null;
	}));
	const submitRow = form.querySelector('input[type="submit"], button[type="submit"]')?.closest('tr');
	const tbody = submitRow?.parentElement;
	if (!tbody || rows.some((group) => group.some((row) => !row || row.parentElement !== tbody))) {
		return;
	}

	rows.forEach((group) => group.forEach((row) => tbody.appendChild(row)));
	tbody.appendChild(submitRow);

	const total = STEPS.length;
	const items = Array.from(stepper.querySelectorAll('li'));
	const nav = document.createElement('div');
	nav.className = 'bripeon-wizard-nav';
	const back = document.createElement('button');
	back.type = 'button';
	back.className = 'bripeon-button bripeon-secondary-button';
	back.textContent = 'Späť';
	const next = document.createElement('button');
	next.type = 'button';
	next.className = 'bripeon-button bripeon-button-primary';
	next.textContent = 'Pokračovať';
	nav.append(back, next);
	tbody.closest('table').after(nav);

	let step = 1;
	const firstErrorStep = rows.findIndex((group) => group.some((row) => row.querySelector('.error')));
	if (firstErrorStep >= 0) {
		step = firstErrorStep + 1;
	}

	function render() {
		rows.forEach((group, index) => group.forEach((row) => { row.hidden = index + 1 !== step; }));
		submitRow.hidden = step !== total;
		next.hidden = step === total;
		back.hidden = step === 1;
		nav.style.justifyContent = step === 1 ? 'flex-end' : 'space-between';
		items.forEach((item, index) => {
			item.className = index + 1 < step ? 'is-done' : (index + 1 === step ? 'is-current' : '');
			if (index + 1 === step) {
				item.setAttribute('aria-current', 'step');
			} else {
				item.removeAttribute('aria-current');
			}
		});
	}

	function clearErrors(row) {
		row.querySelectorAll('.bripeon-wizard-error').forEach((node) => node.remove());
	}

	/** Validates the visible step with the Nette rules and shows messages inline. */
	function validateStep() {
		let firstInvalid = null;
		rows[step - 1].forEach((row) => {
			clearErrors(row);
			row.querySelectorAll('[data-nette-rules]').forEach((control) => {
				if (control.disabled) {
					return;
				}
				netteForms.formErrors = [];
				if (netteForms.validateControl(control)) {
					return;
				}
				const message = netteForms.formErrors[0]?.message || 'Skontrolujte toto pole.';
				const error = document.createElement('span');
				error.className = 'error bripeon-wizard-error';
				error.setAttribute('role', 'alert');
				error.textContent = message;
				control.closest('td').appendChild(error);
				firstInvalid ??= control;
			});
		});
		netteForms.formErrors = [];
		firstInvalid?.focus();
		return firstInvalid === null;
	}

	function go(target) {
		step = target;
		render();
		stepper.scrollIntoView({block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
	}

	next.addEventListener('click', () => {
		if (validateStep()) {
			go(step + 1);
		}
	});
	back.addEventListener('click', () => go(step - 1));

	form.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' && step < total && event.target.tagName !== 'TEXTAREA') {
			event.preventDefault();
			next.click();
		}
	});
	form.addEventListener('input', (event) => {
		event.target.closest('tr')?.querySelectorAll('.bripeon-wizard-error').forEach((node) => node.remove());
	});

	stepper.hidden = false;
	form.classList.add('bripeon-wizard-ready');
	render();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}

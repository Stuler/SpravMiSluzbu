(function () {
	"use strict";

	const toggle = document.querySelector('.bripeon-menu-toggle');
	const nav = document.querySelector('#navmenu');
	const account = document.querySelector('.bripeon-account');
	const accountToggle = document.querySelector('.bripeon-account-toggle');
	const desktop = window.matchMedia('(min-width: 992px)');

	function setMenu(open) {
		document.body.classList.toggle('bripeon-menu-open', open);
		if (toggle) {
			toggle.setAttribute('aria-expanded', String(open));
			toggle.setAttribute('aria-label', open ? 'Zavrieť menu' : 'Otvoriť menu');
		}
	}

	function setAccount(open) {
		if (account && accountToggle) {
			account.classList.toggle('is-open', open);
			accountToggle.setAttribute('aria-expanded', String(open));
		}
	}

	if (toggle && nav) {
		toggle.addEventListener('click', () => setMenu(!document.body.classList.contains('bripeon-menu-open')));
		nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
	}

	if (accountToggle) {
		accountToggle.addEventListener('click', () => setAccount(!account.classList.contains('is-open')));
	}

	document.addEventListener('click', (event) => {
		if (account && !account.contains(event.target)) {
			setAccount(false);
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			setAccount(false);
			setMenu(false);
		}
	});

	desktop.addEventListener('change', () => setMenu(false));
})();

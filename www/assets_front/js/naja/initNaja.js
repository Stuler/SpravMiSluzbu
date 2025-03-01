import naja from 'naja';
import netteForms from 'nette-forms';

naja.formsHandler.netteForms = netteForms;

document.addEventListener('DOMContentLoaded', function () {
	naja.registerExtension(new ModalExtension());
	naja.initialize({
		timeout: 70000, // maximální délka requestu
		history: false
	});
});

const modalExtension = {
	initialize(naja) {
		naja.snippetHandler.addEventListener('afterUpdate', (event) => {
			const {snippet} = event.detail;
			if (snippet.classList.contains('modal')) {
				$(snippet).modal('show');
			}
		});
	}
}

naja.registerExtension(modalExtension);

class ModalExtension {
	initialize(naja) {
		naja.addEventListener('complete', this.openModal.bind(this));
	}

	openModal(event) {
		let payload = event.detail.payload;

		if (payload === null || !payload.hasOwnProperty('modalId')) {
			return;
		}
		let modalId = payload.modalId;
		let showModal = payload.showModal;
		if (showModal === undefined || showModal === false) {
			return;
		}
		$("#" + modalId).modal('show');
	}
}


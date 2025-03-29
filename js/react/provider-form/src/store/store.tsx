import {makeAutoObservable} from "mobx";

class ProviderFormStore {
	step = 0;

	formData = {
		companyName: '',
		email: '',
		phone: '',
		address: '',
	};

	constructor() {
		makeAutoObservable(this);
	}

	nextStep() {
		this.step = Math.min(this.step + 1, 3);
	}

	prevStep() {
		this.step = Math.max(this.step - 1, 0);
	}

	updateField(field: string, value: string) {
		this.formData[field] = value;
	}

	submitForm() {
		console.log("Submitting form with data:", this.formData);
		// Add submission logic here
	}
}

export const providerFormStore = new ProviderFormStore();

export const store = {
	providerFormStore,
};

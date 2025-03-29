import * as yup from 'yup';

export const step3Schema = yup.object({
	companyName: yup.string().required('Názov firmy je povinný'),
	ico: yup
		.string()
		.required('IČO je povinné')
		.matches(/^\d{8}$/, 'IČO musí obsahovať 8 číslic'),

	street: yup.string().required('Ulica je povinná'),
	streetNumber: yup.string().required('Číslo ulice je povinné'),
	city: yup.string().required('Mesto je povinné'),
	zip: yup
		.string()
		.required('PSČ je povinné')
		.matches(/^\d{3}\s?\d{2}$/, 'Zadajte platné PSČ (napr. 010 01 alebo 01001)'),

	usePersonalAsContact: yup.boolean(),

	contactFirstName: yup
		.string()
		.when('usePersonalAsContact', {
			is: false,
			then: (schema) => schema.required('Meno kontaktnej osoby je povinné'),
		}),

	contactLastName: yup
		.string()
		.when('usePersonalAsContact', {
			is: false,
			then: (schema) => schema.required('Priezvisko kontaktnej osoby je povinné'),
		}),
});

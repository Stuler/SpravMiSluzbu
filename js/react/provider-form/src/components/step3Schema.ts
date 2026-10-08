import * as yup from 'yup';

export const step3Schema = yup.object({
	companyName: yup.string().required('Názov firmy je povinný'),
	ico: yup.string().optional(),
	street: yup.string().required('Ulica je povinná'),
	streetNumber: yup.string().required('Číslo je povinné'),
	cityId: yup.string().required('Mesto je povinné'),
	zip: yup.string().required('PSČ je povinné'),
	usePersonalAsContact: yup.boolean(),
	contactFirstName: yup.string().when('usePersonalAsContact', {
		is: false,
		then: (schema) => schema.required('Meno je povinné'),
		otherwise: (schema) => schema.optional(),
	}),
	contactLastName: yup.string().when('usePersonalAsContact', {
		is: false,
		then: (schema) => schema.required('Priezvisko je povinné'),
		otherwise: (schema) => schema.optional(),
	}),
});

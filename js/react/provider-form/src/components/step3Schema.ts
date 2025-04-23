import * as yup from 'yup';

export const step3Schema = yup.object().shape({
	companyName: yup.string().required('Názov firmy je povinný'),
	ico: yup.string().required('IČO je povinné'),
	street: yup.string().required('Ulica je povinná'),
	streetNumber: yup.string().required('Číslo ulice je povinné'),
	cityId: yup.string().required('Mesto je povinné'),
	zip: yup.string().required('PSČ je povinné'),
	usePersonalAsContact: yup.boolean(),
	contactFirstName: yup.string().when('usePersonalAsContact', {
		is: false,
		then: yup.string().required('Meno je povinné'),
		otherwise: yup.string(),
	}),
	contactLastName: yup.string().when('usePersonalAsContact', {
		is: false,
		then: yup.string().required('Priezvisko je povinné'),
		otherwise: yup.string(),
	}),
});

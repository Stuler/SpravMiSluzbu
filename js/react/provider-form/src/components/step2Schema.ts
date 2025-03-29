import * as yup from 'yup';

export const step2Schema = yup.object({
	firstName: yup.string().required('Meno je povinné'),
	lastName: yup.string().required('Priezvisko je povinné'),
	email: yup.string().email('Neplatný email').required('Email je povinný'),
	password: yup.string().min(6, 'Minimálne 6 znakov').required('Heslo je povinné'),
	confirmPassword: yup
		.string()
		.oneOf([yup.ref('password')], 'Heslá sa musia zhodovať')
		.required('Zopakujte heslo'),
	phone: yup
		.string()
		.required('Telefón je povinný')
		.matches(/^(\+421|0)[9]\d{8}$/, 'Zadajte platné slovenské telefónne číslo'),
});

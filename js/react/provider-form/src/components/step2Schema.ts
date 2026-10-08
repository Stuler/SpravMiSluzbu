import * as yup from 'yup';

export const normalizePhone = (value: string): string => value
	.replace(/[\s()-]/g, '')
	.replace(/^00421/, '+421')
	.replace(/^0(?=[1-9])/, '+421');

export const step2Schema = yup.object({
	firstName: yup.string().required('Meno je povinné'),
	lastName: yup.string().required('Priezvisko je povinné'),
	email: yup.string().email('Neplatný email').required('Email je povinný'),
	password: yup.string().min(8, 'Minimálne 8 znakov').required('Heslo je povinné'),
	confirmPassword: yup
		.string()
		.oneOf([yup.ref('password')], 'Heslá sa musia zhodovať')
		.required('Zopakujte heslo'),
	phone: yup
		.string()
		.transform((value) => normalizePhone(value))
		.required('Telefón je povinný')
		.matches(/^\+421[1-9]\d{8}$/, 'Zadajte platné slovenské mobilné číslo alebo pevnú linku'),
});

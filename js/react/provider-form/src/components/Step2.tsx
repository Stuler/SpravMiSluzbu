import React, {useEffect} from 'react';
import {useForm} from 'react-hook-form';
import {yupResolver} from '@hookform/resolvers/yup';
import {step2Schema} from './step2Schema';

type FormData = {
	firstName: string;
	lastName: string;
	email: string;
	password: string;
	confirmPassword: string;
	phone: string;
};

const labels: Record<string, string> = {
	firstName: 'Meno',
	lastName: 'Priezvisko',
	email: 'Email',
	password: 'Heslo',
	confirmPassword: 'Potvrďte heslo',
	phone: 'Telefónne číslo',
};

const fields: (keyof FormData)[] = ['firstName', 'lastName', 'email', 'password', 'confirmPassword', 'phone'];
const autocomplete: Record<keyof FormData, string> = {
	firstName: 'given-name',
	lastName: 'family-name',
	email: 'email',
	password: 'new-password',
	confirmPassword: 'new-password',
	phone: 'tel',
};

type Props = {
	data: FormData;
	onChange: (field: keyof FormData, value: string) => void;
	onStepValid: (isValid: boolean) => void;
};

const Step2: React.FC<Props> = ({data, onChange, onStepValid}) => {
	const {
		register,
		watch,
		formState: {errors, isValid},
	} = useForm<FormData>({
		mode: 'onChange',
		resolver: yupResolver(step2Schema),
		defaultValues: data,
	});

	useEffect(() => {
		onStepValid(isValid);
	}, [isValid, onStepValid]);

	useEffect(() => {
		const subscription = watch((values, {name}) => {
			if (name && fields.includes(name) && typeof values[name] === 'string') {
				onChange(name, values[name]);
			}
		});
		return () => subscription.unsubscribe();
	}, [watch, onChange]);

	return (
		<div className="bripeon-form-step">
			{fields.map((field) => (
				<div key={field}>
					<label htmlFor={`provider-${field}`}>{labels[field]}</label>
					<input
						id={`provider-${field}`}
						autoComplete={autocomplete[field]}
						aria-invalid={Boolean(errors[field])}
						aria-describedby={[
							errors[field] ? `provider-${field}-error` : '',
							field === 'phone' || field === 'password' ? `provider-${field}-help` : '',
						].filter(Boolean).join(' ') || undefined}
						type={
							field === 'password' || field === 'confirmPassword'
								? 'password'
								: field === 'email'
									? 'email'
									: field === 'phone' ? 'tel' : 'text'
						}
						{...register(field)}
					/>
					{field === 'phone' && <p id="provider-phone-help" className="bripeon-form-help">Mobil alebo pevná linka, napr. +421 905 123 456 alebo 02 1234 5678.</p>}
					{field === 'password' && <p id="provider-password-help" className="bripeon-form-help">Použite aspoň 8 znakov.</p>}
					{errors[field] && (
						<p id={`provider-${field}-error`} className="bripeon-field-error" role="alert">{errors[field]?.message}</p>
					)}
				</div>
			))}
		</div>
	);
};

export default Step2;

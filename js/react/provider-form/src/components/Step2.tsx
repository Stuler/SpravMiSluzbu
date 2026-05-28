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

	const values = watch();

	// Sync all fields with parent formData state
	useEffect(() => {
		Object.entries(values).forEach(([field, value]) =>
			onChange(field as keyof FormData, value)
		);
	}, [values, onChange]);

	return (
		<form className="bripeon-form-step" onSubmit={(e) => e.preventDefault()}>
			{['firstName', 'lastName', 'email', 'password', 'confirmPassword', 'phone'].map((field) => (
				<div key={field}>
					<label>{labels[field]}</label>
					<input
						type={
							field === 'password' || field === 'confirmPassword'
								? 'password'
								: field === 'email'
									? 'email'
									: 'text'
						}
						{...register(field as keyof FormData)}
					/>
					{errors[field as keyof FormData] && (
						<p className="bripeon-field-error">{errors[field as keyof FormData]?.message}</p>
					)}
				</div>
			))}
		</form>
	);
};

export default Step2;

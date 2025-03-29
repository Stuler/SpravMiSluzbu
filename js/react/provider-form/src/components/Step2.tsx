import React, {useEffect} from 'react';
import {useForm} from 'react-hook-form';
import {yupResolver} from '@hookform/resolvers/yup';
import {step2Schema} from './step2Schema';
import {yup} from 'yup';

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
		handleSubmit,
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
		<form className="space-y-4" onSubmit={(e) => e.preventDefault()}>
			{['firstName', 'lastName', 'email', 'password', 'confirmPassword', 'phone'].map((field) => (
				<div key={field}>
					<label className="block font-medium mb-1">{labels[field]}</label>
					<input
						type={field.includes('password') ? 'password' : 'text'}
						{...register(field as keyof FormData)}
						className="w-full border px-3 py-2 rounded"
					/>
					{errors[field as keyof FormData] && (
						<p className="text-red-500 text-sm">{errors[field as keyof FormData]?.message}</p>
					)}
				</div>
			))}
		</form>
	);
};

export default Step2;

import React, {useEffect, useState} from 'react';
import {useForm} from 'react-hook-form';
import {yupResolver} from '@hookform/resolvers/yup';
import Select from 'react-select';
import {step3Schema} from './step3Schema';

type CityOption = { id: string; name: string; zip: string };

type FormData = {
	companyName: string;
	ico: string;
	street: string;
	streetNumber: string;
	cityId: string;
	city: string;
	zip: string;
	usePersonalAsContact: boolean;
	contactFirstName: string;
	contactLastName: string;
};

type Props = {
	data: FormData;
	onChange: (field: keyof FormData, value: string | boolean) => void;
	onStepValid: (isValid: boolean) => void;
};

const Step3: React.FC<Props> = ({data, onChange, onStepValid}) => {
	const [cities, setCities] = useState<CityOption[]>([]);

	const {
		register,
		setValue,
		watch,
		formState: {errors, isValid},
	} = useForm<FormData>({
		mode: 'onChange',
		resolver: yupResolver(step3Schema) as any,
		defaultValues: data,
	});

	const values = watch();

	useEffect(() => {
		fetch('/api/cities')
			.then((res) => res.json())
			.then((data) => {
				const formatted = data.map((item: any) => ({
					id: String(item.id),
					name: item.name,
					zip: item.zip || '',
				}));
				setCities(formatted);
			});
	}, []);

	useEffect(() => {
		onStepValid(isValid);
	}, [isValid, onStepValid]);

	useEffect(() => {
		const subscription = watch((values, {name}) => {
			if (name && values[name] !== undefined) {
				onChange(name, values[name] as string | boolean);
			}
		});
		return () => subscription.unsubscribe();
	}, [watch, onChange]);

	const handleZipChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const zip = e.target.value;
		setValue('zip', zip, {shouldValidate: true});

		const matches = cities.filter((city) => city.zip.replace(/\s/g, '') === zip.replace(/\s/g, ''));
		if (!values.cityId && matches.length === 1) {
			setValue('cityId', matches[0].id, {shouldValidate: true});
			setValue('city', matches[0].name);
		}
	};

	const cityOptions = cities.map((city) => ({
		value: city.id,
		label: city.name,
	}));

	const selectedCityOption = cityOptions.find((option) => option.value === values.cityId) || null;

	return (
		<div className="bripeon-form-step">
			<div className="bripeon-form-group">
				<h3>Údaje o firme</h3>

				<div>
					<label htmlFor="provider-companyName">Názov firmy</label>
					<input id="provider-companyName" autoComplete="organization" aria-invalid={Boolean(errors.companyName)} aria-describedby={errors.companyName ? 'provider-companyName-error' : undefined} type="text" {...register('companyName')}/>
					{errors.companyName && <p id="provider-companyName-error" className="bripeon-field-error" role="alert">{errors.companyName.message}</p>}
				</div>

				<div>
					<label htmlFor="provider-ico">IČO <span className="bripeon-optional">(nepovinné)</span></label>
					<input id="provider-ico" type="text" inputMode="numeric" {...register('ico')}/>
				</div>

				<div className="bripeon-form-grid">
					<div>
						<label htmlFor="provider-street">Ulica</label>
						<input id="provider-street" autoComplete="address-line1" aria-invalid={Boolean(errors.street)} aria-describedby={errors.street ? 'provider-street-error' : undefined} type="text" {...register('street')}/>
						{errors.street && <p id="provider-street-error" className="bripeon-field-error" role="alert">{errors.street.message}</p>}
					</div>
					<div>
						<label htmlFor="provider-streetNumber">Číslo</label>
						<input id="provider-streetNumber" aria-invalid={Boolean(errors.streetNumber)} aria-describedby={errors.streetNumber ? 'provider-streetNumber-error' : undefined} type="text" {...register('streetNumber')}/>
						{errors.streetNumber && <p id="provider-streetNumber-error" className="bripeon-field-error" role="alert">{errors.streetNumber.message}</p>}
					</div>
				</div>

				<div className="bripeon-form-grid">
					<div>
						<label htmlFor="provider-cityId">Mesto</label>
						<Select
							inputId="provider-cityId"
							aria-invalid={Boolean(errors.cityId)}
							aria-describedby={errors.cityId ? 'provider-cityId-error' : undefined}
							options={cityOptions}
							value={selectedCityOption}
							onChange={(option) => {
								if (!option) {
									setValue('cityId', '', {shouldValidate: true});
									setValue('city', '');
									setValue('zip', '', {shouldValidate: true});
									return;
								}
								const selected = cities.find((c) => c.id === option.value);
								if (selected) {
									setValue('cityId', selected.id, {shouldValidate: true});
									setValue('city', selected.name);
									setValue('zip', selected.zip, {shouldValidate: true});
								}
							}}
							filterOption={(option, inputValue) => {
								const words = option.label.toLowerCase().split(' ');
								return words.some((word) => word.startsWith(inputValue.toLowerCase()));
							}}
							placeholder="-- Vyberte mesto --"
							className="react-select-container"
							classNamePrefix="react-select"
							isClearable
						/>
						{errors.cityId && <p id="provider-cityId-error" className="bripeon-field-error" role="alert">{errors.cityId.message}</p>}
					</div>

					<div>
						<label htmlFor="provider-zip">PSČ</label>
						<input
							id="provider-zip"
							autoComplete="postal-code"
							inputMode="numeric"
							aria-invalid={Boolean(errors.zip)}
							aria-describedby={errors.zip ? 'provider-zip-error' : undefined}
							type="text"
							{...register('zip')}
							onChange={handleZipChange}
						/>
						{errors.zip && <p id="provider-zip-error" className="bripeon-field-error" role="alert">{errors.zip.message}</p>}
					</div>
				</div>
			</div>

			<div className="bripeon-form-group">
				<h3>Kontaktná osoba</h3>

				<div className="bripeon-checkbox-row">
					<input
						id="provider-usePersonalAsContact"
						type="checkbox"
						checked={values.usePersonalAsContact}
						onChange={(e) => {
							setValue('usePersonalAsContact', e.target.checked, {shouldValidate: true});
						}}
					/>
					<label htmlFor="provider-usePersonalAsContact">Použiť údaje z kroku 2</label>
				</div>

				{!values.usePersonalAsContact && (
					<div className="bripeon-form-grid">
						<div>
							<label htmlFor="provider-contactFirstName">Meno</label>
							<input id="provider-contactFirstName" autoComplete="section-contact given-name" aria-invalid={Boolean(errors.contactFirstName)} aria-describedby={errors.contactFirstName ? 'provider-contactFirstName-error' : undefined} type="text" {...register('contactFirstName')}/>
							{errors.contactFirstName && <p id="provider-contactFirstName-error" className="bripeon-field-error" role="alert">{errors.contactFirstName.message}</p>}
						</div>
						<div>
							<label htmlFor="provider-contactLastName">Priezvisko</label>
							<input id="provider-contactLastName" autoComplete="section-contact family-name" aria-invalid={Boolean(errors.contactLastName)} aria-describedby={errors.contactLastName ? 'provider-contactLastName-error' : undefined} type="text" {...register('contactLastName')}/>
							{errors.contactLastName && <p id="provider-contactLastName-error" className="bripeon-field-error" role="alert">{errors.contactLastName.message}</p>}
						</div>
					</div>
				)}
			</div>
		</div>
	);
};

export default Step3;

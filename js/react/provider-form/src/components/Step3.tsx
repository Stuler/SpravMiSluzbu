import React, {useEffect, useState} from 'react';
import {useForm} from 'react-hook-form';
import Select from 'react-select';

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
		formState: {isValid},
	} = useForm<FormData>({
		mode: 'onChange',
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
		Object.entries(values).forEach(([field, value]) => {
			onChange(field as keyof FormData, value);
		});
	}, [values, onChange]);

	const handleZipChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const zip = e.target.value;
		setValue('zip', zip);

		const matchedCity = cities.find((city) => city.zip === zip);
		if (matchedCity) {
			setValue('cityId', matchedCity.id);
			setValue('city', matchedCity.name);
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
					<label>Názov firmy</label>
					<input type="text" {...register('companyName')}/>
				</div>

				<div>
					<label>IČO</label>
					<input type="text" {...register('ico')}/>
				</div>

				<div className="bripeon-form-grid">
					<div>
						<label>Mesto</label>
						<Select
							options={cityOptions}
							value={selectedCityOption}
							onChange={(option) => {
								if (!option) {
									setValue('cityId', '');
									setValue('city', '');
									setValue('zip', '');
									return;
								}
								const selected = cities.find((c) => c.id === option.value);
								if (selected) {
									setValue('cityId', selected.id);
									setValue('city', selected.name);
									setValue('zip', selected.zip);
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
					</div>

					<div>
						<label>PSČ</label>
						<input
							type="text"
							{...register('zip')}
							onChange={handleZipChange}
						/>
					</div>
				</div>
			</div>

			<div className="bripeon-form-group">
				<h3>Kontaktná osoba</h3>

				<div className="bripeon-checkbox-row">
					<input
						type="checkbox"
						checked={values.usePersonalAsContact}
						onChange={(e) => {
							setValue('usePersonalAsContact', e.target.checked);
						}}
					/>
					<label>Použiť údaje z kroku 2</label>
				</div>

				{!values.usePersonalAsContact && (
					<div className="bripeon-form-grid">
						<div>
							<label>Meno</label>
							<input type="text" {...register('contactFirstName')}/>
						</div>
						<div>
							<label>Priezvisko</label>
							<input type="text" {...register('contactLastName')}/>
						</div>
					</div>
				)}
			</div>
		</div>
	);
};

export default Step3;

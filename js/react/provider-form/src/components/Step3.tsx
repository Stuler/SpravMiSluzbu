import React from 'react';

type CityOption = { id: string; name: string };

type Props = {
	data: {
		companyName: string;
		ico: string;
		street: string;
		streetNumber: string;
		city: string;
		zip: string;
		usePersonalAsContact: boolean;
		contactFirstName: string;
		contactLastName: string;
	};
	onChange: (field: keyof Props['data'], value: string | boolean) => void;
};

const Step3: React.FC<Props> = ({data, onChange}) => {
	const [cities, setCities] = useState<CityOption[]>([]);

	useEffect(() => {
		fetch('/api/cities')
			.then((res) => res.json())
			.then((data) => {
				const formatted = data.map((item: any) => ({
					id: item.id,
					name: item.name,
				}));
				setCities(formatted);
			});
	}, []);
	return (
		<div className="space-y-6">
			{/* Firma */}
			<div className="space-y-3">
				<h3 className="font-semibold">Údaje o firme</h3>

				<div>
					<label>Názov firmy</label>
					<input
						type="text"
						value={data.companyName}
						onChange={(e) => onChange('companyName', e.target.value)}
						className="w-full border px-3 py-2 rounded"
					/>
				</div>

				<div>
					<label>IČO</label>
					<input
						type="text"
						value={data.ico}
						onChange={(e) => onChange('ico', e.target.value)}
						className="w-full border px-3 py-2 rounded"
					/>
				</div>

				<div className="grid grid-cols-2 gap-4">
					<div>
						<label>Ulica</label>
						<input
							type="text"
							value={data.street}
							onChange={(e) => onChange('street', e.target.value)}
							className="w-full border px-3 py-2 rounded"
						/>
					</div>
					<div>
						<label>Číslo ulice</label>
						<input
							type="text"
							value={data.streetNumber}
							onChange={(e) => onChange('streetNumber', e.target.value)}
							className="w-full border px-3 py-2 rounded"
						/>
					</div>
				</div>

				<div className="grid grid-cols-2 gap-4">
					<div>
						<label>Mesto</label>
						<select
							value={data.cityId}
							onChange={handleCityChange}
							className="w-full border px-3 py-2 rounded"
						>
							<option value="">-- Vyberte mesto --</option>
							{cities.map((city) => (
								<option key={city.id} value={city.id}>
									{city.name}
								</option>
							))}
						</select>
						{errors.city && (
							<p className="text-red-500 text-sm">{errors.city.message}</p>
						)}
					</div>

					<div>
						<label>PSČ</label>
						<input
							type="text"
							value={data.zip}
							onChange={(e) => onChange('zip', e.target.value)}
							className="w-full border px-3 py-2 rounded"
						/>
						{errors.zip && (
							<p className="text-red-500 text-sm">{errors.zip.message}</p>
						)}
					</div>
				</div>
			</div>

			{/* Kontaktná osoba */}
			<div className="space-y-3">
				<h3 className="font-semibold">Kontaktná osoba</h3>

				<div className="flex items-center gap-2">
					<input
						type="checkbox"
						checked={data.usePersonalAsContact}
						onChange={(e) => onChange('usePersonalAsContact', e.target.checked)}
					/>
					<label>Použiť údaje z kroku 2</label>
				</div>

				{!data.usePersonalAsContact && (
					<div className="grid grid-cols-2 gap-4">
						<div>
							<label>Meno</label>
							<input
								type="text"
								value={data.contactFirstName}
								onChange={(e) => onChange('contactFirstName', e.target.value)}
								className="w-full border px-3 py-2 rounded"
							/>
						</div>
						<div>
							<label>Priezvisko</label>
							<input
								type="text"
								value={data.contactLastName}
								onChange={(e) => onChange('contactLastName', e.target.value)}
								className="w-full border px-3 py-2 rounded"
							/>
						</div>
					</div>
				)}
			</div>
		</div>
	);
};

export default Step3;

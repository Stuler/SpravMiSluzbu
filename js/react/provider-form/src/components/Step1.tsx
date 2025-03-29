import React, {useEffect, useState} from 'react';
import Select from 'react-select';

type Option = { value: string; label: string };

type Props = {
	data: {
		serviceCategories: Option[];
		coveredRegions: Option[];
	};
	onChange: (field: keyof Props['data'], value: Option[]) => void;
	onStepValid: (isValid: boolean) => void;
};

const Step1: React.FC<Props> = ({data, onChange, onStepValid}) => {
	const [categoryOptions, setCategoryOptions] = useState<Option[]>([]);
	const [regionOptions, setRegionOptions] = useState<Option[]>([]);

	useEffect(() => {
		// Replace with your actual endpoints
		fetch('/api/categories')
			.then(res => res.json())
			.then(data => {
				const options = data.map((item: any) => ({
					value: item.id,
					label: item.name,
				}));
				setCategoryOptions(options);
			});

		fetch('/api/regions')
			.then(res => res.json())
			.then(data => {
				const options = data.map((item: any) => ({
					value: item.id,
					label: item.name,
				}));
				setRegionOptions(options);
			});
	}, []); // [] means - only run initially

	useEffect(() => {
		const isValid = data.serviceCategories.length > 0 && data.coveredRegions.length > 0;
		onStepValid(isValid);
	}, [data.serviceCategories, data.coveredRegions, onStepValid]);


	return (
		<div className="space-y-6">
			<div>
				<label className="block mb-1 font-medium">Service Categories</label>
				<Select
					isMulti
					options={categoryOptions}
					value={data.serviceCategories}
					onChange={(selected) => onChange('serviceCategories', selected as Option[])}
				/>
			</div>

			<div>
				<label className="block mb-1 font-medium">Covered Regions</label>
				<Select
					isMulti
					options={regionOptions}
					value={data.coveredRegions}
					onChange={(selected) => onChange('coveredRegions', selected as Option[])}
				/>
			</div>

			<p className="text-sm text-red-500 mt-1">Prosím vyberte aspoň jednu kategóriu a región.</p>
		</div>
	);
};

export default Step1;

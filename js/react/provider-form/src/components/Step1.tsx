import React, {useEffect, useState} from 'react';
import Select from 'react-select';

type Option = { value: number; label: string };
type GroupedOption = { label: string; options: Option[] };

type Props = {
	data: {
		serviceCategories: Option[];
		coveredRegions: Option[];
	};
	onChange: (field: keyof Props['data'], value: Option[]) => void;
	onStepValid: (isValid: boolean) => void;
};

const Step1: React.FC<Props> = ({data, onChange, onStepValid}) => {
	const [categoryOptions, setCategoryOptions] = useState<GroupedOption[]>([]);
	const [regionOptions, setRegionOptions] = useState<Option[]>([]);

	useEffect(() => {
		fetch('/api/categories')
			.then(res => res.json())
			.then(data => {
				setCategoryOptions(data);
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
		<div className="bripeon-form-step">
			<div>
				<label>Aké služby ponúkate?</label>
				<Select
					isMulti
					options={categoryOptions}
					value={data.serviceCategories}
					onChange={(selected) => onChange('serviceCategories', selected as Option[])}
					placeholder="Vyberte kategórie služieb"
					className="react-select-container"
					classNamePrefix="react-select"
				/>
			</div>

			<div>
				<label>Kde pôsobíte?</label>
				<Select
					isMulti
					options={regionOptions}
					value={data.coveredRegions}
					onChange={(selected) => onChange('coveredRegions', selected as Option[])}
					placeholder="Vyberte regióny"
					className="react-select-container"
					classNamePrefix="react-select"
				/>
			</div>

			<p className="bripeon-form-help">Vyberte aspoň jednu kategóriu a región, aby sme vedeli priradiť relevantné dopyty.</p>
		</div>
	);
};

export default Step1;

import React, {useState} from 'react';
import Step1 from './components/Step1';
import Step2 from './components/Step2';
import Step3 from './components/Step3';

type Option = { value: string; label: string };

type FormData = {
	serviceCategories: Option[];
	coveredRegions: Option[];
	firstName: string;
	lastName: string;
	email: string;
	password: string;
	confirmPassword: string;
	phone: string;
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
	subscriptionPlan: 'free' | 'basic' | 'premium';
	cardNumber: string;
	cardExpiry: string;
	cardCvc: string;
};


const initialData: FormData = {
	serviceCategories: [],
	coveredRegions: [],
	firstName: '',
	lastName: '',
	email: '',
	password: '',
	confirmPassword: '',
	phone: '',
	ico: '',
	companyName: '',
	street: '',
	streetNumber: '',
	cityId: '',
	city: '',
	zip: '',
	usePersonalAsContact: true,
	contactFirstName: '',
	contactLastName: '',
	subscriptionPlan: 'free',
	cardNumber: '',
	cardExpiry: '',
	cardCvc: '',
};

/**
 * Main App component that manages the multi-step form.
 * - Holds overall form data and step index.
 * - Controls which step to render.
 * - Passes formData and onChange() to child steps.
 * - Handles navigation (Next, Back, Submit).
 *
 * @constructor
 */
const App: React.FC = () => {
	const [step, setStep] = useState(0);
	const [formData, setFormData] = useState<FormData>(initialData);
	const [stepValid, setStepValid] = useState(false);

	/**
	 * Handles changes in form fields.
	 * Updates the formData state with the new value.
	 *
	 * @param field
	 * @param value
	 */
	const handleChange = (field: keyof FormData, value: any) => {
		setFormData((prev) => ({...prev, [field]: value}));
	};

	const next = () => setStep((prev) => Math.min(prev + 1, 3));
	const back = () => setStep((prev) => Math.max(prev - 1, 0));
	const handleSubmit = () => {
		console.log('Form submitted:', formData);
		// submit to API here
	};

	const steps = [
		<Step1 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step2 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step3 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
	];

	return (
		<div className="max-w-xl mx-auto p-6 border rounded shadow">
			<h2 className="text-xl font-semibold mb-4">Krok {step + 1} z 3</h2>

			{steps[step]}

			<div className="mt-6 flex justify-between">
				{step > 0 ? (
					<button
						onClick={back}
						className="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
					>
						Späť
					</button>
				) : <div/>} {/* keeps spacing if Back isn't shown */}

				{step < steps.length - 1 ? (
					<button
						onClick={next}
						disabled={!stepValid}
						className={`px-4 py-2 rounded ${
							stepValid
								? 'bg-blue-500 text-white hover:bg-blue-600'
								: 'bg-gray-300 text-gray-500 cursor-not-allowed'
						}`}
					>
						Ďalej
					</button>
				) : (
					<button
						onClick={handleSubmit}
						className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
					>
						Odoslať
					</button>
				)}
			</div>
		</div>
	);
};

export default App;

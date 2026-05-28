import React, {useState, useEffect} from 'react';
import Step1 from './components/Step1';
import Step2 from './components/Step2';
import Step3 from './components/Step3';
import Step4 from './components/Step4';

type Option = { value: number; label: string };

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
};

const App: React.FC = () => {
	const [step, setStep] = useState(0);
	const [formData, setFormData] = useState<FormData>(initialData);
	const [stepValid, setStepValid] = useState(false);
	const [clientSecret, setClientSecret] = useState<string | null>(null);

	const handleChange = (field: keyof FormData, value: any) => {
		setFormData((prev) => ({...prev, [field]: value}));
	};

	const next = async () => {
		if (step === 2) { // Step3 -> Step4
			try {
				const registerRes = await fetch('/api/create-provider', {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(formData),
				});

				if (!registerRes.ok) {
					throw new Error('Failed to register user.');
				}

				const registerData = await registerRes.json();
				const userId = registerData.userId;

				const paymentRes = await fetch('/api/create-payment-intent', {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({
						userId,
					}),
				});

				if (!paymentRes.ok) {
					throw new Error('Failed to create payment intent.');
				}

				const paymentData = await paymentRes.json();
				setClientSecret(paymentData.clientSecret);

				setStep((prev) => prev + 1);

			} catch (error) {
				console.error('Error during registration or payment intent creation:', error);
				alert('Nastala chyba pri registrácii alebo vytvorení platby.');
			}
		} else {
			setStep((prev) => Math.min(prev + 1, 4));
		}
	};

	const back = () => setStep((prev) => Math.max(prev - 1, 0));

	const handleSubmit = () => {
		console.log('Bripeon provider form submitted:', formData);
		// You may want to submit form data to your API first
	};

	const steps = [
		<Step1 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step2 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step3 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		clientSecret ? <Step4 clientSecret={clientSecret}/> : <p className="bripeon-form-help">Načítavam platobný formulár...</p>,
	];

	return (
		<div className="bripeon-provider-wizard">
			<div className="bripeon-wizard-header">
				<div>
					<span>Registrácia</span>
					<h2>Krok {step + 1} z 4</h2>
				</div>
				<div className="bripeon-progress" aria-label={`Krok ${step + 1} z 4`}>
					<span style={{width: `${((step + 1) / 4) * 100}%`}}/>
				</div>
			</div>

			{steps[step]}

			<div className="bripeon-wizard-actions">
				{step > 0 ? (
					<button
						onClick={back}
						className="bripeon-secondary-button"
					>
						Späť
					</button>
				) : (
					<div/>
				)}

				{step < steps.length - 1 ? (
					<button
						onClick={next}
						disabled={!stepValid && step < 3} // Step 4 uses its own validation
						className="bripeon-primary-button"
					>
						Ďalej
					</button>
				) : (
					<button
						onClick={handleSubmit}
						className="bripeon-primary-button"
					>
						Odoslať
					</button>
				)}
			</div>
		</div>
	);
};

export default App;

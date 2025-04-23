import React, {useState, useEffect} from 'react';
import Step1 from './components/Step1';
import Step2 from './components/Step2';
import Step3 from './components/Step3';
import Step4 from './components/Step4'; // 👈 Import your payment form

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
				// 1. First, send the collected form data to backend to create the user
				const registerRes = await fetch('/api/create-provider', {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(formData),
				});

				if (!registerRes.ok) {
					throw new Error('Failed to register user.');
				}

				const registerData = await registerRes.json();
				const userId = registerData.userId; // Expect your backend to return { userId: 123 }

				// 2. Then, create payment intent for that user
				const paymentRes = await fetch('/api/create-payment-intent', {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({
						subscriptionPlan: formData.subscriptionPlan,
						userId: userId, // <-- send it to Stripe metadata
					}),
				});

				if (!paymentRes.ok) {
					throw new Error('Failed to create payment intent.');
				}

				const paymentData = await paymentRes.json();
				setClientSecret(paymentData.clientSecret);

				// 3. Finally, move to Step 4
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
		console.log('Form submitted:', formData);
		// You may want to submit form data to your API first
	};

	const steps = [
		<Step1 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step2 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step3 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		clientSecret ? <Step4 clientSecret={clientSecret}/> : <p>Loading payment form...</p>,
	];

	return (
		<div className="max-w-xl mx-auto p-6 border rounded shadow">
			<h2 className="text-xl font-semibold mb-4">Krok {step + 1} z 4</h2>

			{steps[step]}

			<div className="mt-6 flex justify-between">
				{step > 0 ? (
					<button
						onClick={back}
						className="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
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
						className={`px-4 py-2 rounded ${
							stepValid || step === 3
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

import React, {useCallback, useEffect, useRef, useState} from 'react';
import Step1 from './components/Step1';
import Step2 from './components/Step2';
import Step3 from './components/Step3';
import {normalizePhone} from './components/step2Schema';

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

const TOTAL_STEPS = 3;

const App: React.FC = () => {
	const [step, setStep] = useState(0);
	const [formData, setFormData] = useState<FormData>(initialData);
	const [stepValid, setStepValid] = useState(false);
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [submissionErrors, setSubmissionErrors] = useState<string[]>([]);
	const submittingRef = useRef(false);
	const errorRef = useRef<HTMLDivElement>(null);

	const handleChange = useCallback((field: keyof FormData, value: FormData[keyof FormData]) => {
		setFormData((prev) => Object.is(prev[field], value) ? prev : {...prev, [field]: value});
	}, []);

	const next = useCallback(() => {
		setStepValid(false);
		setStep((prev) => Math.min(prev + 1, TOTAL_STEPS - 1));
	}, []);
	const back = () => {
		setStepValid(false);
		setStep((prev) => Math.max(prev - 1, 0));
	};

	// Keep errors and entered data on the page while Nette validates the CSRF token.
	const submit = useCallback(async () => {
		if (!stepValid || submittingRef.current) {
			return;
		}

		const formEl = document.getElementById('providerRegistrationForm') as HTMLFormElement | null;
		const payloadInput = formEl?.querySelector('input[name="payload"]') as HTMLInputElement | null;
		const submitButton = document.getElementById('providerRegistrationSubmit') as HTMLButtonElement | null;

		if (!formEl || !payloadInput || !submitButton) {
			setSubmissionErrors(['Formulár sa nepodarilo načítať. Načítajte stránku znova.']);
			return;
		}

		const body = new window.FormData(formEl);
		body.set('payload', JSON.stringify({...formData, phone: normalizePhone(formData.phone)}));
		body.set(submitButton.name, submitButton.value);
		submittingRef.current = true;
		setIsSubmitting(true);
		setSubmissionErrors([]);
		try {
			const response = await fetch(formEl.action, {
				method: 'POST',
				headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
				credentials: 'same-origin',
				body,
			});
			const result = await response.json();
			if (response.ok && typeof result.redirect === 'string') {
				window.location.assign(result.redirect);
				return;
			}
			const errors = Array.isArray(result.errors) ? result.errors.filter((error: unknown) => typeof error === 'string') : [];
			setSubmissionErrors(errors.length ? errors : ['Registráciu sa nepodarilo dokončiť. Skúste to znova.']);
		} catch {
			setSubmissionErrors(['Registráciu sa nepodarilo odoslať. Skontrolujte pripojenie a skúste to znova.']);
		} finally {
			submittingRef.current = false;
			setIsSubmitting(false);
		}
	}, [formData, stepValid]);

	useEffect(() => {
		const formEl = document.getElementById('providerRegistrationForm');
		const handleSubmit = (event: Event) => {
			event.preventDefault();
			if (!stepValid || submittingRef.current) {
				return;
			}
			if (step < TOTAL_STEPS - 1) {
				next();
			} else {
				void submit();
			}
		};
		formEl?.addEventListener('submit', handleSubmit);
		return () => formEl?.removeEventListener('submit', handleSubmit);
	}, [step, stepValid, next, submit]);

	useEffect(() => {
		if (submissionErrors.length) {
			errorRef.current?.focus();
		}
	}, [submissionErrors]);

	const steps = [
		<Step1 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step2 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
		<Step3 data={formData} onChange={handleChange} onStepValid={setStepValid}/>,
	];

	const isLastStep = step === TOTAL_STEPS - 1;

	return (
		<div className="bripeon-provider-wizard">
			<div className="bripeon-wizard-header">
				<div>
					<span>Registrácia</span>
					<h2>Krok {step + 1} z {TOTAL_STEPS}</h2>
				</div>
				<div className="bripeon-progress" aria-label={`Krok ${step + 1} z ${TOTAL_STEPS}`}>
					<span style={{width: `${((step + 1) / TOTAL_STEPS) * 100}%`}}/>
				</div>
			</div>

			{submissionErrors.length > 0 && (
				<div className="bripeon-form-error" role="alert" tabIndex={-1} ref={errorRef}>
					{submissionErrors.map((error, index) => <p key={index}>{error}</p>)}
				</div>
			)}
			<fieldset className="bripeon-wizard-fields" disabled={isSubmitting}>
				{steps[step]}
			</fieldset>

			<div className="bripeon-wizard-actions">
				{step > 0 ? (
					<button type="button" onClick={back} className="bripeon-secondary-button" disabled={isSubmitting}>
						Späť
					</button>
				) : (
					<div/>
				)}

				{isLastStep ? (
					<button
						type="button"
						onClick={submit}
						disabled={!stepValid || isSubmitting}
						className="bripeon-primary-button"
					>
						{isSubmitting ? 'Odosielam…' : 'Odoslať'}
					</button>
				) : (
					<button
						type="button"
						onClick={next}
						disabled={!stepValid}
						className="bripeon-primary-button"
					>
						Ďalej
					</button>
				)}
			</div>
		</div>
	);
};

export default App;

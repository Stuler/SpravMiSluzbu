import React, {useState, useEffect} from 'react';
import {loadStripe, Stripe} from '@stripe/stripe-js';
import {Elements, PaymentElement, useStripe, useElements} from '@stripe/react-stripe-js';

let cachedStripePromise: Promise<Stripe | null> | null = null;

const Step4: React.FC<{ clientSecret: string }> = ({clientSecret}) => {
	const [stripePromise, setStripePromise] = useState<Promise<Stripe | null> | null>(null);

	useEffect(() => {
		if (!cachedStripePromise) {
			fetch('/api/get-stripe-public-key')
				.then((res) => res.json())
				.then((data) => {
					cachedStripePromise = loadStripe(data.publicKey);
					setStripePromise(cachedStripePromise);
				});
		} else {
			setStripePromise(cachedStripePromise);
		}
	}, []);

	const options = {
		clientSecret,
		appearance: {
			theme: 'stripe' as const,
		},
		loader: 'auto' as const,
	};

	if (!stripePromise) {
		return <p className="bripeon-form-help">Načítavam platobný formulár...</p>;
	}

	return (
		<div className="bripeon-payment-panel">
			<Elements stripe={stripePromise} options={options}>
				<CheckoutForm/>
			</Elements>
		</div>
	);
};

const CheckoutForm: React.FC = () => {
	const stripe = useStripe();
	const elements = useElements();

	const [message, setMessage] = useState<string | null>(null);
	const [isLoading, setIsLoading] = useState(false);

	const handleSubmit = async (e: React.FormEvent) => {
		e.preventDefault();

		if (!stripe || !elements) {
			return;
		}

		setIsLoading(true);

		const {error} = await stripe.confirmPayment({
			elements,
			confirmParams: {
				return_url: window.location.origin + '/thank-you',
			},
		});

		if (error?.type === 'card_error' || error?.type === 'validation_error') {
			setMessage(error.message ?? 'Nastala chyba pri spracovaní platby.');
		} else if (error) {
			setMessage('Nastala neočakávaná chyba.');
		}

		setIsLoading(false);
	};

	const paymentElementOptions = {
		layout: 'accordion' as const,
	};

	return (
		<form id="payment-form" onSubmit={handleSubmit} className="bripeon-form-step">
			<h3>Platobné údaje</h3>
			<PaymentElement id="payment-element" options={paymentElementOptions}/>
			{message && <p className="bripeon-field-error">{message}</p>}
			<button
				type="submit"
				id="submit"
				className="bripeon-primary-button bripeon-payment-button"
				disabled={!stripe || !elements || isLoading}
			>
        <span id="button-text">
          {isLoading ? <div className="spinner" id="spinner"/> : 'Zaplatiť'}
        </span>
			</button>
		</form>
	);
};

export default Step4;

import React from "react";
import ReactDOM from "react-dom/client";
import ProviderSignUpForm from "./provider-form/ProviderSignUpForm";

const rootElement = document.getElementById("providerSignUpForm");
if (rootElement) {
	ReactDOM.createRoot(rootElement).render(
		<React.StrictMode>
			<ProviderSignUpForm/>
		</React.StrictMode>
	);
}

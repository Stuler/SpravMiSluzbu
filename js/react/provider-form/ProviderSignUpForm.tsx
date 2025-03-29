import React from 'react';
import {Provider} from 'mobx-react';
import {store} from "./src/store/store";
import App from "./src/App";

const ProviderSignUpForm: React.FC = () => {
	return (
		<Provider {...store}>
			<App/>
		</Provider>
	);
};

export default ProviderSignUpForm;

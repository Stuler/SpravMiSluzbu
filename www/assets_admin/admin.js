import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import './vendor/bootstrap-icons/bootstrap-icons.css';
import './vendor/boxicons/css/boxicons.min.css';
import './vendor/quill/quill.snow.css';
import './vendor/quill/quill.bubble.css';
import './vendor/remixicon/remixicon.css';
import './vendor/simple-datatables/style.css';

import './vendor/apexcharts/apexcharts.min.js';
import './vendor/bootstrap/js/bootstrap.bundle.min.js';
import './vendor/chart.js/chart.umd.js';
import './vendor/echarts/echarts.min.js';
import './vendor/quill/quill.js';
import './vendor/simple-datatables/simple-datatables.js';
import './vendor/php-email-form/validate.js';

import naja from 'naja';
import netteForms from 'nette-forms';
import "./js/main";
import "./js/datagrid/datagrid.js";
import "./js/datagrid/datagrid.css";
import "./js/datagrid/datagrid-spinners.css";
import "./js/datagrid/datagrid-spinners.js";
import "./js/datagrid/datagrid-instant-url-refresh";
// import bootstrap icons
import './vendor/bootstrap-icons/bootstrap-icons.css';


document.addEventListener('DOMContentLoaded', () => {
	console.log('DOMContentLoaded');
	naja.initialize();
	naja.formsHandler.netteForms = netteForms;
});

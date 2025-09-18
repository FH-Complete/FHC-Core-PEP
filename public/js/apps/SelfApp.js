import SelfReport from '../components/SelfReport.js';
import FhcAlert from '../../../../js/plugins/FhcAlert.js';
import Phrasen from '../../../../js/plugins/Phrasen.js';
import FhcApi from '../../../../js/plugins/Api.js';

const pepSelfAPP = Vue.createApp({
	components: {
		SelfReport
	}
});

pepSelfAPP
	.use(primevue.config.default)
	.use(FhcAlert)
	.use(FhcApi)
	.use(Phrasen)
	.mount('#main');

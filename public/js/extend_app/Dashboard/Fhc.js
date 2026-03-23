import SelfReport from '../../components/SelfReport.js';

FhcApps.router.addRoute({
	path: `/extensions/FHC-Core-PEP/PEP/self`,
	name: 'PEPSelfReport',
	component: SelfReport,
	props: true
});


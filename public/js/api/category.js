import {CoreRESTClient} from "../../../../../public/js/RESTClient";

export default {
	/*deleteMassnahme(data)
	{
		try {
			return CoreRESTClient.post('/extensions/FHC-Core-International/Student/studentDeleteMassnahme', data);
		} catch (error) {
			throw error;
		}
	},*/

	get(data)
	{
		try {
			return CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/getCategoryData', data);
		} catch (error) {
			throw error;
		}
	},
	getStart(data)
	{
		try {
			return CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/loadReport', data);
		} catch (error) {
			throw error;
		}
	},
	getLehre(data)
	{
		try {
			return CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/lehreReport', data);
		} catch (error) {
			throw error;
		}
	},
	saveMitarbeiter(data)
	{
		try {
			return CoreRESTClient.post('/extensions/FHC-Core-PEP/components/PEP/saveMitarbeiter', data);
		} catch (error) {
			throw error;
		}
	},


};
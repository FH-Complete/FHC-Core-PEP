import {CoreRESTClient} from "../../../../../public/js/RESTClient";

export default {

	getStart(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getStart'
		return this.$fhcApi.get(url, data)
	},
	getCategories()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getCategories'
		return this.$fhcApi.get(url)
	},
	getCategory(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getCategory'
		return this.$fhcApi.get(url, data)
	},
	stundenvoerruecken(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/vorruecken'
		return this.$fhcApi.post(url, data)
	},
	saveMitarbeiter(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/saveMitarbeiter'
		return this.$fhcApi.post(url, data)
	},
	getLehre(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getLehre'
		return this.$fhcApi.get(url, data, {timeout: 15000});
	},

	getConfig()
	{
		return this.$fhcApi.get('/extensions/FHC-Core-PEP/components/TabsConfig/get');
	},
	getLehreinheit(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getLehreinheit'
		return this.$fhcApi.get(url, data);

	},
	getRaumtypen()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getRaumtypen'
		return this.$fhcApi.get(url);
	},
	getLektoren()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getLektoren'
		return this.$fhcApi.get(url);

	},
	saveLehreinheit(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/saveLehreinheit'
		return this.$fhcApi.post(url, data);

	},
};
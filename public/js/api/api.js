export default {

	getStart(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getStart'
		return this.$fhcApi.get(url, data, {timeout: 10000})
	},
	getCategories()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getCategories'
		return this.$fhcApi.get(url)
	},
	getCategory(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getCategory'
		return this.$fhcApi.get(url, data, {timeout: 10000})
	},
	getStudienjahre()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getStudienjahre'
		return this.$fhcApi.get(url)
	},
	getOrganisationen()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getOrganisationen'
		return this.$fhcApi.get(url)
	},
	getProjects(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getProjects'
		return this.$fhcApi.get(url, data)
	},
	stundenvoerruecken(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/vorruecken'
		return this.$fhcApi.post(url, data, {timeout: 10000})
	},
	stundenzuruecksetzen(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/stundenzuruecksetzen'
		return this.$fhcApi.post(url, data, {timeout: 10000})
	},
	saveMitarbeiter(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/saveMitarbeiter'
		return this.$fhcApi.post(url, data, {timeout: 10000})
	},
	getLehre(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getLehre'
		return this.$fhcApi.get(url, data, {timeout: 10000});
	},

	setVar(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/setVariables'
		return this.$fhcApi.post(url, data, {timeout: 10000});
	},
	getConfig()
	{
		return this.$fhcApi.get('/extensions/FHC-Core-PEP/components/TabsConfig/get', {timeout: 10000});
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
		return this.$fhcApi.get(url, {timeout: 10000});
	},
	getProjekte()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/getProjekte'
		return this.$fhcApi.get(url, {timeout: 10000});
	},
	saveLehreinheit(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/saveLehreinheit'
		return this.$fhcApi.post(url, data, {timeout: 10000});

	},
	addProjectStunden(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/addProjectStunden'
		return this.$fhcApi.post(url, data, {timeout: 10000});
	},
	updateProjectStunden(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/updateProjectStunden'
		return this.$fhcApi.post(url, data, {timeout: 10000});
	},
	updateAnmerkung(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/updateAnmerkung'
		return this.$fhcApi.post(url, data, {timeout: 10000});

	},
	deleteProjectStunden(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/PEP/deleteProjectStunden'
		return this.$fhcApi.post(url, data, {timeout: 10000});

	},
};
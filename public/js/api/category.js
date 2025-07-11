export default {
	getCategory(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Category/getCategory',
			params: data
		}
	},

	getOrgForCategories()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Category/getOrgForCategories',
		}
	},

	stundenzuruecksetzen(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Category/stundenzuruecksetzen',
			params: data
		}
	},

	saveMitarbeiter(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Category/saveMitarbeiter',
			params: data
		}
	},
};
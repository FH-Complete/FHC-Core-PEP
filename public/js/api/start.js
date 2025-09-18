export default {


	//Start
	getStart(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Start/getStart',
			params: data
		}
	},
	getStudiensemester(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Start/getStudiensemester',
			params: data
		}
	},
	getCategories()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Start/getCategories',
		}
	},
};
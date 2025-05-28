export default {

	getConfig()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/TabsConfig/get'
		}
	},

	setVar(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Setup/setVariables',
			params: data
		}
	},
};
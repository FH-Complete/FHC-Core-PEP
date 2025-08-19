export default {
	getSelf(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/SelfOverview/getSelfOverview',
			params: data
		}
	},
	getLektoren(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/SelfOverview/getLektoren',
			params: data
		}
	},
};
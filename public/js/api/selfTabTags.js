export default {
	getTag(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/SelfOverviewTags/getTag',
			params: data
		};
	},
};
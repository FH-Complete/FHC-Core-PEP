export const Tags = {

	getTag(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/getTag',
			params: data
		};
	},

	getTags()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/getTags',
		};
	},

	addTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/addTag',
			params: data
		};
	},

	updateTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/updateTag',
			params: data
		};
	},

	doneTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/doneTag',
			params: data
		};
	},

	deleteTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/deleteTag',
			params: data
		};
	},

};
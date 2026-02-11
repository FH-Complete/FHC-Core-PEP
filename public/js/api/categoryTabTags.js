export const ApiCategoryTag = {

	getTag(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/getTag',
			params: data
		};
	},

	getTags()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/getTags',
		};
	},

	addTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/addTag',
			params: data
		};
	},

	updateTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/updateTag',
			params: data
		};
	},

	doneTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/doneTag',
			params: data
		};
	},

	deleteTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/CategoryTags/deleteTag',
			params: data
		};
	},


};
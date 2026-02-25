export const ApiProjectTag = {

	getTag(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/getTag',
			params: data
		};
	},

	getTags()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/getTags',
		};
	},

	addTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/addTag',
			params: data
		};
	},

	updateTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/updateTag',
			params: data
		};
	},

	doneTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/doneTag',
			params: data
		};
	},

	deleteTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/ProjectTags/deleteTag',
			params: data
		};
	},


};
export const ApiLVEntwicklungTag = {

	getTag(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/getTag',
			params: data
		};
	},

	getTags()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/getTags',
		};
	},

	addTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/addLVEntwicklungTag',
			params: data
		};
	},

	updateTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/updateTag',
			params: data
		};
	},

	doneTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/doneTag',
			params: data
		};
	},

	deleteTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklungTags/deleteTag',
			params: data
		};
	},


};
export default {


	getLektoren()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/getLektoren'
		}
	},
	getProjekte()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/getProjekte'
		}
	},
	getProjects(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/getProjects',
			params: data
		}
	},
	addProjectStunden(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/addProjectStunden',
			params: data
		}
	},
	updateProjectStunden(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/updateProjectStunden',
			params: data
		}
	},

	deleteProjectStunden(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/deleteProjectStunden',
			params: data
		}
	},

	getStartAndEnd(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Project/getStartAndEnd',
			params: data
		}
	},

};
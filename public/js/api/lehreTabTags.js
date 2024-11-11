export default {
	getTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/getTag'
		return this.$fhcApi.get(url, data)
	},
	getTags()
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/getTags'
		return this.$fhcApi.get(url)
	},
	addTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/addTag'
		return this.$fhcApi.post(url, data)
	},

	updateTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/updateTag'
		return this.$fhcApi.post(url, data)
	},

	doneTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/doneTag'
		return this.$fhcApi.post(url, data)
	},
	deleteTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/deleteTag'
		return this.$fhcApi.post(url, data)
	},

};
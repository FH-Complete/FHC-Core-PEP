export default {
	getLVs(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/getLVs',
			params: data
		}
	},
	getFutureLvs(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/getFutureLvs',
			params: data
		}
	},

	getRollen()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/getRollen',
		}
	},
	getStatus()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/getStatus',
		}
	},
	update(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/update',
			params: data
		}
	},

	delete(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/LVEntwicklung/delete',
			params: data
		}
	},
};
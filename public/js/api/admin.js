export default {

	getStudienjahre()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Admin/getStudienjahre'
		}
	},

	getOrganisationen()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Admin/getOrganisationen'
		}
	},

	stundenvoerruecken(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Admin/vorruecken',
			params: data
		}
	},


};
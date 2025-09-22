export default {

	getLehre(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/getLehre',
			params: data
		}
		
	},
	getLehreinheit(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/getLehreinheit',
			params: data
		}
	},
	getLehreinheiten(data)
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/getLehreinheiten',
			params: data
		}
	},

	getLektoren()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/getLektoren'
		}
	},

	saveLehreinheit(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/saveLehreinheit',
			params: data
		}
	},

	updateFaktor(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/updateFaktor',
			params: data
		}

	},
	updateAnmerkung(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/updateAnmerkung',
			params: data
		}
	},

	//Wird derzeit nicht benötigt
	getRaumtypen()
	{
		return {
			method: 'get',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Lehre/getRaumtypen',
		}
	},



};
import { Tags } from './tags';
export default {

	...Tags,

	addTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/addMitarbeiterTag'
		return this.$fhcApi.post(url, data)
	},
	deleteTag(data)
	{
		const url = '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/deleteMitarbeiterTag'
		return this.$fhcApi.post(url, data)
	},

};
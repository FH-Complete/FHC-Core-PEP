import { Tags } from './tags.js';
export default {

	...Tags,

	addTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/addMitarbeiterTag',
			params: data
		};
	},


	deleteTag(data)
	{
		return {
			method: 'post',
			url: '/extensions/FHC-Core-PEP/components/api/fronted/v1/Tags/deleteMitarbeiterTag',
			params: data
		};
	},

};
import {personalViewTabulatorOptions} from './TabulatorSetup.js';
import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';

export const Personal = {
	components: {
		CoreFilterCmpt,
	},
	data: function() {
		return {
			personalViewTabulatorOptions: personalViewTabulatorOptions
		}
	},
	methods: {
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		setTableData(data) {
			this.$refs.personalTable.tabulator.setData(data);
		}
	},

	template: `
	<core-filter-cmpt
			ref="personalTable"
			:tabulator-options="personalViewTabulatorOptions"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
	></core-filter-cmpt>
	`
};
import {lehreViewTabulatorOptions, pepViewTabulatorOptions} from './TabulatorSetup.js';
import {pepViewerTabulatorEventHandlers} from './TabulatorSetup.js';
import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';

export const Lehre = {
	components: {
		CoreFilterCmpt,
	},
	data: function() {
		return {
			lehreViewTabulatorOptions: lehreViewTabulatorOptions,
			lehreViewerTabulatorEventHandlers: pepViewerTabulatorEventHandlers,
		}
	},
	methods: {
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		setTableData(data) {
			this.$refs.lehreTable.tabulator.setData(data);
		}
	},

	template: `
	<core-filter-cmpt
			ref="lehreTable"
			:tabulator-options="lehreViewTabulatorOptions"
			:tabulator-events="lehreViewerTabulatorEventHandlers"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
	></core-filter-cmpt>
	`
};
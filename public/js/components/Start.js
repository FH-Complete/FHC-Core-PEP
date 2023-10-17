import {pepViewTabulatorOptions} from './TabulatorSetup.js';
import {pepViewerTabulatorEventHandlers} from './TabulatorSetup.js';
import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';

export const Start = {
	components: {
		CoreFilterCmpt,
	},
	data: function() {
		return {
			pepViewTabulatorOptions: pepViewTabulatorOptions,
			pepViewerTabulatorEventHandlers: pepViewerTabulatorEventHandlers,
		}
	},
	methods: {
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		setTableData(data) {
			this.$refs.startTable.tabulator.setData(data);
		}
	},

	template: `
	<core-filter-cmpt
			ref="startTable"
			:tabulator-options="pepViewTabulatorOptions"
			:tabulator-events="pepViewerTabulatorEventHandlers"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
	></core-filter-cmpt>
	`
};
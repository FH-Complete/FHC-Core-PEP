import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';

import {formatter} from "../mixins/formatters";


export default {
	name: "Lehre",
	components: {
		CoreFilterCmpt,
	},
	props: {
		config: null,
		modelValue: null,
		currentTab: ''
	},

	computed: {
		tabulatorOptions()
		{
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				selectable: false,
				placeholder: "Keine Daten verfügbar",
				columns: [
					{title: 'Fakultaet', field: 'fakultaet', headerFilter: true},
					{title: 'STG', field: 'stg_kuerzel', headerFilter: true},
					{title: 'LV Organisationseinheit', field: 'lv_oe', headerFilter: true, visible: true},
					{title: 'LV Bezeichnung', field: 'lv_bezeichnung', headerFilter: true},
					{title: 'LE Semester', field: 'studiensemester_kurzbz', headerFilter: true, visible:false},
					{title: 'Gruppe', field: 'gruppe', headerFilter: true, visible:false},
					{title: 'LE ID', field: 'lehreinheit_id', headerFilter: true, visible:false},
					{title: 'LE Lehrform', field: 'lehrform_kurzbz', headerFilter: true},
					{title: 'Lektor*in', field: 'lektor', headerFilter: true},
					{title: 'Vorname', field: 'lektor_vorname', headerFilter: true},
					{title: 'Nachname', field: 'lektor_nachname', headerFilter: true},



					{title: 'Zrm - DV', field: 'vertraege', headerFilter: "input", formatter:"textarea", visible: false},
					{title: 'Zrm - Stunden/Woche', field: 'wochenstundenstunden', headerFilter: "input", formatter:"textarea", visible: false, hozAlign:"right"},
					{title: 'Zrm - Stundensatz', field: 'stundensaetze_lehre', headerFilter: "input", visible: false, hozAlign:"right", tooltip: formatter.stundensatzLehreToolTip},
					{title: 'Semesterstunden', field: 'lektor_stunden', headerFilter: true, bottomCalc: "sum", bottomCalcParams:{precision:2},visible: true, hozAlign:"right"},
					{title: 'LE Stundensatz', field: 'le_stundensatz', headerFilter: true, hozAlign:"right"},

					{title: 'Akt - DV', field: 'aktbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Kostenstelle', field: 'aktorgbezeichnung', headerFilter: "input", visible: false},
					{title: 'Akt - Kostenstelle - Parent', field: 'aktparentbezeichnung', headerFilter: "input", visible: false},
					{title: 'Akt - Stunden', field: 'aktstunden', headerFilter: "input", visible: false, hozAlign:"right"},
					{title: 'Akt - Stundensatz - Lehre', field: 'stundensaetze_lehre_aktuell', formatter:"textarea", headerFilter: "input", hozAlign:"right"},
				],
			}
		}
	},
	data: function() {
		return {
			org: 'kfAIDataAnalytics',
			studiensemester: ['WS2023'],
			configs: [],
		}
	},
	methods: {
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		async loadData(data) {
			await Vue.$fhcapi.Category.getLehre(data).then(response => {
				if (CoreRESTClient.isSuccess(response.data))
				{
					if (CoreRESTClient.hasData(response.data))
					{
						let result = CoreRESTClient.getData(response.data);
						this.$refs.lehreTable.tabulator.setData(result);
					}
					else
					{
						this.$refs.lehreTable.tabulator.setData([]);
						this.$fhcAlert.alertWarning("Keine Daten vorhanden");
					}
				}
				else
				{
					this.$refs.lehreTable.tabulator.setData([]);
					this.$fhcAlert.alertWarning("Keine Daten vorhanden");
				}
			});
		},
	},

	template: `
	<core-filter-cmpt
			ref="lehreTable"
			:tabulator-options="tabulatorOptions"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
	></core-filter-cmpt>
	`
};
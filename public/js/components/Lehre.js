import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {formatter} from "../mixins/formatters";


export const Lehre = {
	components: {
		CoreFilterCmpt,
	},
	data: function() {
		return {
			lehreViewTabulatorOptions: {
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
					{title: 'Zrm - DV', field: 'dv', headerFilter: false, formatter: formatter.dvFormatter, visible: false},
					{title: 'Zrm - Stunden', field: 'dv.stunden', headerFilter: false, formatter: formatter.stundenFormatter, visible: false, hozAlign:"right"},
					{title: 'Zrm - Stundensatz', field: 'stundensaetze_lehre', headerFilter: false, formatter: formatter.stundensatzLehre, visible: false, hozAlign:"right", tooltip: formatter.stundensatzLehreToolTip},

					{title: 'Semesterstunden', field: 'lektor_stunden', headerFilter: true, bottomCalc: "sum", bottomCalcParams:{precision:2},visible: true, hozAlign:"right"},
					{title: 'LE Stundensatz', field: 'le_stundensatz', headerFilter: true, hozAlign:"right"},
					{title: 'Akt - DV', field: 'aktuelles_dv.bezeichnung', headerFilter: false, formatter: formatter.aktDVFormatter, visible: false},
					{title: 'Akt - Kostenstelle', field: 'aktuelles_dv.kststelle.orgbezeichnung', headerFilter: false, formatter: formatter.aktKostenstelleFormatter, visible: false},
					{title: 'Akt - Kostenstelle - Parent', field: 'aktuelles_dv.kststelle.parentbezeichnung', headerFilter: false, formatter: formatter.aktParentKostenstelleFormatter, visible: false},
					{title: 'Akt - Stunden', field: 'aktuelles_dv.stunden', headerFilter: false, formatter: formatter.aktStundenFormatter, visible: false, hozAlign:"right"},
					{title: 'Akt - Stundensatz - Lehre', field: 'stundensaetze_lehre_aktuell', headerFilter: false, formatter: formatter.aktStundensatzFormatter, hozAlign:"right", tooltip: formatter.aktStundensatzTooltip},

				],
			},
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
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
	></core-filter-cmpt>
	`
};
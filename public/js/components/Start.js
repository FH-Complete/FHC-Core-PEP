import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import {Button} from './Button.js';
import {formatter} from "../mixins/formatters";

export const Start = {
	components: {
		CoreFilterCmpt,
		ButtonCmpt : Button
	},
	data: function() {
		return {
			changedData : {},
			configs: [],
			startOptions: {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				selectable: false,
				placeholder: "Keine Daten verfügbar",
				headerFilter: true,

				rowFormatter: function(row) {
					var data = row.getData();
					var element = row.getElement();
					if (data.karenz !== false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function(cell)
						{
							if(!cell.getColumn().getDefinition().bottomCalc)
							{
								cell.getElement().style.color = "#0c5460";
							}
						});
					}
				},
				columns: [
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'UID', field: 'uid', headerFilter: true, visible: false},
					{title: 'Zeitraum',  columns:[
							{title: 'DV', field: 'dv', headerFilter: false, formatter: formatter.dvFormatter, tooltip: ""},
							{title: 'Stunden', field: 'dv.stunden', hozAlign:"right", headerFilter: false, formatter: formatter.stundenFormatter, tooltip: formatter.stundenFormatterToolTip},
							{title: 'Stunden/Jahr', field: 'dv.stunden.jahresstunden', hozAlign:"right", headerFilter: false, formatter: formatter.stundenJahrFormatter, tooltip: formatter.stundenJahrFormatterTooltip}
						]
					},
					{title: 'Aktuell',  columns:[
							{title: 'Akt - DV', field: 'aktuelles_dv.bezeichnung', headerFilter: false, formatter: formatter.aktDVFormatter, visible: false},
							{title: 'Akt - Kostenstelle', field: 'aktuelles_dv.kststelle.bezeichnung', headerFilter: false, formatter: formatter.aktKostenstelleFormatter, visible: false},
							{title: 'Akt - Kostenstelle - Parent', field: 'aktuelles_dv.kststelle.parentbezeichnung', headerFilter: false, formatter: formatter.aktParentKostenstelleFormatter, visible: false},
							{title: 'Akt - Stunden', field: 'aktuelles_dv.stunden', hozAlign:"right", headerFilter: false, formatter: formatter.aktStundenFormatter, tooltip: formatter.aktStundenFormatterTooltip, visible: false},
							{title: 'Akt - Stundensatz - Lehre', field: 'stundensaetze_lehre_aktuell', hozAlign:"right", headerFilter: false, formatter: formatter.aktStundensatzFormatter, tooltip: formatter.datumFormatter, visible: false},
						]
					},
					{title: 'Karenz', field: 'karenz', visible: false, formatter: formatter.karenzFormatter},
					{field: "studiensemester_0_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, title: 'Lehraufträge 1. Semester'},
					{field: "studiensemester_1_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, title: 'Lehraufträge 2. Semester'},
					{title: 'Summe', formatter: formatter.berechneSumme, field: 'summe', hozAlign:"right", bottomCalc: formatter.berechneSummeBottom, bottomCalcFormatter: formatter.bottomCalcFormatter, bottomCalcParams:{precision:2},visible: true}
				],
			}
		}
	},
	mounted() {
		this.$refs.startTable.tabulator.on("cellEdited", (cell) => {
			let column = cell.getField();
			let row = cell.getRow();
			let value = cell.getValue();
			let uid = row.getData().uid;

			if (column.includes("kategorie"))
			{
				let teile = column.split("_");
				let semester = teile[1];
				semester = (this.configs['semester'][semester]);
				let kategorie_id = parseInt(teile[3]);

				if (value !== "" && !isNaN(value))
				{
					if (!this.changedData[uid])
					{
						this.changedData[uid] = [];
					}

					this.changedData[uid].push({
						kategorie: kategorie_id,
						semester: semester,
						stunden: value
					});

					this.$refs.startTable.tabulator.redraw(true);
				}
			}
		});
	},
	methods: {
		saveButtonClick: function() {
			this.saveData();
		},
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		setTableData(data) {
			this.changedData = {};
			const lastElement = data[data.length - 1];

			if (lastElement['configs'] !== undefined)
			{
				this.configs = lastElement['configs'];
				data.pop();

				this.configs.semester.forEach((studiensemester, key) => {

					this.configs.kategorien.forEach(kategorie => {
						let existingColumns = this.$refs.startTable.tabulator.getColumns().map(column => column.getField());

						let newColumns = {
							title: kategorie.beschreibung + " " + studiensemester,
							field: "studiensemester_" + key + "_kategorie_" + kategorie.kategorie_id,
							editor: "input",
							hozAlign:"right",
							tooltip: "<i class='fa fa-edit'></i>",
							formatter: function (cell, formatterParams, onRendered) {
								var value = cell.getValue();
								if (value !== "" && !isNaN(value))
								{
									value = parseFloat(value).toFixed(2);
									return value;
								}
							},
						};

						if (!existingColumns.includes(newColumns.field))
						{
							this.$refs.startTable.tabulator.addColumn(newColumns, true, "summe");
						}
						else
						{
							this.$refs.startTable.tabulator.updateColumnDefinition(newColumns.field, {title: newColumns.title});
						}
					});
					this.$refs.startTable.tabulator.updateColumnDefinition('studiensemester_' + key + '_lehrauftrag', {title: "Lehrauftraege " + studiensemester});
				});

				if (this.configs.semester[1] === undefined)
				{
					this.configs.kategorien.forEach(kategorie => {

						let columnField = "studiensemester_1_kategorie_" + kategorie.kategorie_id;

						let existingColumns = this.$refs.startTable.tabulator.getColumns().map(column => column.getField());
						if (existingColumns.includes(columnField))
							this.$refs.startTable.tabulator.deleteColumn(columnField);
					});

					this.$refs.startTable.tabulator.hideColumn('studiensemester_1_lehrauftrag');
				}
				else
				{
					this.$refs.startTable.tabulator.showColumn('studiensemester_1_lehrauftrag');
				}
			}
			this.$refs.startTable.tabulator.setData(data);
		},
		saveTableData()
		{
			if (Object.keys(this.changedData).length === 0)
				return
			CoreRESTClient.post(
				'/extensions/FHC-Core-PEP/components/PEP/save',
				this.changedData
			).then(
				result => {
					// display errors
					if (CoreRESTClient.isError(result.data))
					{
						this.$fhcAlert.handleSystemMessage(result.data.retval);
					}
					else
					{
						this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					}
				}
			).catch(
				error => {
					let errorMessage = error.message ? error.message : 'Unknown error';
					this.errors.push('Error when saving software: ' + errorMessage);
				}
			);
		},
	},
	template: `
		<core-filter-cmpt
			ref="startTable"
			:tabulator-options="startOptions"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
		></core-filter-cmpt>
	`
};
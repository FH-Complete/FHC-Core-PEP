import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import {formatter} from "../mixins/formatters";

export default {
	name: "Start",
	props: {
		config: null,
		modelValue: null,
		currentTab: ''
	},
	components: {
		CoreFilterCmpt
	},
	async beforeCreate() {
		await this.$p.loadCategory(['global', 'lehre', 'person', 'ui', 'international']);
	},
	computed: {
		tabulatorOptions()
		{
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				selectable: false,
				placeholder: "Keine Daten verfügbar",
				rowFormatter: function(row) {
					var data = row.getData();
					var element = row.getElement();
					if (data.karenz !== false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function (cell) {
							if (!cell.getColumn().getDefinition().bottomCalc) {
								cell.getElement().style.color = "#0c5460";
							}
						});
					}
				},
				columns: [
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'UID', field: 'uid', headerFilter: true, visible: false},
					{title: 'Karenz', field: 'karenz', visible: false, formatter: formatter.karenzFormatter, headerFilter:"input"},
					{title: 'Zrm - DV', field: 'vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'wochenstundenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - DV', field: 'aktbezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - Kostenstelle', field: 'aktorgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Kostenstelle - Parent', field: 'aktparentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'aktstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'stundensaetze_lehre_aktuell', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},
					{field: "studiensemester_0_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, headerFilter:"input", title: 'Lehraufträge 1. Semester', formatter: formatter.checkLehrauftraegeStunden},
					{field: "studiensemester_1_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, headerFilter:"input", title: 'Lehraufträge 2. Semester', formatter: formatter.checkLehrauftraegeStunden},
					{title: 'Summe', formatter: formatter.berechneSumme, field: 'summe', hozAlign:"right", bottomCalc: formatter.berechneSummeBottom, bottomCalcFormatter: formatter.bottomCalcFormatter, bottomCalcParams:{precision:2},visible: true}
				],
			}
		}
	},
	methods: {
		async loadData(data)
		{
			await Vue.$fhcapi.Category.getStart(data).then(response => {
				if (CoreRESTClient.isSuccess(response.data))
				{
					if (CoreRESTClient.hasData(response.data))
					{
						let result = CoreRESTClient.getData(response.data);
						this.setTableData(result);
					}
					else
					{
						this.$fhcAlert.alertWarning("Keine Daten vorhanden");
						this.$refs.startTable.tabulator.setData([]);
					}

				}
			});
		},
		saveButtonClick: function() {
			this.saveData();
		},
		updateValue(newValue) {
			this.$emit('update:modelValue', newValue);
		},
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		setTableData(data) {
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
							headerFilter: "input",
							hozAlign: "right",
							bottomCalc: 'sum',
							bottomCalcParams: {precision:2},
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
			:tabulator-options="tabulatorOptions"
			@nw-new-entry="newSideMenuEntryHandler"
			:table-only=true
			:hideTopMenu=false
		></core-filter-cmpt>
	`
};
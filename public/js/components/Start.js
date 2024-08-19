import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import {formatter} from "../mixins/formatters";

export default {
	name: "Start",
	props: {
		config: null,
		modelValue: {
			type: Object,
			required: true
		},
	},
	components: {
		CoreFilterCmpt,
		CoreBaseLayout
	},
	data()
	{
		return {
			categoriesConfig: {},
			semester: [],
		}
	},

	computed: {
		tabulatorOptions()
		{
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: function(row) {
					var data = row.getData();
					var element = row.getElement();
					if (data.karenz !== false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function (cell) {
							if (!cell.getColumn().getDefinition().bottomCalc) {
								cell.getElement().style.color = "#ABBD06FF";
							}
						});
					}
				},
				persistenceID: "pep_start",
				columns: [
					{
						formatter: 'rowSelection',
						titleFormatter: 'rowSelection',
						titleFormatterParams: {
							rowRange: "active"
						},
						headerSort: false,
						width: 70
					},
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
					{field: "studiensemester_0_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, headerFilter:"input", title: 'Lehraufträge 1. Semester', formatter: formatter.checkLehrauftraegeStunden, formatterParams:{precision:2}},
					{field: "studiensemester_1_lehrauftrag", hozAlign:"right", bottomCalc: 'sum', bottomCalcParams:{precision:2}, headerFilter:"input", title: 'Lehraufträge 2. Semester', formatter: formatter.checkLehrauftraegeStunden, formatterParams:{precision:2}},
					{title: 'Offene Stunden', formatter: formatter.berechneSumme, field: 'summe', hozAlign:"right", bottomCalc: formatter.berechneSummeBottom, bottomCalcFormatter: formatter.bottomCalcFormatter, bottomCalcParams:{precision:2},visible: true}
				],
			}
		},
		theModel: {
			get() {
				return this.modelValue;
			},
			set(value) {
				this.$emit('update:modelValue', value);
			}
		}
	},
	created() {


		/*	this.$nextTick(() => {
				this.theModel = { ...this.modelValue, loadDataReady: true };
			});*/
	},
	methods: {
		async loadData(data)
		{
			this.semester = data.semester;
			await this.loadColumns();

			await this.$fhcApi.factory.pep.getStart(data)
				.then(response => {
					this.$refs.startTable.tabulator.setData(response.data)
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},

		async loadColumns() {
			const tabulatorInstance = this.$refs.startTable.tabulator;
			let existingColumns = tabulatorInstance?.getColumns().map(column => column.getField());

			this.semester.forEach((studiensemester, key) => {
				this.categoriesConfig.forEach(kategorie => {
					const fieldKey = `studiensemester_${key}_kategorie_${kategorie.kategorie_id}`;
					const newColumn = {
						title: `${kategorie.beschreibung} ${studiensemester}`,
						field: fieldKey,
						headerFilter: "input",
						hozAlign: "right",
						bottomCalc: 'sum',
						bottomCalcParams: { precision: 2 },
						formatterParams: { precision: 2 },
						visible: true,
						formatter: (cell) => {
							const value = cell.getValue();
							return !isNaN(value) ? parseFloat(value).toFixed(2) : "0.00";
						}
					};

					if (!existingColumns.includes(fieldKey))
					{
						tabulatorInstance.addColumn(newColumn, true, "summe");
					}
					else
					{
						tabulatorInstance.updateColumnDefinition(fieldKey, { title: newColumn.title, visible: true });
					}
				});

				tabulatorInstance.updateColumnDefinition(`studiensemester_${key}_lehrauftrag`, {
					title: `Lehrauftraege ${studiensemester}`
				});
			});

			if (!this.semester[1]) {
				this.categoriesConfig.forEach(kategorie => {
					const fieldKey = `studiensemester_1_kategorie_${kategorie.kategorie_id}`;

					if (existingColumns.includes(fieldKey)) {
						tabulatorInstance.deleteColumn(fieldKey);
					}
				});

				tabulatorInstance.hideColumn('studiensemester_1_lehrauftrag');
			} else {
				tabulatorInstance.showColumn('studiensemester_1_lehrauftrag');
			}
		},

		async loadColumns2()
		{
			this.theModel?.config?.semester?.forEach((studiensemester, key) => {
				this.categoriesConfig.forEach(kategorie => {
					let existingColumns = this.$refs.startTable.tabulator.getColumns().map(column => column.getField());

					let newColumns = {
						title: kategorie.beschreibung + " " + studiensemester,
						field: "studiensemester_" + key + "_kategorie_" + kategorie.kategorie_id,
						headerFilter: "input",
						hozAlign: "right",
						bottomCalc: 'sum',
						bottomCalcParams: {precision:2},
						formatterParams:{precision:2},
						visible:true,
						formatter: function (cell, formatterParams, onRendered) {
							var value = cell.getValue();
							if (value !== "" && !isNaN(value))
							{
								return parseFloat(value).toFixed(formatterParams.precision);
							}
							else
								return parseFloat(0).toFixed(formatterParams.precision);
						},
					};

					if (!existingColumns.includes(newColumns.field))
					{
						this.$refs.startTable.tabulator.addColumn(newColumns, true, "summe");
					}
					else
					{
						this.$refs.startTable.tabulator.updateColumnDefinition(newColumns.field, {title: newColumns.title, visible:true});
					}
				});

				this.$refs.startTable.tabulator.updateColumnDefinition('studiensemester_' + key + '_lehrauftrag', {title: "Lehrauftraege " + studiensemester});
			});

			if ((Array.isArray(this.theModel?.config?.semester)))
			{
				if (this.theModel?.config?.semester[1] === undefined)
				{
					this.categoriesConfig.forEach(kategorie => {

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
		},
		async getCategoriesConfig()
		{
			if (Object.keys(this.categoriesConfig).length !== 0)
				return;
			await this.$fhcApi.factory.pep.getCategories()
				.then(response => {
					this.categoriesConfig = response.data
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		tableBuilt(){
			this.getCategoriesConfig().then(() => this.theModel = { ...this.modelValue, loadDataReady: true })

		}
	},
	template: `
		<core-base-layout>
			<template #main>
			<h5>{{$p.t('lehre', 'studiensemester')}}: {{semester?.join(', ')}}</h5>
				<core-filter-cmpt
					ref="startTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }]"
					:table-only=true
					:side-menu="false"
					:hideTopMenu=false
				></core-filter-cmpt>
			</template>
		</core-base-layout>
		
	`
};
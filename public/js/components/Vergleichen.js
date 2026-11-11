import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import {formatter} from "../mixins/formatters.js";
import ApiStart from "../api/start.js";



export default {
	name: "Vergleich",
	components: {
		CoreFilterCmpt,
		CoreBaseLayout
	},
	data()
	{
		return {
			categoriesConfig: {},
			columnsConfig: {},
			semester: [],
			projectsConfig: {},
			loadedData: {},
			configLoaded: false,
		}
	},

	created()
	{
		this.getCategoriesConfig();
	},
	computed: {
		generalPresets()
		{
			return [{
				id: null,
				name: "Standard",
				displayedColumns: this.tabulatorOptions.columns
					.filter(column => column.field && column.visible !== false)
					.map(column => column.field),
				headerFilters: [],
				sort: null,
			}];
		},
		tabulatorOptions()
		{
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: function(row) {
					var data = row.getData();
					var element = row.getElement();
					if (data.karenz === false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function (cell) {
							if (!cell.getColumn().getDefinition().bottomCalc) {
								cell.getElement().style.color = "#ABBD06FF";
							}
						});
					}
				},
				persistenceID: "2026_11_10_pep_vergleich",
				columns: [
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'UID', field: 'uid', headerFilter: true, visible: false},
					{title: 'Karenz', field: 'karenz', visible: false, formatter: formatter.karenzFormatter, headerFilter:"input"},
					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},

					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'akt_stundensaetze_lehre', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},
					...this.buildConfigColumns(),
					{
						title: 'Offene Stunden',
						formatter: formatter.berechneSumme,
						field: 'summe',
						hozAlign:"right",
						bottomCalc: "sum",
						bottomCalcFormatter: formatter.bottomCalcFormatter,
						bottomCalcParams: {precision:2},
						visible: true
					},
					{
						title: 'Verplante Stunden',
						formatter: formatter.berechneSummeVerplant,
						field: 'summeverplant',
						hozAlign:"right",
						bottomCalc: formatter.berechneSummeBottomVerplant,
						bottomCalcParams: {precision:2},
						visible: true
					}
				],
			}
		},
	},
	methods: {
		async loadData(data)
		{
			await this.loadColumns(data.semester);
			await this.$api.call(ApiStart.getStart(data))
				.then(response => {
					this.$refs?.vergleichTable.tabulator.setData(response.data)
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		loadColumns(semester)
		{
			const tabulatorInstance = this.$refs?.vergleichTable.tabulator;

			if (this.columnsConfig.lehrauftraege) {
				semester.forEach((studiensemester, index) => {
					const fieldKey = `studiensemester_${index}_lehrauftrag`;
					const title = `Lehraufträge ${studiensemester}`;
					tabulatorInstance.updateColumnDefinition(fieldKey, { title: title, visible: true });
				});

				if (semester.length < 2) {
					tabulatorInstance.hideColumn('studiensemester_1_lehrauftrag');
				}
			}

			if (this.columnsConfig.projects) {
				semester.forEach((studiensemester, index) => {
					const fieldKey = `studiensemester_${index}_project`;
					const title = `Projekte ${studiensemester}`;
					tabulatorInstance.updateColumnDefinition(fieldKey, { title: title, visible: true });
				});

				if (semester.length < 2) {
					tabulatorInstance.hideColumn('studiensemester_1_project');
				}
			}

			if (this.columnsConfig.categories) {
				this.columnsConfig.categories.forEach(kategorie => {
					semester.forEach((studiensemester, index) => {
						const fieldKey = `studiensemester_${index}_kategorie_${kategorie.kategorie_id}`;
						const title = `${kategorie.beschreibung} ${studiensemester}`;
						tabulatorInstance.updateColumnDefinition(fieldKey, { title: title, visible: true });
						if (semester.length < 2) {
							const fieldKey = `studiensemester_1_kategorie_${kategorie.kategorie_id}`;
							tabulatorInstance.hideColumn(fieldKey);
						}
					});
				});
			}
		},
		createColumns(configKey, titlePrefix, cellFormatter, category = null)
		{
			return [0, 1].map(i => {
				const semester = i === 0 ? 'WS' : 'SS';
				const fieldKey = category
					? `studiensemester_${i}_kategorie_${category.kategorie_id}`
					: `studiensemester_${i}_${configKey}`;

				return this.createColumnDefinition(`${titlePrefix} ${semester}`, fieldKey, cellFormatter);
			});
		},
		createColumnDefinition(title, fieldKey, cellFormatter)
		{
			return {
				title: title,
				field: fieldKey,
				hozAlign: "right",
				bottomCalc: 'sum',
				bottomCalcParams: { precision: 2 },
				headerFilter: "input",
				formatter: cellFormatter,
				formatterParams: { precision: 2 },
				visible: true,
			};
		},
		buildConfigColumns()
		{
			const config = this.columnsConfig;
			const columns = [];

			if (config.lehrauftraege)
				columns.push(...this.createColumns('lehrauftrag', 'Lehraufträge', formatter.checkLehrauftraegeStunden));

			if (config.projects)
				columns.push(...this.createColumns('project', 'Projekte', formatter.checkStunden));

			if (config.categories)
			{
				config.categories.forEach(kategorie => {
					columns.push(...this.createColumns('kategorie', kategorie.beschreibung, formatter.checkStunden, kategorie));
				});
			}

			return columns;
		},
		async getCategoriesConfig()
		{
			await this.$api.call(ApiStart.getCategories())
				.then(response => {
					this.columnsConfig = response.data;
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
			this.configLoaded = true;
		},
		tableBuilt()
		{
			this.theModel = { ...this.modelValue, loadDataReady: true };
		},
	},
	template: `
		<core-base-layout>
			<template #main>
			<h5>{{$p.t('lehre', 'studiensemester')}}: {{semester?.join(', ')}}</h5>
				<core-filter-cmpt
					v-if="configLoaded"
					ref="vergleichTable"
					:tabulator-options="tabulatorOptions"
					:isUsingPresets="true"
					presetsId="pepVergleichTable"
					:general-presets="generalPresets"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }]"
					:table-only=true
					:side-menu="false"
				>
				<template #actions></template>
				</core-filter-cmpt>
			</template>
		</core-base-layout>
	`
};
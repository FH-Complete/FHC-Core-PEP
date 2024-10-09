import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import {formatter} from "../mixins/formatters";
import FhcLoader from '../../../../js/components/Loader.js';


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
		CoreBaseLayout,
		FhcLoader
	},
	watch: {
		loadedData: {
			handler(newValue) {

				console.log(newValue);
				if (newValue.oldSemester)
				{
					this.oldStudiensemester = this.semester
						.filter(item => item.startsWith("SS"))
						.map(item => {
							const jahr = parseInt(item.slice(-4)) - 1;
							return `<span style="color: red"> (SS${jahr})</span>`;
						});
				}
				else
				{
					this.oldStudiensemester = "";
				}
			},
			deep: true
		},

	},
	data()
	{
		return {
			categoriesConfig: {},
			columnsConfig: {},
			semester: [],
			projectsConfig: {},
			loadedData: {},
			oldStudiensemester: "",
			isOldSemesterLoaded: false
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
					if (data.karenz === false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function (cell) {
							if (!cell.getColumn().getDefinition().bottomCalc) {
								cell.getElement().style.color = "#ABBD06FF";
							}
						});
					}
				},
				persistenceID: "2024_10_09_pep_start",
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
					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},

					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - Kostenstelle', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Kostenstelle - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'akt_stundensaetze_lehre', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},
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
		theModel: {
			get() {
				return this.modelValue;
			},
			set(value) {
				this.$emit('update:modelValue', value);
			}
		}
	},
	methods: {
		async loadData(data)
		{
			if (this.loadedData.studienjahr !== data.studienjahr)
				await this.loadStudiensemester(data.studienjahr)
			data.oldSemester = this.loadedData.oldSemester;
			this.loadedData = data;
			await this.$fhcApi.factory.pep.getStart(data)
				.then(response => {
					this.$refs?.startTable.tabulator.setData(response.data)
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		async loadStudiensemester(studienjahr)
		{
			let data = {
				'studienjahr': studienjahr
			}
			await this.$fhcApi.factory.pep.getStudiensemester(data)
				.then(response => {
					this.semester = response.data
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		loadColumns(semester)
		{
			const tabulatorInstance = this.$refs?.startTable.tabulator;

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
		createColumns(configKey, titlePrefix, formatter, mode = 'studiensemester', category = null)
		{
			if (mode === 'studiensemester')
			{
				for (let i = 0; i < 2; i++) {
					let semester = i === 0 ? 'WS' : 'SS';

					const fieldKey = category
						? `studiensemester_${i}_kategorie_${category.kategorie_id}`
						: `studiensemester_${i}_${configKey}`;

					const title = `${titlePrefix} ${semester}`;
					this.addColumn(title, fieldKey, formatter);
				}
			}
			else
			{
				const fieldKey = category
					? `studiensemester_kategorie_${category.kategorie_id}`
					: `studiensemester_${configKey}`;

				const title = `${titlePrefix}`;
				this.addColumn(title, fieldKey, formatter);
			}
		},
		addColumn(title, fieldKey, formatter)
		{
			const tabulatorInstance = this.$refs?.startTable.tabulator;
			const newColumn = {
				title: title,
				field: fieldKey,
				hozAlign: "right",
				bottomCalc: 'sum',
				bottomCalcParams: { precision: 2 },
				headerFilter: "input",
				formatter: formatter,
				formatterParams: { precision: 2 },
				visible: true,
			};
			tabulatorInstance.addColumn(newColumn, true, "summe");
		},
		getOldSemester(e) {
			this.isOldSemesterLoaded = !this.isOldSemesterLoaded;
			this.loadedData = {...this.loadedData, oldSemester: this.isOldSemesterLoaded}

			this.$refs.loader.show();
			this.loadData(this.loadedData).then(() => {this.$refs.loader.hide();});
		},
		addColumns()
		{
			if (this.columnsConfig.lehrauftraege)
			{
				this.createColumns('lehrauftrag', 'Lehraufträge', formatter.checkLehrauftraegeStunden);
				/*.then(() => {
					var column = this.$refs?.startTable.tabulator.getColumn('studiensemester_1_lehrauftrag');
					if (column)
					{
						let oldTitle = column.getDefinition().title;
						const newTitle = `${oldTitle} <input id='select-old-ss' type='checkbox' title='altes Sommersemester laden'>`;
						let elements = document.getElementsByClassName('tabulator-col-title');
						const matchingElement = Array.from(elements).find(element => element.textContent.includes(oldTitle));
						matchingElement.innerHTML = newTitle;
						const checkbox = document.getElementById("select-old-ss");
						if (checkbox)
						{
							checkbox.addEventListener("click", this.getOldSemester);
						}
					}
				});*/
			}

			if (this.columnsConfig.projects)
			{
				this.createColumns('project', 'Projekte', formatter.checkStunden, this.columnsConfig.mode.projects);
			}

			if (this.columnsConfig.categories)
			{
				this.columnsConfig.categories.forEach(kategorie => {
					this.createColumns('kategorie', kategorie.beschreibung, formatter.checkStunden, this.columnsConfig.mode.categories, kategorie);
				});
			}
		},
		async getCategoriesConfig()
		{
			if (Object.keys(this.columnsConfig).length !== 0)
				return;
			await this.$fhcApi.factory.pep.getCategories()
				.then(response => {
					this.columnsConfig = response.data
				})
				.then(() => this.addColumns())
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		tableBuilt()
		{
			this.getCategoriesConfig()
				.then(() => this.theModel = { ...this.modelValue, loadDataReady: true })
		},
	},
	template: `
		<core-base-layout>
			<template #main>
			<h5>{{$p.t('lehre', 'studiensemester')}}: {{semester?.join(', ')}} 
				<button class="btn btn-outline-secondary btn-sm" @click="getOldSemester" title="Betrifft nur die Spalte 'Lehraufträge SS'">
					<i :class="isOldSemesterLoaded ? 'fas fa-redo' : 'fa fa-history'"></i>
					{{isOldSemesterLoaded ? 'Ausgewähltes SS laden' : 'Vorheriges SS laden'}}
				</button> 
				<span v-html="oldStudiensemester"></span>
			</h5>
				<core-filter-cmpt
					ref="startTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }]"
					:table-only=true
					:side-menu="false"
					:hideTopMenu=false
				>
				<template #actions></template>
				</core-filter-cmpt>
			</template>
		</core-base-layout>
		<fhc-loader ref="loader" :timeout="0"></fhc-loader>
	`
};
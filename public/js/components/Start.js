import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import {formatter} from "../mixins/formatters.js";
import tagMixin from "../mixins/tag.js";
import FhcLoader from '../../../../js/components/Loader.js';
import Tag from '../../../../js/components/Tag/Tag.js';
import { tagHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import { extendedHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import { addTagInTable, deleteTagInTable, updateTagInTable } from "../../../../js/helpers/TagHelper.js";

import ApiStartTag from "../api/startTabTags.js";
import ApiStart from "../api/start.js";

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
		FhcLoader,
		Tag,
	},
	mixins: [tagMixin],
	watch: {
		loadedData: {
			handler(newValue) {
				if (newValue.oldSemester)
				{
					this.oldStudiensemester = this.semester
						.filter(item => item.startsWith("SS"))
						.map(item => {
							const jahr = parseInt(item.slice(-4)) - 1;
							return `<span style="color: red"> (SS${jahr})</span>`;
						});

					let column = this.$refs?.startTable.tabulator.getColumn('studiensemester_1_lehrauftrag');
					if (column)
					{
						column.getElement().classList.add("highlight-alert");
					}
				}
				else
				{
					this.oldStudiensemester = "";
					let column = this.$refs?.startTable.tabulator.getColumn('studiensemester_1_lehrauftrag');
					if (column)
					{
						column.getElement().classList.remove("highlight-alert");
					}
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
			isOldSemesterLoaded: false,
			selectedColumnValues: [],
			tagEndpoint: ApiStartTag,
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
				layout: 'fitDataStretch',
				height: '60vh',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: function(row) {
					let data = row.getData();
					let element = row.getElement();
					if (data.karenz === false && !element.classList.contains('calcs-bottom'))
					{
						row.getCells().forEach(function (cell) {
							if (!cell.getColumn().getDefinition().bottomCalc) {
								cell.getElement().style.color = "#ABBD06FF";
							}
						});
					}
				},
				persistenceID: "2026_11_11_pep_start",
				persistence: true,
				columnDefaults: {
					headerFilterFunc: extendedHeaderFilter,
					tooltip: true
				},
				columns: [
					{
						title: "Auswahl",
						field: 'rowSelection',
						formatter: 'rowSelection',
						titleFormatter: 'rowSelection',
						titleFormatterParams: {
							rowRange: "active"
						},
						headerSort: false,
						width: 40
					},
					{
						title: 'Tags',
						field: 'tags',
						tooltip: false,
						headerFilter: true,
						headerFilterFunc: tagHeaderFilter,
						formatter: (cell) => formatter.tagFormatter(cell, this.$refs.tagComponent),
						width: 150,
					},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true, visible: false},
					{title: 'Karenz', field: 'karenz', visible: false, formatter: formatter.karenzFormatter, headerFilter:"input"},
					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", headerTooltip: "Zeitraum - Dienstverhältnis"},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", headerTooltip: "Zeitraum - Stunden pro Woche"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea",  headerTooltip: "Zeitraum - Stunden pro Jahr"},

					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'akt_stundensaetze_lehre', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},
					...this.buildConfigColumns(),
					{
						title: 'Offene Stunden',
						mutator: formatter.mutatorBerechneSumme,
						formatter: formatter.berechneSumme,
						field: 'summe',
						headerFilter: true,
						hozAlign:"right",
						bottomCalc: "sum",
						bottomCalcFormatter: formatter.bottomCalcFormatter,
						bottomCalcParams: {precision:2},
						visible: true,
						sorter:"number",
					},
					{
						title: 'Verplante Stunden',
						headerFilter: true,
						mutator: formatter.mutatorBerechneSummeVerplant,
						formatter: formatter.berechneSummeVerplant,
						field: 'summeverplant',
						hozAlign:"right",
						bottomCalc: "sum",
						bottomCalcParams: {precision:2},
						visible: true,
						sorter:"number",
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
		updateSelectedRows() {
			this.selectedRows = this.$refs.startTable.tabulator.getSelectedRows();
			this.selectedColumnValues = this.selectedRows.map(row => row.getData().uid);
			this.addColorToInfoText(this.selectedColumnValues);
		},
		lektorMail()
		{
			const selectedRows = this.$refs.startTable.tabulator.getSelectedRows();
			let emails = []
			selectedRows.forEach(row => {
				let rowData = row.getData()

				if (!emails.includes(rowData.email))
					emails.push(rowData.email)
			})
			window.location.href = `mailto:${emails}`;
		},

		addedTag(addedTag) {
			addTagInTable(addedTag, this.$refs.startTable.tabulator.getRows(), 'mitarbeiter_uid')
		},
		deletedTag(deletedTag) {
			deleteTagInTable(deletedTag, this.$refs.startTable.tabulator.getRows())
		},
		updatedTag(updatedTag) {
			updateTagInTable(updatedTag, this.$refs.startTable.tabulator.getRows())
		},
		async loadData(data)
		{
			if (this.loadedData.studienjahr !== data.studienjahr)
				await this.loadStudiensemester(data.studienjahr)
			data.oldSemester = this.loadedData.oldSemester;
			this.loadedData = data;
			await this.$api.call(ApiStart.getStart(data))
				.then(response => {
					if (response.data.length === 0)
					{
						this.$fhcAlert.alertInfo("Start: Keine Daten vorhanden");
						this.$refs.startTable.tabulator.setData([]);
					}
					else
						this.$refs.startTable.tabulator.setData(response.data);
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
			await this.$api.call(ApiStart.getStudiensemester(data))
				.then(response => {
					this.semester = response.data
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		createColumns(configKey, titlePrefix, cellFormatter, mode = 'studiensemester', category = null)
		{
			if (mode === 'studiensemester')
			{
				return [0, 1].map(i => {
					const semester = i === 0 ? 'WS' : 'SS';
					const fieldKey = category
						? `studiensemester_${i}_kategorie_${category.kategorie_id}`
						: `studiensemester_${i}_${configKey}`;

					return this.createColumnDefinition(`${titlePrefix} ${semester}`, fieldKey, cellFormatter);
				});
			}

			const fieldKey = category
				? `studiensemester_kategorie_${category.kategorie_id}`
				: `studiensemester_${configKey}`;

			return [this.createColumnDefinition(`${titlePrefix}`, fieldKey, cellFormatter)];
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
		getOldSemester(e) {
			this.isOldSemesterLoaded = !this.isOldSemesterLoaded;
			this.loadedData = {...this.loadedData, oldSemester: this.isOldSemesterLoaded}

			this.$refs.loader.show();
			this.loadData(this.loadedData).then(() => {this.$refs.loader.hide();});
		},
		buildConfigColumns()
		{
			const config = this.columnsConfig;
			const columns = [];

			if (config.lehrauftraege)
			{
				columns.push(...this.createColumns('lehrauftrag', 'Lehraufträge', formatter.checkLehrauftraegeStunden));
				columns.push(...this.createColumns('lehrauftrag_realstunden', 'Lehraufträge Realstunden', formatter.checkLehrauftraegeStunden));
			}

			if (config.projects)
				columns.push(...this.createColumns('project', 'Projekte', formatter.checkStunden, config.mode.projects));

			if (config.lventwicklung)
				columns.push(...this.createColumns('lv_entwicklung', 'LV-Entwicklung', formatter.checkStunden, config.mode.lventwicklung));

			if (config.categories)
			{
				config.categories.forEach(kategorie => {
					columns.push(...this.createColumns('kategorie', kategorie.beschreibung, formatter.checkStunden, config.mode.categories, kategorie));
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
			<h5>{{$p.t('lehre', 'studiensemester')}}: {{semester?.join(', ')}} 
				<button class="btn btn-outline-secondary btn-sm" @click="getOldSemester" title="Betrifft nur die Spalte 'Lehraufträge SS'">
					<i :class="isOldSemesterLoaded ? 'fas fa-redo' : 'fa fa-history'"></i>
					{{isOldSemesterLoaded ? 'Ausgewähltes SS laden' : 'Vorheriges SS laden'}}
				</button> 
				<span v-html="oldStudiensemester"></span>
			</h5>
				<core-filter-cmpt
					v-if="configLoaded"
					ref="startTable"
					:isUsingPresets="true"
					presetsId="pepStartTable"
					:general-presets="generalPresets"
					:download="config?.download"
					:tableOnly=false
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }, { event: 'rowSelectionChanged', handler: updateSelectedRows }]"
					:table-only=true
					:side-menu="false"
				>
				<template #actions>
				<button class="btn btn-primary" @click="lektorMail">EMail an Lektor</button>
				<Tag ref="tagComponent"
					:endpoint="tagEndpoint"
					:values="selectedColumnValues"
					@added="addedTag"
					@deleted="deletedTag"
					@updated="updatedTag"
				></Tag>
						</template>
				</core-filter-cmpt>
			</template>
			<fhc-loader ref="loader" :timeout="0"></fhc-loader>
		</core-base-layout>
	`
};
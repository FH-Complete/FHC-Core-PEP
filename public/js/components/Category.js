import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import { extendedHeaderFilter } from "../../../..//js/tabulator/filters/extendedHeaderFilter";

export default {
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
	data: function() {
		return{
			studienjahr: null,
			rowCount: {},
			columnsToMark: ['stunden', 'anmerkung', 'category_oe_kurzbz', 'mitarbeiter_uid'],
			tableData: [],
			mitarbeiterListe: {},
			orgListe: null
		}
	},
	computed: {
		tabulatorOptions() {
			return {
				index: "row_index",
				height: '60vh',
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				persistenceID: "2025_03_12_pep_kategorie_" + this.config.category_id,
				persistence: true,
				rowFormatter: (row) =>
				{
					if (row.getElement().classList.contains("tabulator-calcs"))
						return;

					let data = row.getData();
					let columns = row.getTable().getColumns();

					if (data.kategorie_mitarbeiter_id === null || data.newentry === false)
					{
						this.columnsToMark.forEach((spaltenName) => {
							let column = columns.find(col => col.getField() === spaltenName);
							if (column) {
								let cellElement = row.getCell(column).getElement();
								cellElement.classList.add("highlight-warning");
							}
						});
					}
				},
				columnDefaults: {
					headerFilterFunc: extendedHeaderFilter,
					tooltip: true
				},
				columns: [
					{
						formatter: 'rowSelection',
						titleFormatter: 'rowSelection',
						titleFormatterParams: {
							rowRange: "active"
						},
						headerSort: false,
						width: 40
					},
					{
						title: 'Aktionen',
						field: 'actions',
						width: 100,
						formatter: (cell, formatterParams, onRendered) => {

							if (cell.getData().disabled)
								return;
							if (cell.getData().editable === false)
								return;
							let container = document.createElement('div');
							container.className = "d-flex gap-2";

							let duplicateButton = document.createElement('button');
							duplicateButton.className = 'btn btn-outline-secondary';
							duplicateButton.innerHTML = '<i class="fa fa-plus"></i>';
							duplicateButton.addEventListener('click', (event) =>
								this.duplicateRow(cell)
							);
							container.append(duplicateButton);
							if ((cell.getData().kategorie_mitarbeiter_id !== null || cell.getData().newentry === true))
							{
								let deleteButton = document.createElement('button');
								deleteButton.className = 'btn btn-outline-secondary';
								deleteButton.innerHTML = '<i class="fa fa-minus"></i>';
								deleteButton.addEventListener('click', (event) => {
									deleteButton.disabled = true;
									duplicateButton.disabled = true;
									this.deleteRow(cell);
								});
								container.append(deleteButton);
							}

							return container;
						},
					},
					//{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true, visible:false},
					{
						title: 'UID',
						field: 'mitarbeiter_uid',
						headerFilter: true,
						editor: "list",
						width: 50,
						editorParams:() => {
							return {
								values: this.mitarbeiterListe,
								autocomplete: true,
								listOnEmpty:true,
							}
						},
						editable:(cell) => {
							return ((cell.getData().kategorie_mitarbeiter_id !== null || cell.getData().newentry === true))
						},
					},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea"},
					{
						title: 'Stunden',
						field: 'stunden',
						headerFilter: true,
						editor: "number",
						bottomCalcParams: {precision: 2},
						bottomCalc: "sum",
						hozAlign: "right",
						formatter: function (cell, formatterParams, onRendered)
						{



							var value = cell.getValue();
							if (value === null || isNaN(value) || value === "")
							{
								cell.setValue(0);
								return parseFloat(0).toFixed(2);
							}

							if (!isNaN(value))
							{
								value = parseFloat(value).toFixed(2);
								return value;
							}
						},
						validator: ["numeric", {
							type: function(cell, value, parameters)
							{

								if (value === "")
									return true;
								if (isNaN(value))
									return false;

								value = parseFloat(value);
								if (value.toFixed(2) != value)
									return false;

								if (value > 9999.99 || value < 0)
									return false;

								return true;
							},
						}]
					},
					{
						title: 'Organisation',
						field: 'category_oe_kurzbz',
						editor: "list",
						headerFilter: true,
						width: 400,
						headerFilterParams: { values: this.orgListe },
						editorParams:() => {
							return {
								values: this.orgListe,
								autocomplete: true,
								allowEmpty : true,
								clearable: true,
								listOnEmpty: true,
								dropdownAlign: "left",

							}
						},
						formatter: (cell, formatterParams, onRendered) => {
							const value = cell.getValue();
							return this.orgListe[value] || null;
						},
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", visible: true, editor: "textarea",
						formatter: "textarea"
					},

				]
			}
		},
		theModel: {
			get() {
				return this.modelValue;
			},
			set(value) {
				this.$emit('update:modelValue', value);
			}
		},
	},
	async created() {
		await this.fetchOrganisationen();
	},

	methods: {
		async loadData(data)
		{
			this.theModel.config.category_id = this.config.category_id
			data.category_id = this.config.category_id
			await this.$fhcApi.factory.pep.getCategory(data)
				.then(response => {
					this.$refs.categoryTable.tabulator.setData(response.data).then(() => this.getMitarbeiterListe(response.data));
					if (!this.rowCount[this.config.category_id])
					{
						this.rowCount[this.config.category_id] =  this.$refs.categoryTable.tabulator.getRows().length;
					}
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		async getMitarbeiterListe(data)
		{
			let mitarbeiter = {};

			data.forEach(row => {
				if (row.mitarbeiter_uid && row.vorname && row.nachname)
				{
					if (!(mitarbeiter[row.mitarbeiter_uid]))
						mitarbeiter[row.mitarbeiter_uid] = `${row.vorname} ${row.nachname}`;
				}
			});
			this.mitarbeiterListe = mitarbeiter;
		},
		async fetchOrganisationen()
		{
			await this.$fhcApi.factory.pep.getOrgForCategories()
				.then(response => {
					this.orgListe = response.data
						.reduce((acc, org) => {
							const orgName = `[${org.organisationseinheittyp_kurzbz}] ${org.bezeichnung}`;
							acc[org.oe_kurzbz] = org.aktiv ? orgName : `<s>${orgName}</s>`;
							return acc;
						}, {});
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		async resetHours()
		{
			if (this.$refs.categoryTable.tabulator.getRows().length == 0)
				return;
			this.theModel.config.category_id = this.config.category_id
			if (await this.$fhcAlert.confirm({
				message: 'Stunden für alle aus der Liste auf Standard zurücksetzen?',
				acceptLabel: 'Ja',
				acceptClass: 'btn btn-danger',
			}) === false)
				return;
			this.$fhcApi.factory.pep.stundenzuruecksetzen(this.theModel.config)
				.then(response => {
					if (response.data === true)
					{
						this.$fhcAlert.alertWarning("Stunden für das nächste Studienjahr sind bereits eingetragen!")
					}
					else
					{
						this.loadData(this.theModel.config);
						this.theModel = { ...this.modelValue, needReload: true };
						this.$fhcAlert.alertSuccess("Erfolgreich zurückgesetzt")
					}
				}).catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},

		onCellEdited(cell)
		{
			let value = cell.getValue();
			let field = cell.getField();
			let oldValue = cell.getOldValue();

			if (field === "stunden")
			{
				if (oldValue === null || oldValue === "")
				{
					return;
				}

				if (parseFloat(value).toFixed(2) === parseFloat(oldValue).toFixed(2))
					return;

				let row = cell.getRow();
				this.speichern(row);
			}
			else if (field === "anmerkung" || field === 'category_oe_kurzbz' || field === 'mitarbeiter_uid')
			{
				if ((value === "" || value === null) && (oldValue === "" || oldValue === null))
				{
					return;
				}

				if (value === oldValue)
				{
					return;
				}

				let row = cell.getRow();
				this.speichern(row);
			}
		},
		async speichern (row, remove = false) {

			let data = row.getData();
			data.studienjahr = this.theModel.config.studienjahr;
			data.kategorie = this.config.category_id;

			let oldindex = data.row_index;
			if (remove)
				data.delete = true;

			await this.$fhcApi.factory.pep.saveMitarbeiter(data)
				.then(response => {

					if (data.kategorie_mitarbeiter_id === null)
					{
						row.update({
							kategorie_mitarbeiter_id: response.data,
						})
						row.reformat();
					}
					else if (remove)
					{
						response.data.row_index = oldindex;
						response.data.delete = false;
						row.update(response.data)
					}

					if (!remove)
					{
						let children = row.getElement().childNodes;
						children.forEach((child) => {
							child.classList.add("highlight-success");
						})

						setTimeout(function(){
							children.forEach((child) => {
								child.classList.remove("highlight-success");
							})
						}, 1000);

						row.update(response.data.updated)
					}
					this.theModel = { ...this.modelValue, needReload: true };
				});
		},
		duplicateRow(cell)
		{
			let row = cell.getRow()
			let rowData = row.getData();
			if (!rowData.stunden || rowData.stunden === "0.00")
				return;

			let newData = { ...rowData };

			let count = this.uidCount(rowData.mitarbeiter_uid);

			if (count === 1 && rowData.kategorie_mitarbeiter_id === null)
			{
				this.speichern(row)
			}
			else
			{
				this.rowCount[this.config.category_id]++;
				newData.row_index = this.rowCount[this.config.category_id];
				newData.kategorie_mitarbeiter_id = null;
				newData.anmerkung = null;
				newData.newentry = true;

				this.$refs.categoryTable.tabulator.addRow(newData, false, cell.getRow());
				let newRow = this.$refs.categoryTable.tabulator.getRow(newData.row_index);
				this.speichern(newRow)
			}
		},
		deleteRow(cell)
		{
			let row = cell.getRow();
			let data = row.getData();

			let uid = data.mitarbeiter_uid;
			let index = data.row_index;

			let counts = this.uidCount(uid)

			this.speichern(row, true).then(response =>
				{
					let children = row.getElement().childNodes;
					children.forEach((child) => {
						child.classList.add("highlight-alert");
					})

					if (counts === 1)
					{
						row.update({
							kategorie_mitarbeiter_id: null,
							newentry: false,
							delete: false
						})

						setTimeout(function(){
							children.forEach((child) => {
								child.classList.remove("highlight-alert");
							})
							row.reformat();
						}, 200);
					}
					else
					{
						setTimeout(function(){
							row.delete();
						}, 200);
					}
				}
			)
		},
		uidCount(uid)
		{
			let count = 0;
			for (let row of this.$refs.categoryTable.tabulator.getRows())
			{
				if (row.getData().mitarbeiter_uid === uid)
				{
					count++;
				}
			}
			return count;
		},
		tableBuilt()
		{
			this.theModel = { ...this.modelValue, loadDataReady: true }
		}
	},
	template: `
		<core-base-layout>
			<template #main>
				<h5>{{$p.t('lehre', 'studienjahr')}}: {{theModel?.config?.studienjahr}}</h5>
				<core-filter-cmpt
					v-if="orgListe"
					ref="categoryTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited }, { event: 'tableBuilt', handler: tableBuilt }]"
					:table-only=true
					:side-menu="false"
					:countOnly="true">
					<template #actions>
						<button class="btn btn-danger btn-sm resetHoursButton" @click="resetHours">Stunden zurücksetzen</button>
					</template>
				</core-filter-cmpt>
			</template>
			
		</core-base-layout>
		
	`
};
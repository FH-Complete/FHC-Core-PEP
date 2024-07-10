import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';

export default {
	emits: [
		'component-loaded'
	],
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
		}
	},

	mounted() {
		this.$nextTick(() => {
			this.theModel = { ...this.modelValue, loadDataReady: true };
		});
	},
	computed: {
		tabulatorOptions() {
			return {
				index: "row_index",
				maxHeight: "100%",
				layout: 'fitDataStretch',
				selectable: false,
				placeholder: "Keine Daten verfügbar",
				columns: [
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

							let button = document.createElement('button');
							button.className = 'btn btn-outline-secondary';
							button.innerHTML = '<i class="fa fa-plus"></i>';
							button.addEventListener('click', (event) =>
								this.duplicateRow(cell)
							);
							container.append(button);
							if (
								(cell.getData().kategorie_mitarbeiter_id !== null || cell.getData().newentry === true))
							{
								button = document.createElement('button');
								button.className = 'btn btn-outline-secondary';
								button.innerHTML = '<i class="fa fa-minus"></i>';
								button.addEventListener('click', (event) =>
									this.deleteRow(cell)
								);
								container.append(button);
							}

							return container;
						},
					},
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true, visible:false},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'Zrm - DV', field: 'vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'wochenstundenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - Kostenstelle', field: 'aktorgbezeichnung', headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - Kostenstelle - Parent', field: 'aktparentbezeichnung', headerFilter: "input", formatter: "textarea"},
					{title: 'Stunden', field: 'stunden', headerFilter: true, editor: "input", bottomCalcParams: {precision:2}, bottomCalc: "sum", hozAlign: "right",
						formatter: function (cell, formatterParams, onRendered) {
							var value = cell.getValue();

							if (value === null || isNaN(value) || value === "")
							{
								return parseFloat(0).toFixed(2);
							}
							if (!isNaN(value))
							{
								value = parseFloat(value).toFixed(2);
								return value;
							}
						},
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", visible: true, editor: "textarea",
						formatter: "textarea"
					},
				],
				persistenceID: "pep_kategorie_" + this.config.category_id
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
		async loadData()
		{
			this.theModel.config.category_id = this.config.category_id
			await this.$fhcApi.factory.pep.getCategory(this.theModel.config)
				.then(response => {
					this.$refs.categoryTable.tabulator.setData(response.data);
					if (!this.rowCount[this.config.category_id])
					{
						this.rowCount[this.config.category_id] =  this.$refs.categoryTable.tabulator.getRows().length;
					}

				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		vorruecken()
		{
			this.theModel.config.category_id = this.config.category_id
			this.$fhcApi.factory.pep.stundenvoerruecken(this.theModel.config)
				.then(response => {
					if (response.data === true)
					{
						this.$fhcAlert.alertWarning("Stunden für das nächste Studienjahr sind bereits eingetragen!")
					}
					else
						this.$fhcAlert.alertSuccess("Erfolgreich vorgerueckt")
				}).catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},


		onCellEdited(cell)
		{

			if ((cell.getValue() === "" || cell.getValue() === "0") && (cell.getOldValue() === null || cell.getOldValue() === 0))
				return;

			let row = cell.getRow();
			let data = cell.getRow().getData();
			this.addOrUpdate(data)
			if (data.kategorie_mitarbeiter_id === null)
			{
				row.update({newentry: true})
				row.reformat();
			}
		},

		async addOrUpdate(data, newEntry = false)
		{
			let newValue = { ...this.theModel.updatedData };
			let uid = data.mitarbeiter_uid;
			let index = data.row_index;

			if (!newValue[this.config.category_id])
				newValue[this.config.category_id] = {}

			if (!newValue[this.config.category_id][uid])
				newValue[this.config.category_id][uid] = {}

			if (!newValue[this.config.category_id][uid][index])
				newValue[this.config.category_id][uid][index] = {}

			newValue[this.config.category_id][uid][index] = {
				kategorie: this.config.category_id,
				studienjahr: this.theModel.config.studienjahr,
				kategorie_mitarbeiter_id: data.kategorie_mitarbeiter_id,
				stunden: data.stunden,
				anmerkung: data.anmerkung,
				newentry: newEntry,
				uid: uid,
				reloadStudienjahr: this.studienjahr,
				reloadKategorie: this.config.category_id,
			}

			this.theModel.updatedData = newValue;
		},

		duplicateRow(cell)
		{
			let row = cell.getRow()
			let rowData = row.getData();
			if (rowData.stunden === 0.00 || rowData.stunden == "0")
				return;
			let newData = { ...rowData };

			this.rowCount[this.config.category_id]++;
			newData.row_index = this.rowCount[this.config.category_id];
			newData.kategorie_mitarbeiter_id = null;
			newData.anmerkung = null;
			newData.newentry = true;

			this.$refs.categoryTable.tabulator.addRow(newData, false, cell.getRow());
			let newRowData = this.$refs.categoryTable.tabulator.getRow(newData.row_index).getData();
			this.addOrUpdate(newRowData, true).then(() =>
			{
				if (rowData.kategorie_mitarbeiter_id === null)
				{
					row.update({newentry: true})
					row.reformat();
					this.addOrUpdate(rowData, true);
				}
			});

		},
		deleteRow(cell)
		{
			let row = cell.getRow();
			let data = row.getData();

			let uid = data.mitarbeiter_uid;
			let index = data.row_index;

			row.getElement().classList.add('disabled-row')
			let counts = this.uidCount(uid)
			if (this.theModel.updatedData?.[this.config.category_id]?.[uid]?.[index])
			{
				if (counts === 0)
				{
					row.update({newentry: false})
					row.reformat();
					row.getElement().classList.remove('disabled-row')
				}
				else
					row.delete();


				delete this.theModel.updatedData[this.config.category_id][uid][index];

				if (Object.keys(this.theModel.updatedData[this.config.category_id][uid]).length === 0)
					delete this.theModel.updatedData[this.config.category_id][uid]

				if (Object.keys(this.theModel.updatedData[this.config.category_id]).length === 0)
					delete this.theModel.updatedData[this.config.category_id]

			}
			else
			{
				if (!this.theModel.updatedData[this.config.category_id])
					this.theModel.updatedData[this.config.category_id] = {}

				if (!this.theModel.updatedData[this.config.category_id][uid])
					this.theModel.updatedData[this.config.category_id][uid] = {}

				if (!this.theModel.updatedData[this.config.category_id][uid][index])
					this.theModel.updatedData[this.config.category_id][uid][index] = {}

				this.theModel.updatedData[this.config.category_id][uid][index] = {
					kategorie_mitarbeiter_id: data.kategorie_mitarbeiter_id,
					delete: true,
					reloadStudienjahr: this.studienjahr,
					reloadKategorie: this.config.category_id
				};
			}
		},
		uidCount(uid)
		{
			let count = 0;
			for (let row of this.$refs.categoryTable.tabulator.getRows())
			{
				if (row.getData().mitarbeiter_uid === uid && !row.getElement().classList.contains('disabled-row'))
				{
					count++;
				}
			}
			return count;
		},
	},


	template: `
		<core-base-layout>
			<template #main>
				<h5>{{$p.t('lehre', 'studienjahr')}}: {{theModel?.config?.studienjahr}}</h5>
				<core-filter-cmpt
					ref="categoryTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited }]"
					:table-only=true
					:side-menu="false"
					:hideTopMenu=false>
					<template #actions>
						<button class="btn btn-primary" @click="vorruecken">Stunden vorrücken</button>
					</template>
				</core-filter-cmpt>
			</template>
			
		</core-base-layout>
		
	`
};
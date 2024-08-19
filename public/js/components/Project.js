import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import BsModal from '../../../../js/components/Bootstrap/Modal.js';
import FormInput from "../../../../js/components/Form/Input.js";
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
		CoreBaseLayout,
		BsModal,
		FormInput
	},
	data: function() {
		return{
			formData: {
				lektor: '',
				sapprojekte: '',
				stunden: '',
				anmerkung: ''
			},
			studienjahr: null,
			rowCount: 0,
			sapprojekte: {
				type: Array
			},
			lektoren: {
				type: Array
			},
			filteredLektor: [],
			filteredProjekte: [],
			loadedData: {}
		}
	},
	mounted() {
		this.getProjekte();
		this.getLektoren();
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
							let container = document.createElement('div');
							container.className = "d-flex gap-2";
							if (
								(cell.getData().pep_projects_employees_id))
							{
								let deleteButton = document.createElement('button');
								deleteButton.className = 'btn btn-outline-secondary';
								deleteButton.innerHTML = '<i class="fa fa-minus"></i>';
								deleteButton.addEventListener('click', (event) => {
									this.deleteRow(cell);
								});
								container.append(deleteButton);
							}

							return container;
						},
					},
					{title: 'Projekt', field: 'project_id', headerFilter: true},
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true},
					/*{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},*/
					{title: 'SAP - Stunden', field: 'summe_planstunden', headerFilter: true},
					{
						title: 'PEP - Stunden',
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
								return parseFloat(0).toFixed(2);
							}

							if (!isNaN(value))
							{
								value = parseFloat(value).toFixed(2);
								return value;
							}
						},
						cellEdited: function (cell)
						{
							var value = cell.getValue();
							if (value && !isNaN(value) && value > 0)
							{
								return (parseFloat(value).toFixed(2));
							}
							else
							{
								return (parseFloat(0).toFixed(2));
							}
						},
						validator: ["numeric", {
							type: function(cell, value, parameters)
							{
								if (isNaN(value))
									return false;

								value = parseFloat(value);
								if (value.toFixed(2) != value)
									return false;

								if (value > 999.99 || value < 0)
									return false;

								return true;
							},
						}]
					},
					{title: 'PEP - Gesamtstunden/Projekt', field: 'gesamt_stunden', headerFilter: true, visible: false},
				],
				persistenceID: "pep_project"
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
		tableBuilt(){
			this.theModel = { ...this.modelValue, loadDataReady: true };
		},

		getProjekte() {
			this.$fhcApi.factory.pep.getProjekte()
				.then(result => result.data)
				.then(result => {
					this.sapprojekte = result;
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		getLektoren() {
			this.$fhcApi.factory.pep.getLektoren()
				.then(result => result.data)
				.then(result => {
					this.lektoren = result
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		searchLektor(event)
		{
			const query = event.query.toLowerCase().trim();
			this.filteredLektor = this.lektoren.filter(lektor => {
				const fullName = `${lektor.vorname.toLowerCase()} ${lektor.nachname.toLowerCase()}`;
				const reverseFullName = `${lektor.nachname.toLowerCase()} ${lektor.vorname.toLowerCase()}`;
				return fullName.includes(query) || reverseFullName.includes(query) || lektor.uid.toLowerCase().includes(query);
			}).map(lektor => ({
				label: `${lektor.nachname} ${lektor.vorname} (${lektor.uid})`,
				uid: lektor.uid
			}));
		},
		searchProjekt(event)
		{
			const query = event.query.toLowerCase().trim();
			this.filteredProjekte = this.sapprojekte.filter(projekt => {
				return projekt.project_id.toLowerCase().includes(query);
			}).map(projekt => ({
				label: `${projekt.project_id}`,
				project_id: projekt.project_id
			}));
		},

		async loadData(data)
		{

			this.loadedData = data;
			await this.$fhcApi.factory.pep.getProjects(data)
				.then(response => {
					this.$refs.projectTable.tabulator.setData(response.data);
					if (!this.rowCount)
					{
						this.rowCount =  this.$refs.projectTable.tabulator.getRows().length;
					}

				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},

		async onCellEdited(cell)
		{
			let value = cell.getValue();

			if ((value === "" || value === "0" || value == 0) && (cell.getOldValue() === null || cell.getOldValue() == 0)
				|| (value == cell.getOldValue())
			)
				return;
			let row = cell.getRow();
			let rowData = row.getData()

			let data = {
				'project_id': rowData.project_id,
				'uid': rowData.mitarbeiter_uid,
				'id': rowData.pep_projects_employees_id,
				'stunden': value
			}
			await this.$fhcApi.factory.pep.updateProjectStunden(data).then(response => {
				if (!response.data)
					this.$fhcAlert.alertWarning("Fehler beim Löschen")
				{
					console.log(response.data);

					this.$refs.projectTable.tabulator.updateRow(row.getIndex(), {
						'stunden' : value,
						'pep_projects_employees_id': response.data
					})
					row.reformat();
				}
			});
		},


		duplicateRow(cell)
		{
			let row = cell.getRow()
			let rowData = row.getData();

			let newData = { ...rowData };

			this.rowCount++;
			newData.row_index = this.rowCount;

			this.$refs.projectTable.tabulator.addRow(newData, false, cell.getRow());
			let newRow = this.$refs.projectTable.tabulator.getRow(newData.row_index);
			this.speichern(newRow)


		},
		async deleteRow(cell)
		{
			let row = cell.getRow();
			let data = row.getData();

			let uid = data.mitarbeiter_uid;

			let postData = {
				'id': data.pep_projects_employees_id,
				'uid': uid
			}

			console.log(postData);
			await this.$fhcApi.factory.pep.deleteProjectStunden(postData)
				.then(response => {
					if (!response.data)
						this.$fhcAlert.alertWarning("Fehler beim Löschen")
					{
						if (data.synced === 0)
							row.delete()
						else
						{
							this.$refs.projectTable.tabulator.updateRow(row.getIndex(), {
								'stunden' : 0,
								'pep_projects_employees_id': null
							})

							row.reformat();
						}

					}
				});
		},

		addData()
		{
			this.$refs.newModal.show();
		},

		reset()
		{
			this.formData = {};
			this.$refs.newModal.hide();
		},
		saveProjekt()
		{
			let data = {
				'lektor' : this.formData.lektor.uid,
				'project' : this.formData.sapprojekte.project_id,
				'stunden' : this.formData.stunden,
				'anmerkung' : this.formData.anmerkung,
			}

			this.$fhcApi.factory.pep.addProjectStunden(data)
				.then(result => result.data)
				.then(() => this.loadData(this.loadedData))
				.then(() => this.reset())

				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		}

	},
	template: `
		<core-base-layout>
			<template #main>
				<core-filter-cmpt
					ref="projectTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited }, { event: 'tableBuilt', handler: tableBuilt }]"
					:table-only=true
					:side-menu="false"
					:hideTopMenu=false
					new-btn-label="Zeile"
					new-btn-show
					@click:new="addData">
				</core-filter-cmpt>
				<bs-modal ref="newModal" class="bootstrap-prompt" dialogClass="modal-lg" @hidden-bs-modal="reset">
					<template #title>Stunden hinzufügen</template>
					<template #default>
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="autocomplete"
									:label="Lektor"
									:suggestions="filteredLektor"
									placeholder="Mitarbeiter auswählen"
									field="label"
									v-model="formData.lektor"
									@complete="searchLektor"
								></form-input>
							</div>
							<div class="col">
								<form-input
									type="autocomplete"
									v-model="formData.sapprojekte"
									:suggestions="filteredProjekte"
									field="label"
									placeholder="Projekt auswählen"
									@complete="searchProjekt"
									:label="Projekt"
								></form-input>
							</div>
							
						</div>
						<hr />
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="number"
									v-model="formData.stunden"
									name="stunden"
									placeholder="Stunden"
									:label="Stunden"
								>
								</form-input>
									
									
							</div>
								
							<div class="col">
								<form-input
									type="textarea"
									v-model="formData.anmerkung"
									name="anmerkung"
									placeholder="Anmerkung"
									:label="Anmerkung"
								>
								</form-input>
							</div>
						</div>
					</template>
					<template #footer>
						<button type="button" class="btn btn-primary" @click="saveProjekt">Speichern</button>
					</template>
				</bs-modal>
			</template>
			
		</core-base-layout>
		
	`
};
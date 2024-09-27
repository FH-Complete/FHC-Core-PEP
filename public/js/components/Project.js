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
				anmerkung: '',
				von: '',
				bis: '',
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
			loadedData: {},
			filteredDates: {
				studienjahr: '',
				von: '',
				bis: ''
			},
			columnsToMark: ['summe_planstunden', 'stunden']

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
				rowFormatter: (row) =>
				{
					if (row.getElement().classList.contains("tabulator-calcs"))
						return;
					let data = row.getData();
					let children = row.getElement().childNodes;
					let columns = row.getTable().getColumns();
					if (data.stunden === null && data.summe_planstunden !== null)
					{

						this.columnsToMark.forEach((spaltenName) => {
							let column = columns.find(col => col.getField() === spaltenName);
							if (column) {
								let cellElement = row.getCell(column).getElement();
								cellElement.classList.add("highlight-warning");
							}
						});
					}
					else if (data.stunden !== null && data.summe_planstunden === null)
					{
						this.columnsToMark.forEach((spaltenName) => {
							let column = columns.find(col => col.getField() === spaltenName);
							if (column) {
								let cellElement = row.getCell(column).getElement();
								cellElement.classList.add("highlight-info");
							}
						});
					}
				},
				columns: [
					{
						title: 'Aktionen',
						field: 'actions',
						width: 100,
						formatter: (cell, formatterParams, onRendered) => {

							let container = document.createElement('div');
							container.className = "d-flex gap-2";
							if ((cell.getData().pep_projects_employees_id))
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
					{title: 'Projekt - ID', field: 'project_id', headerFilter: true},
					{title: 'Projekt - Name', field: 'name', headerFilter: true},
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'SAP - Stunden', field: 'summe_planstunden', headerFilter: true},
					{
						title: 'PEP - Stunden',
						field: 'stunden',
						headerFilter: true,
						editor: "number",
						bottomCalcParams: {precision: 2},
						bottomCalc: "sum",
						cellEdited: (cell) => {
							this.stundenEdited(cell);
						},
						hozAlign: "right",
						negativeSign: false,
						formatter: function (cell, formatterParams, onRendered)
						{
							let value = cell.getValue();
							if (value === null)
								return;
							if (value === "")
								return null;
							if (!isNaN(value))
								return parseFloat(value).toFixed(2);
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
						},
						]
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", visible: true, editor: "textarea",
						formatter: "textarea",
						cellEdited: (cell) => {
							this.anmerkungEdited(cell);
						},
					},
					{title: 'Startdatum', field: 'start_date', headerFilter: true, visible: false},
					{title: 'Enddatum', field: 'end_date', headerFilter: true, visible: false},
					{title: 'Projektlaufzeit in Monaten', field: 'laufzeit', headerFilter: true},
					{title: 'Verbrauchte Zeit in Monaten', field: 'verbrauchte_zeit', headerFilter: true},
					{title: 'Restlaufzeit in Monaten', field: 'restlaufzeit', headerFilter: true},
					{title: 'Status', field: 'status', headerFilter: true},
					{title: 'Erster Stichtag (01.01)', field: 'erster', headerFilter: true},
					{title: 'Zweiter Stichtag (01.06)', field: 'zweiter', headerFilter: true},
					{title: 'Aktuelle Stunden', field: 'aktuellestunden', headerFilter: true},
				],
				persistenceID: "2024_09_26_pep_project"
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
				uid: lektor.uid,
			}));
		},
		searchProjekt(event)
		{
			const query = event.query.toLowerCase().trim();
			this.filteredProjekte = this.sapprojekte
				.filter(projekt => {
					const projektstart = new Date(projekt.start_date);
					const projektende = projekt.end_date && new Date(projekt.end_date)
					const studienjahrvon = new Date(this.filteredDates.von);
					const studienjahrbis = new Date(this.filteredDates.bis);
					return (projektstart <= studienjahrbis || projektstart === null) && (projektende >= studienjahrvon || projektende === null);
				})
				.filter(projekt => {
					return projekt.project_id.toLowerCase().includes(query);
				}).map(projekt => ({
					label: `${projekt.project_id}`,
					project_id: projekt.project_id,
					von: projekt.start_date,
					bis: projekt.end_date
				}));
		},
		fillDates(selectedProject)
		{
			this.formData.von = selectedProject.value.von && this.formatDate(selectedProject.value.von);
			this.formData.bis = selectedProject.value.bis && this.formatDate(selectedProject.value.bis);
		},
		formatDate(date)
		{
			let von = date.split(' ')[0];
			const [year, month, day] = von.split('-');
			return `${day}.${month}.${year}`;
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

		stundenEdited(cell)
		{
			let value = cell.getValue();
			let row = cell.getRow();

			if (parseFloat(value).toFixed(2) === parseFloat(cell.getOldValue()).toFixed(2))
				return;
			else if (cell.getOldValue() === null && value === "")
				value = null;
			else if (value === "")
				value = 0

			this.updateStunden(row)

		},
		anmerkungEdited(cell)
		{
			let value = cell.getValue();
			let row = cell.getRow();

			if (value === cell.getOldValue())
				return;

			this.updateStunden(row)

		},
		async updateStunden(row)
		{
			let rowData = row.getData()
			let data = {
				'project_id': rowData.project_id,
				'uid': rowData.mitarbeiter_uid,
				'id': rowData.pep_projects_employees_id,
				'anmerkung': rowData.anmerkung,
				'stunden': rowData.stunden,
				'studienjahr': this.theModel.config.studienjahr
			}

			await this.$fhcApi.factory.pep.updateProjectStunden(data).then(response => {
				if (!response.data)
					this.$fhcAlert.alertWarning("Fehler beim Löschen")
				{
					this.$refs.projectTable.tabulator.updateRow(row.getIndex(), {

						'pep_projects_employees_id': response.data
					})
					this.theModel = { ...this.modelValue, needReload: true };
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert")
					row.reformat();
				}
			});
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

			await this.$fhcApi.factory.pep.deleteProjectStunden(postData)
				.then(response => {
					if (!response.data)
						this.$fhcAlert.alertWarning("Fehler beim Löschen")
					{
						if (data.summe_planstunden === null)
							row.delete()
						else
						{
							this.$refs.projectTable.tabulator.updateRow(row.getIndex(), {
								'stunden' : null,
								'pep_projects_employees_id': null
							})

							row.reformat();
						}
						this.theModel = { ...this.modelValue, needReload: true };
					}
				});
		},

		async addData()
		{
			if (this.filteredDates.studienjahr !== this.theModel.config.studienjahr)
			{
				let data = {'studienjahr' : this.theModel.config.studienjahr};
				await this.$fhcApi.factory.pep.getStartAndEnd(data)
					.then(result => result.data)
					.then(result => {
						this.filteredDates.von = result.start;
						this.filteredDates.bis = result.ende;
						this.filteredDates.studienjahr = result.studienjahr_kurzbz;
					})
					.catch(error => {
						this.$fhcAlert.handleSystemError(error);
					});
			}
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
				'studienjahr': this.theModel.config.studienjahr,
				'org': this.theModel.config.org
			}
			this.$fhcApi.factory.pep.addProjectStunden(data)
				.then(result => result.data)
				.then(result => {
					if (result === true)
						return this.$fhcAlert.alertWarning("Mitarbeiter ist bereits dem Projekt zugeordnet!");
					this.$refs.projectTable.tabulator.addRow(result, false)

				})
				.then(() => this.reset())
				.then(() => this.theModel = { ...this.modelValue, needReload: true })
				.catch(error => {
					this.$fhcAlert.handleSystemError((error));
				});
		}

	},
	template: `
		<core-base-layout>
			<template #main>
				<core-filter-cmpt
					ref="projectTable"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }]"
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
									@item-select="fillDates"
									:label="Projekt"
								></form-input>
							</div>
						</div>
						<br />
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									placeholder="Projektstart"
									v-model="formData.von"
									readonly
								></form-input>
							</div>
							<div class="col">
								<form-input
									placeholder="Projektende"
									v-model="formData.bis"
									readonly
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
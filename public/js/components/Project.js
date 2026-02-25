import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import BsModal from '../../../../js/components/Bootstrap/Modal.js';
import FormInput from "../../../../js/components/Form/Input.js";
import { extendedHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import {formatter} from "../mixins/formatters.js";
import { dateFilter } from "../../../../js/tabulator/filters/Dates.js";
import focusMixin from "../mixins/focus.js";
import ApiProject from "../api/project.js";
import Tag from '../../../../js/components/Tag/Tag.js';
import { ApiProjectTag  } from "../api/projectTabTags.js";
import { tagHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import tagMixin from "../mixins/tag.js";
import { addTagInTable, deleteTagInTable, updateTagInTable } from "../../../../js/helpers/TagHelper.js";


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
		FormInput,
		Tag
	},
	mixins: [focusMixin, tagMixin],
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
			columnsToMark: ['stunden', 'anmerkung'],
			focusFields: ["anmerkung", "stunden"],
			tagEndpoint: ApiProjectTag,
			selectedColumnValues: []
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
				height: '60vh',
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: (row) =>
				{
					if (row.getElement().classList.contains("tabulator-calcs"))
						return;
					let data = row.getData();
					let columns = row.getTable().getColumns();
					if (data.only_pep)
					{
						columns.forEach((column) => {
							let cellElement = row.getCell(column).getElement();
							cellElement.classList.add("highlight-error");
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
					else
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
				persistenceID: "2025_03_12_pep_project",
				persistence: true,
				keybindings: false,
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
					{
						title: 'Tags',
						field: 'tags',
						tooltip: false,
						headerFilter: true,
						headerFilterFunc: tagHeaderFilter,
						formatter: (cell) => formatter.tagFormatter(cell, this.$refs.tagComponent),
						width: 150,
					},
					{title: 'Projekt - ID', field: 'project_id', headerFilter: true},
					{title: 'Projekt - Name', field: 'name', headerFilter: true},
					{title: 'Verantwortlicher lt. SAP', field: 'leitung', formatter:"textarea", headerFilter: "input"},
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'Gesamtplanstunden lt. SAP', field: 'summe_planstunden', headerFilter: true},
					{
						title: 'PEP - Stunden',
						field: 'stunden',
						headerFilter: "input",
						editor: "number",
						bottomCalcParams: {precision: 2},
						bottomCalc: "sum",
						hozAlign: "right",
						cellEdited: (cell) => {
							let value = cell.getValue();
							let oldValue = cell.getOldValue();

							if (oldValue === null && value === null)
							{
								value = null;
							}
							else if (value === "" )
							{
								value = parseFloat(0).toFixed(2);
							}
							else if (!isNaN(value))
							{
								value = parseFloat(value).toFixed(2);
							}
							return value;
						},
						formatter: function (cell, formatterParams, onRendered) {
							let value = cell.getValue();
							let row = cell.getRow();
							let anmerkungValue = row.getData().anmerkung;
							let oldValue = cell.getOldValue();

							if (value === null)
								return;

							if (value === "")
							{
								if (anmerkungValue && anmerkungValue.trim() !== "")
								{
									return parseFloat(0).toFixed(2);
								}
								if (oldValue === null || oldValue === "")
								{
									return;
								}
								return parseFloat(0).toFixed(2);
							}

							if (!isNaN(value))
							{
								return parseFloat(value).toFixed(2);
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
						},
						]
					},
					{title: 'Anmerkung',
						field: 'anmerkung',
						headerFilter: "input",
						visible: true,
						editor: "textarea",
						formatter: "textarea",
						editorParams: {
							shiftEnterSubmit: true
						},
					},
					{title: 'Startdatum', field: 'start_date', formatter: formatter.dateFormatter, headerFilterFunc: 'dates', headerFilter: dateFilter, visible: false},
					{title: 'Enddatum', field: 'end_date', formatter: formatter.dateFormatter, headerFilterFunc: 'dates', headerFilter: dateFilter, visible: false},
					{title: 'Projektlaufzeit in Monaten', field: 'laufzeit', headerFilter: true},
					{title: 'Verbrauchte Zeit in Monaten', field: 'verbrauchte_zeit', headerFilter: true},
					{title: 'Restlaufzeit in Monaten', field: 'restlaufzeit', headerFilter: true},
					{title: 'Status lt. SAP', field: 'status', headerFilter: true},
					{title: 'Status intern', field: 'status_sap_intern', headerFilter: true},
					{title: 'Erster Stichtag (01.01) im ausgewählten SJ', field: 'erster', headerFilter: true},
					{title: 'Zweiter Stichtag (01.06) im ausgewählten SJ', field: 'zweiter', headerFilter: true},
					{title: 'gebuchte Stunden im ausgewählten SJ', field: 'aktuellestunden', headerFilter: true},
					{title: 'bis heute gebuchte Gesamtstunden', field: 'aktuellestundengesamt', headerFilter: true},
					{title: 'Offene Stunden', headerFilter: true, field: 'offenestunden'},
					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},

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
		tableBuilt(){
			this.theModel = { ...this.modelValue, loadDataReady: true };
			this.addFocus('projectTable', this.focusFields)
		},


		getProjekte() {
			this.$api.call(ApiProject.getProjekte())
				.then(result => result.data)
				.then(result => {
					this.sapprojekte = result;
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		getLektoren() {
			this.$api.call(ApiProject.getLektoren())
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
					return projekt.project_id.toLowerCase().includes(query) || projekt.name.toLowerCase().includes(query);
				}).map(projekt => ({
					label: `${projekt.project_id} (${projekt.name})`,
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
			await this.$api.call(ApiProject.getProjects(data))
				.then(response => {
					if (response.data.length === 0)
					{
						this.$fhcAlert.alertInfo("Projekte: Keine Daten vorhanden");
						this.$refs.projectTable.tabulator.setData([]);
					}
					else
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
		onCellEdited(cell)
		{
			let value = cell.getValue();
			let oldValue = cell.getOldValue();
			let field = cell.getField();

			if (field === "stunden")
			{
				if (parseFloat(value).toFixed(2) === parseFloat(cell.getOldValue()).toFixed(2))
					return;

				let row = cell.getRow();
				this.updateStunden(row);
			}
			else if (field === "anmerkung")
			{
				if ((value === "" || value === null) && (oldValue === "" || oldValue === null))
				{
					return;
				}

				if (value === oldValue) {
					return;
				}

				let row = cell.getRow();
				this.updateStunden(row);
			}

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

			await this.$api.call(ApiProject.updateProjectStunden(data))
				.then(response => {
					if (!response.data)
						this.$fhcAlert.alertWarning("Fehler beim Löschen")
					else
					{
						row.update({'pep_projects_employees_id': response.data});

						this.theModel = { ...this.modelValue, needReload: true };
						this.$fhcAlert.alertSuccess("Erfolgreich gespeichert")
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

			await this.$api.call(ApiProject.deleteProjectStunden(postData))
				.then(response => {
					if (!response.data)
						this.$fhcAlert.alertWarning("Fehler beim Löschen")
					{
						if (data.summe_planstunden === null)
							row.delete()
						else
						{
							row.update({
								'stunden' : null,
								'pep_projects_employees_id' : null,
								'anmerkung': null,
								'tags': null
							})
							.then(() => {
								row.reformat()
							})
							.then(() => {
								this.$fhcAlert.alertSuccess("Erfolgreich gespeichert")
							});

						}
						this.theModel = { ...this.modelValue, needReload: true };
					}
				}).finally(() => {this.updateSelectedRows()});
		},

		async addData()
		{
			if (this.filteredDates.studienjahr !== this.theModel.config.studienjahr)
			{
				let data = {'studienjahr' : this.theModel.config.studienjahr};
				await this.$api.call(ApiProject.getStartAndEnd(data))
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
			this.$api.call(ApiProject.addProjectStunden(data))
				.then(result => result.data)
				.then(result => {
					if (result === true)
						return this.$fhcAlert.alertWarning("Mitarbeiter ist bereits dem Projekt zugeordnet!");

					const existingRows = this.$refs.projectTable.tabulator.getRows();
					const existingRow = existingRows.find(row => {
						const rowData = row.getData();
						return rowData.mitarbeiter_uid === result.mitarbeiter_uid && rowData.project_id === result.project_id;
					});

					if (existingRow)
					{
						existingRow.update({ ...existingRow.getData(), ...result });
						existingRow.reformat();
					}
					else
					{
						this.$refs.projectTable.tabulator.addRow(result, false);
					}
				})
				.then(() => this.reset())
				.then(() => this.theModel = { ...this.modelValue, needReload: true })
				.catch(error => {
					this.$fhcAlert.handleSystemError((error));
				});
		},
		updateSelectedRows() {
			this.selectedRows = this.$refs.projectTable.tabulator.getSelectedRows();
			this.selectedColumnValues = this.selectedRows.map(row => row.getData().pep_projects_employees_id).filter((row) => row !== null);
			this.addColorToInfoText(this.selectedColumnValues);
		},
		addedTag(addedTag) {
			addTagInTable(addedTag, this.$refs.projectTable.tabulator.getRows(), 'pep_projects_employees_id');
		},
		deletedTag(deletedTag) {
			deleteTagInTable(deletedTag, this.$refs.projectTable.tabulator.getRows())
		},
		updatedTag(updatedTag) {
			updateTagInTable(updatedTag, this.$refs.projectTable.tabulator.getRows())
		},
	},
	template: `
		<core-base-layout>
			<template #main>
				<h5>{{$p.t('lehre', 'studienjahr')}}: {{theModel?.config?.studienjahr}}</h5>
				<core-filter-cmpt
					ref="projectTable"
					:download="config?.download"
					:tabulator-options="tabulatorOptions"
					:tabulator-events="[{ event: 'tableBuilt', handler: tableBuilt }, { event: 'cellEdited', handler: onCellEdited }, { event: 'rowSelectionChanged', handler: updateSelectedRows }]"
					:table-only=true
					:side-menu="false"
					:countOnly="true">
					<template #actions>
						<Tag ref="tagComponent"
							:endpoint="tagEndpoint"
							:values="selectedColumnValues"
							@added="addedTag"
							@deleted="deletedTag"
							@updated="updatedTag"
							zuordnung_typ="pep_projects_employees_id"
						></Tag>
						<button class="btn btn-primary" @click="addData">Mitarbeiter hinzufügen</button>
					</template>
				</core-filter-cmpt>
				<bs-modal ref="newModal" class="bootstrap-prompt" dialogClass="modal-lg" @hidden-bs-modal="reset">
					<template #title>Stunden hinzufügen</template>
					<template #default>
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="autocomplete"
									label="Lektor"
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
									label="Projekt"
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
									label="Stunden"
								>
								</form-input>
									
									
							</div>
								
							<div class="col">
								<form-input
									type="textarea"
									v-model="formData.anmerkung"
									name="anmerkung"
									placeholder="Anmerkung"
									label="Anmerkung"
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
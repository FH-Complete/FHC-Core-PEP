import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import BsModal from '../../../../js/components/Bootstrap/Modal.js';
import FormInput from "../../../../js/components/Form/Input.js";

import {formatter} from "../mixins/formatters";


export default {
	name: "Lehre",
	components: {
		CoreFilterCmpt,
		BsModal,
		FormInput,
		CoreBaseLayout
	},
	props: {
		config: null,
		modelValue: {
			type: Object,
			required: true
		},
	},
	mounted(){
		this.getRaumtypen();
		this.getLektoren();
	},
	data: function() {
		return {
			formData: {
				lehreinheit_id: '',
				raumtyp: '',
				raumtypalternativ: '',
				start_kw: '',
				stundenblockung: '',
				wochenrythmus: '',
				anmerkung: '',
				lektor: '',
				oldlektor: '',
				lehreinheit_ids: []
			},
			formDataFaktor: {
				bezeichnung: '',
				faktor: '',
				lvstunden: 0,
				lvstundenfaktor: '',
				lv_id: '',
				updatestudiensemester: ''

			},
			raumtypen: {
				type: Array
			},
			lektoren: {
				type: Array
			},
			studiensemester: [],
			filteredLektor: [],
			filteredRaumtypen: [],
			selectedLektor: null,
			selectedRow: null,
			modalTitle: 'Änderungen'

		}
	},

	computed: {
		tabulatorOptions()
		{
			return {
				index: "row_index",
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
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
					{
						title: 'Aktionen',
						field: 'actions',
						width: 85,
						formatter: (cell, formatterParams, onRendered) => {
							let container = document.createElement('div');
							container.className = "d-flex gap-1";

							let button = document.createElement('button');
							button.className = 'btn btn-outline-secondary';
							button.innerHTML = '<i class="fa fa-envelope"></i>';
							button.addEventListener('click', (event) =>
								this.assistenzMail(cell.getData())
							);
							container.append(button);

							if (cell.getData().editable === true)
							{
								container.className = "d-flex gap-2";
								button = document.createElement('button');
								button.className = 'btn btn-outline-secondary';
								button.innerHTML = '<i class="fa fa-edit"></i>';
								button.addEventListener('click', (event) =>
									this.editLehreinheit(cell.getData(), cell.getRow())
								);
								container.append(button);
							}
							return container;

						},
					},
					{title: 'Fakultaet', field: 'fakultaet', headerFilter: true},
					{title: 'LVID', field: 'lv_id', headerFilter: true},
					{title: 'STG', field: 'stg_kuerzel', headerFilter: true},
					{title: 'LV Organisationseinheit', field: 'lv_oe', headerFilter: true, visible: true},
					{title: 'LV Bezeichnung', field: 'lv_bezeichnung', headerFilter: true},
					{title: 'LE Semester', field: 'studiensemester_kurzbz', headerFilter: true, visible:false},
					{title: 'Gruppe', field: 'gruppe', headerFilter: true, visible:false},
					{title: 'LE ID', field: 'lehreinheit_id', headerFilter: true, visible:false},
					{title: 'LE Lehrform', field: 'lehrform_kurzbz', headerFilter: true},
					{title: 'Lektor*in', field: 'lektor', headerFilter: true},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'lektor_nachname', headerFilter: true},
					{title: 'Hinzugefuegt am', field: 'insertamum', headerFilter: true},
					{title: 'Updated am', field: 'updateamum', headerFilter: true},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", editor: "textarea", formatter: "textarea"},
					{title: 'LV Leitung', field: 'lehrfunktion_kurzbz', headerFilter: true, viisble: false},
					{title: 'Semesterstunden', field: 'lektor_stunden', headerFilter: true, bottomCalc: "sum", bottomCalcParams:{precision:2},visible: true, hozAlign:"right"},
					{title: 'Realstunden', field: 'faktorstunden', headerFilter: true, visible: true, hozAlign:"right", bottomCalc: "sum", bottomCalcParams:{precision:2},
						formatter: (cell) => {
							let container = document.createElement('div');
							container.className = "d-flex gap-2 justify-content-end";
							let value = document.createElement('span');
							value.textContent = isNaN(cell.getValue()) || !cell.getValue()  ? '-' : parseFloat(cell.getValue()).toFixed(2);
							container.append(value);
							let button = document.createElement('button');
							button.className = 'btn btn-outline-secondary';
							button.innerHTML = '<i class="fa fa-edit"></i>';
							button.addEventListener('click', (event) =>
								this.editFaktor(cell.getData(), cell.getRow())
							);
							container.append(button);
							return container;

						}
					},
					{title: 'Faktor', field: 'faktor', headerFilter: true, visible: true, hozAlign:"right"},

					{title: 'LE Stundensatz', field: 'le_stundensatz', headerFilter: true, hozAlign:"right"},
					{title: 'LV-Plan Stunden', field: 'lv_plan_stunden', headerFilter: true, hozAlign:"right"},


					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stundensatz', field: 'zrm_stundensatz_lehre', headerFilter: "input", visible: false, hozAlign:"right", tooltip: formatter.stundensatzLehreToolTip},



					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - Kostenstelle', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Kostenstelle - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'akt_stundensaetze_lehre', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},




					{title: 'Vorjahres Lektoren', field: 'vorjahreslektoren', headerFilter: "input", visible: true},
					{title: 'Raumtyp', field: 'raumtyp', headerFilter: "input", visible: false},
					{title: 'Raumtypalternativ', field: 'raumtypalternativ', headerFilter: "input",visible: false},
					{title: 'Wochenrythmus', field: 'wochenrythmus', headerFilter: "input", visible: false},
					{title: 'Start_kw', field: 'start_kw', headerFilter: "input", visible: false},
					{title: 'Stundenblockung', field: 'stundenblockung', headerFilter: "input", visible: false},
				],
				persistenceID: "2024_09_26_pep_lehre",
			}
		},
		faktorTabulatorOptions() {
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				columns: [
					{title: 'Lektor*in', field: 'kurzbz'},
					{title: 'Vorname', field: 'vorname'},
					{title: 'Nachname', field: 'nachname'},
					{title: 'Faktor', field: 'faktor', hozAlign: "right",
						formatter: (cell) => {
							return this.formDataFaktor.faktor
						}
					},
					{
						title: 'Semesterstunden',
						field: 'semesterstunden',
						hozAlign: "right",

					},
					{
						title: 'Realstunden',
						field: 'faktorstunden',
						hozAlign: "right",
						formatter: (cell) => {
							let data = cell.getData();
							let realstunden = parseFloat(data.semesterstunden * this.formDataFaktor.faktor).toFixed(2)
							if (data.vertrag !== 'echterdv')
							{
								realstunden = data.semesterstunden + " (" + realstunden + ")"
							}
							return realstunden
						}
					},
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
		},

		newSideMenuEntryHandler: function (payload)
		{
			this.appSideMenuEntries = payload;
		},
		async loadData(data) {
			this.studiensemester = this.theModel.config.semester;
			await this.$fhcApi.factory.pep.getLehre(data)
				.then(response => {

					if (response.data.length === 0)
					{
						this.$fhcAlert.alertInfo("Lehre: Keine Daten vorhanden");
						this.$refs.lehreTable.tabulator.setData([]);
					}
					else
						this.$refs.lehreTable.tabulator.setData(response.data);
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		recalcFaktor: function()
		{
			let stundenfaktor = this.formDataFaktor.lvstundenfaktor;
			let stunden = this.formDataFaktor.lvstunden;
			let faktor = this.formDataFaktor.faktor;

			if (!stunden)
				return;
			let newFaktor = stundenfaktor/stunden

			this.formDataFaktor.faktor = parseFloat(newFaktor).toFixed(2);
			this.$refs.faktorTable.tabulator.redraw(true)

		},
		editFaktor(data, row)
		{
			this.getLehreinheiten(data.lv_id)
		},
		getLehreinheiten(lv_id)
		{
			let data = {
				'lehrveranstaltung_id': lv_id,
				'studiensemester': this.studiensemester
			}

			this.$fhcApi.factory.pep.getLehreinheiten(data)
				.then(result => result.data)
				.then(result => {
					this.prefillFaktorModal(result)
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
		},
		editLehreinheit(data, row)
		{
			this.selectedRow = row;
			this.getLehreinheit(data.lehreinheit_id, data.uid)
		},
		getLehreinheit(le_id, uid)
		{
			let data = {
				'lehreinheit_id': le_id,
				'mitarbeiter_uid': uid
			}

			this.$fhcApi.factory.pep.getLehreinheit(data)
				.then(result => result.data)
				.then(result => {
					this.prefillLehreinheitModal(result)
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
		},
		prefillLehreinheitModal(data)
		{
			this.formData.lehreinheit_id = data.lehreinheit_id;
			this.formData.faktorstunden = data.faktorstunden;

/*			this.formData.raumtyp = data.raumtyp;
			this.formData.raumtypalternativ = data.raumtypalternativ;
			this.formData.start_kw = data.start_kw;
			this.formData.stundenblockung = data.stundenblockung;
			this.formData.wochenrythmus = data.wochenrythmus;*/
			this.formData.anmerkung = data.anmerkung;
			this.formData.oldlektor = data.mitarbeiter_uid;
			this.formData.studiensemester = this.studiensemester;

			const selectedLektor = this.lektoren.find(lektor => lektor.uid === data.mitarbeiter_uid);
			if (selectedLektor) {
				this.formData.lektor = {
					label: `${selectedLektor.nachname} ${selectedLektor.vorname} (${selectedLektor.uid})`,
					uid: data.mitarbeiter_uid
				}
			}

			const selectedRaumtyp = this.raumtypen.find(raumtyp => raumtyp.raumtyp_kurzbz === data.raumtyp);

			if (selectedRaumtyp) {
				this.formData.raumtyp = {
					label: `${selectedRaumtyp.beschreibung}`,
					raumtyp_kurzbz: data.raumtyp
				}
			}

			const selectedRaumtypAlt = this.raumtypen.find(raumtyp => raumtyp.raumtyp_kurzbz === data.raumtypalternativ);

			if (selectedRaumtypAlt) {
				this.formData.raumtypalternativ = {
					label: `${selectedRaumtypAlt.beschreibung}`,
					raumtyp_kurzbz: data.raumtypalternativ
				}
			}
			this.$refs.editModal.show();
		},
		prefillFaktorModal(data)
		{
			this.formDataFaktor = {
				bezeichnung: data[0].bezeichnung,
				updatestudiensemester: data[0].updatestudiensemester,
				lvstunden: data[0].lvstunden,
				faktor: parseFloat(data[0].faktor).toFixed(2),
				lvstundenfaktor: data[0].faktor * data[0].lvstunden,
				lv_id: data[0].lehrveranstaltung_id,
				semester: this.studiensemester
			}

			this.$refs.faktorTable.tabulator.setData(data);
			this.$refs.faktorModal.show();
		},
		getRaumtypen() {
			this.$fhcApi.factory.pep.getRaumtypen()
				.then(result => result.data)
				.then(result => {
					this.raumtypen = result;
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
		searchRaumtyp(event)
		{
			const query = event.query.toLowerCase().trim();
			this.filteredRaumtypen = this.raumtypen.filter(raumtyp => {
				return raumtyp.beschreibung.toLowerCase().includes(query);
			}).map(raumtyp => ({
				label: `${raumtyp.beschreibung}`,
				raumtyp_kurzbz: raumtyp.raumtyp_kurzbz
			}));
		},
		assistenzMail(rowData)
		{
			let body = `Lehrveranstaltung: ${rowData.lv_bezeichnung} (${rowData.lv_id}) %0D%0A
								Lehreinheit: ${rowData.lehreinheit_id} %0D%0A
								Studiensemester: ${rowData.studiensemester_kurzbz} %0D%0A
								Lektor: ${rowData.lektor} %0D%0A
								Gruppe: ${rowData.gruppe}`

			window.location.href = `mailto:${rowData.stg_email}?body=${body}`;
		},
		lektorMail()
		{
			const selectedRows = this.$refs.lehreTable.tabulator.getSelectedRows();
			let emails = []
			selectedRows.forEach(row => {
				let rowData = row.getData()

				if (!emails.includes(rowData.email))
					emails.push(rowData.email)
			})
			window.location.href = `mailto:${emails}`;
		},
		assistenzMailButton()
		{
			const selectedRows = this.$refs.lehreTable.tabulator.getSelectedRows();
			let emails = []
			let lehreinheiten = []

			selectedRows.forEach(row => {
				let rowData = row.getData()

				if (!emails.includes(rowData.stg_email))
					emails.push(rowData.stg_email)

				if (!lehreinheiten.includes(rowData.lehreinheit_id))
					lehreinheiten.push(rowData.lehreinheit_id)
			})

			let body = `Lehreinheiten: ${lehreinheiten.join('; ')}`
			window.location.href = `mailto:${emails}?body=${body}`;
		},
		updateLehreinheit()
		{
			const selectedRows = this.$refs.lehreTable.tabulator.getSelectedRows();

			selectedRows.forEach(row => {
				let rowData = row.getData()
				if (rowData.editable === false)
					return;
				this.formData.lehreinheit_ids.push({row_index: rowData.row_index, lehreinheit_id: rowData.lehreinheit_id, uid: rowData.uid})
			})
			this.$fhcApi.factory.pep.saveLehreinheit(this.formData)
				.then(result => result.data)
				.then(updateData => {

					updateData.lehreinheiten_ids.forEach(row => {
						this.$refs.lehreTable.tabulator.updateRow(row.id, {
							'lektor' : updateData.lektor,
							'vorname' : updateData.vorname,
							'lektor_nachname' : updateData.nachname,
							'zrm_vertraege' : updateData.zrm_vertraege,
							'zrm_wochenstunden' : updateData.zrm_wochenstunden,
							'zrm_jahresstunden' : updateData.zrm_jahresstunden,
							'le_stundensatz' : updateData.le_stundensatz,
							'bezeichnung' : updateData.bezeichnung,
							'akt_orgbezeichnung' : updateData.akt_orgbezeichnung,
							'akt_parentbezeichnung' : updateData.akt_parentbezeichnung,
							'akt_stunden' : updateData.akt_stunden,
							'akt_stundensaetze_lehre' : updateData.akt_stundensaetze_lehre,
							'uid' : updateData.uid,
							'updateamum' : updateData.updateamum,
						})
					});

					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					this.selectedRow.update({
							'lektor' : updateData.lektor,
							'vorname' : updateData.vorname,
							'lektor_nachname' : updateData.nachname,
							'zrm_vertraege' : updateData.zrm_vertraege,
							'zrm_wochenstunden' : updateData.zrm_wochenstunden,
							'zrm_jahresstunden' : updateData.zrm_jahresstunden,
							'le_stundensatz' : updateData.le_stundensatz,
							'bezeichnung' : updateData.bezeichnung,
							'akt_orgbezeichnung' : updateData.akt_orgbezeichnung,
							'akt_parentbezeichnung' : updateData.akt_parentbezeichnung,
							'akt_stunden' : updateData.akt_stunden,
							'akt_stundensaetze_lehre' : updateData.akt_stundensaetze_lehre,
							'uid' : updateData.uid,
							'anmerkung' : updateData.anmerkung,
							'updateamum' : updateData.updateamum,
						})
						.then(() => this.resetFormData())
						.then(() => this.$refs.editModal.hide());
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		updateFaktor()
		{
			this.$fhcApi.factory.pep.updateFaktor(this.formDataFaktor)
				.then(result => result.data)
				.then(updateData => {
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		onCellEdited(cell)
		{
			let value = cell.getValue();
			if (!value.trim() || (cell.getOldValue() === value.trim()))
				return;

			let data = cell.getRow().getData();

			let updateData = {
				'anmerkung': value,
				'uid': data.uid,
				'lehreinheit_id': data.lehreinheit_id
			}

			this.updateAnmerkung(updateData);
		},
		updateAnmerkung(data)
		{
			this.$fhcApi.factory.pep.updateAnmerkung(data)
				.then(result => result.data)
				.then(updateData => {

					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");

				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		resetFormData()
		{
			this.formData = {
				lehreinheit_id: '',
				raumtyp: '',
				raumtypalternativ: '',
				start_kw: '',
				stundenblockung: '',
				wochenrythmus: '',
				anmerkung: '',
				lektor: '',
				oldlektor: '',
				lehreinheit_ids: []
			};
			this.selectedRow = null;
		},
	},
	template: `
		<core-base-layout>
			<template #main>
			<h5>{{$p.t('lehre', 'studiensemester')}}: {{studiensemester.join(', ')}}</h5>
				<core-filter-cmpt
						ref="lehreTable"
						:tabulator-options="tabulatorOptions"
						@nw-new-entry="newSideMenuEntryHandler"
						:table-only=true
						:hideTopMenu=false
						:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited}, { event: 'tableBuilt', handler: tableBuilt }]"
				>
					<template #actions>
						<button class="btn btn-primary" @click="lektorMail">EMail an Lektor</button>
						<button class="btn btn-primary" @click="assistenzMailButton">EMail an Assistenz</button>
					</template>
				</core-filter-cmpt>
				<bs-modal ref="editModal" class="bootstrap-prompt" dialogClass="modal-lg" @hidden-bs-modal="reset">
					<template #title>{{ modalTitle }}</template>
					<template #default>
						<div class="row row-cols-2">
							<div class="col">
								<!--<form-input
									type="autocomplete"
									v-model="formData.raumtyp"
									:suggestions="filteredRaumtypen"
									field="label"
									placeholder="Raumtypen auswählen"
									@complete="searchRaumtyp" 
									:label="$p.t('lehre', 'raumtyp')"
								></form-input>-->
							</div>
							<!--<div class="col">
								<form-input
									type="autocomplete"
									v-model="formData.raumtypalternativ"
									:suggestions="filteredRaumtypen"
									field="label"
									placeholder="Raumtypen auswählen"
									@complete="searchRaumtyp"
									:label="$p.t('lehre', 'raumtypalternative')"
									dropdown 
								></form-input>
								
							</div>-->
						</div>
						<!--<div class="row row-cols-3">
							<div class="col">
								<form-input
									type="number"
									v-model="formData.start_kw"
									name="startkw"
									:label="$p.t('lehre', 'startkw')"
								>
								</form-input>
							</div>
							<div class="col">
								<form-input
									type="number"
									v-model="formData.stundenblockung"
									name="stundenblockung"
									:label="$p.t('lehre', 'stundenblockung')"
								>
								</form-input>
							</div>
							<div class="col">
								<form-input
									type="number"
									v-model="formData.wochenrythmus"
									name="wochenrythmus"
									:label="$p.t('lehre', 'wochenrythmus')"
								>
								</form-input>
							</div>
						</div>
						<hr />-->
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="autocomplete"
									:label="$p.t('lehre', 'lektor')"
									:suggestions="filteredLektor"
									field="label"
									v-model="formData.lektor"
									@complete="searchLektor"
								></form-input>
							</div>
							<div class="col">
								<form-input
									v-model="formData.anmerkung"
									name="raumtypalternativ"
									:label="$p.t('ui', 'infoandepl/kfl')"
								>
								</form-input>
							</div>
						</div>
					</template>
					<template #footer>
						<button type="button" class="btn btn-primary" @click="updateLehreinheit">{{ $p.t('ui', 'speichern') }}</button>
					</template>
				</bs-modal>

				<bs-modal ref="faktorModal" class="bootstrap-prompt" dialogClass="modal-xl" @hidden-bs-modal="reset">
					<template #title>{{ modalTitle }} - {{ formDataFaktor.bezeichnung }}</template>
						<div class="row row-cols-4">
							<div class="col">
								<form-input
									label="Gültig ab"
									v-model="formDataFaktor.updatestudiensemester"
									readonly
								></form-input>
							</div>
							<div class="col">
								<form-input
									label="Faktor"
									v-model="formDataFaktor.faktor"
									readonly
								></form-input>
							</div>
							<div class="col">
								<form-input
									v-model="formDataFaktor.lvstunden"
									label="Stunden"
									readonly
								>
								</form-input>
							</div>
							<div class="col">
								<form-input
									v-model="formDataFaktor.lvstundenfaktor"
									label="Realstunden"
									type="number"
									@change="recalcFaktor"
								>
								</form-input>
							</div>
							
						</div>
						<core-filter-cmpt
							ref="faktorTable"
							:tabulator-options="faktorTabulatorOptions"
							:table-only=true
							:hideTopMenu=false
							:sideMenu=false
						/>
					<template #footer>
						<button type="button" class="btn btn-primary" @click="updateFaktor">{{ $p.t('ui', 'speichern') }}</button>
					</template>
				</bs-modal>
			</template>
		</core-base-layout>
	`
};
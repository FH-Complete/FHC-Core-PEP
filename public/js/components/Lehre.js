import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import BsModal from '../../../../js/components/Bootstrap/Modal.js';
import FormInput from "../../../../js/components/Form/Input.js";
import FhcLoader from '../../../../js/components/Loader.js';
import { tagHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import { extendedHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import { dateFilter } from "../../../../js/tabulator/filters/Dates.js";
import { Tags as ApiLehreTag} from "../api/tags.js";
import ApiLehre from "../api/lehre.js";
import Tag from '../../../../js/components/Tag/Tag.js';

import {formatter} from "../mixins/formatters.js";
import tagMixin from "../mixins/tag.js";


export default {
	name: "Lehre",
	components: {
		CoreFilterCmpt,
		BsModal,
		FormInput,
		CoreBaseLayout,
		FhcLoader,
		Tag
	},
	props: {
		config: null,
		modelValue: {
			type: Object,
			required: true
		},
	},
	mixins: [tagMixin],
	mounted(){
		//this.getRaumtypen();
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
				updatestudiensemester: '',
				lehrform_kurzbz: '',

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
			modalTitle: 'Änderungen',
			selectedColumnValues: [],
			columnsToMark: ['anmerkung'],
			tagEndpoint: ApiLehreTag
		}
	},

	computed: {
		tabulatorOptions()
		{
			return {
				index: "row_index",
				height: '60vh',
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: (row) =>
				{
					if (row.getElement().classList.contains("tabulator-calcs"))
						return;

					let columns = row.getTable().getColumns();

					this.columnsToMark.forEach((spaltenName) => {
						let column = columns.find(col => col.getField() === spaltenName);
						if (column) {
							let cellElement = row.getCell(column).getElement();
							cellElement.classList.add("highlight-warning");
						}
					});
				},
				persistenceID: "2025_03_26_pep_lehre",
				persistence: true,
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
						title: 'Tags',
						field: 'tags',
						tooltip: false,
						headerFilter: true,
						headerFilterFunc: tagHeaderFilter,
						formatter: (cell) => formatter.tagFormatter(cell, this.$refs.tagComponent),
						width: 150
					},
					{
						title: 'Planungsstatus',
						field: 'tagstatus',
						tooltip: false,
						headerFilter: true,
						headerFilterFunc: tagHeaderFilter,
						formatter: (cell) => formatter.tagFormatter(cell, this.$refs.tagComponent),
						width: 150
					},
					{
						title: 'Aktionen',
						field: 'actions',
						width: 110,
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
					{title: 'LV Org Form', field: 'lv_orgform', headerFilter: true},
					{title: 'LV Organisationseinheit', field: 'lv_oe', headerFilter: true, visible: true},
					{title: 'LV Bezeichnung', field: 'lv_bezeichnung', headerFilter: true},
					{title: 'LV Kurzbz', field: 'lv_kurzbz', headerFilter: true, visible: false},
					{title: 'LV Semester', field: 'lv_semester', headerFilter: true, visible:false},
					{title: 'LE Semester', field: 'studiensemester_kurzbz', headerFilter: true, visible:false},
					{title: 'LE Unterrichtssprache', field: 'le_unterrichtssprache', headerFilter: true},
					{title: 'Gruppe', field: 'gruppe', headerFilter: true, visible:false},
					{title: 'LE ID', field: 'lehreinheit_id', headerFilter: true, visible:false},
					{title: 'LE Lehrform', field: 'lehrform_kurzbz', headerFilter: true},
					{title: 'Lektor*in', field: 'lektor', headerFilter: true},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'lektor_nachname', headerFilter: true},
					{title: 'Hinzugefuegt am', field: 'insertamum', formatter: formatter.dateFormatter, headerFilterFunc: 'dates', headerFilter: dateFilter},
					{title: 'Updated am', field: 'updateamum', formatter: formatter.dateFormatter, headerFilterFunc: 'dates', headerFilter: dateFilter},
					{title: 'Updated von', field: 'lehreinheitupdatevon', headerFilter: true},
					{title: 'Info LV-Planung', field: 'lv_anmerkung', headerFilter: "input", tooltip: false,
						formatter: function (cell, formatterParams, onRendered) {
							const value = cell.getValue();
							if (!value) return "";
							const firstLine = value.split("\n")[0];
							let hasMore = value.split("\n")[1] !== undefined;
							const div = document.createElement("div");
							div.title = value;
							div.textContent = hasMore ? firstLine + " ..." : firstLine;
							return div;
						},
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", editor: "textarea", formatter: "textarea", visible: false},
					{title: 'LV Leitung', field: 'lehrfunktion_kurzbz', headerFilter: true, visible: false},
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
					{title: 'Faktor', field: 'faktor', headerFilter: true, visible: true, hozAlign:"right",
						formatter: (cell) => {
							return isNaN(cell.getValue()) || !cell.getValue()  ? '-' : parseFloat(cell.getValue()).toFixed(2);
						}},
					{title: 'LE Stundensatz', field: 'le_stundensatz', headerFilter: true, hozAlign:"right"},
					{title: 'LV-Plan Stunden', field: 'lv_plan_stunden', headerFilter: true, hozAlign:"right"},
					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stundensatz', field: 'zrm_stundensatz_lehre', headerFilter: "input", visible: false, hozAlign:"right", tooltip: formatter.stundensatzLehreToolTip},
					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stundensatz - Lehre', field: 'akt_stundensaetze_lehre', hozAlign:"right", headerFilter: "input", formatter:"textarea", visible: false},
					{title: 'Vorjahres Lektoren', field: 'vorjahreslektoren', headerFilter: "input", visible: true},
					{title: 'Raumtyp', field: 'raumtyp', headerFilter: "input", visible: false},
					{title: 'Raumtypalternativ', field: 'raumtypalternativ', headerFilter: "input",visible: false},
					{title: 'Wochenrythmus', field: 'wochenrythmus', headerFilter: "input", visible: false},
					{title: 'Start_kw', field: 'start_kw', headerFilter: "input", visible: false},
					{title: 'Stundenblockung', field: 'stundenblockung', headerFilter: "input", visible: false},
					{title: 'Lehrauftrag Status', field: 'lehrauftrag_status', headerFilter: "input", visible: false},
				],
			}
		},
		faktorTabulatorOptions() {
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				persistenceID: "2024_12_11_pep_lehre_faktor",
				columns: [
					{title: 'Lektor*in', field: 'kurzbz', width: 150},
					{title: 'Vorname', field: 'vorname', width: 200},
					{title: 'Nachname', field: 'nachname', width: 200},
					{title: 'Faktor', field: 'faktor', hozAlign: "right", width: 100,
						formatter: (cell) => {
							return this.formDataFaktor.faktor
						}
					},
					{
						title: 'Semesterstunden',
						field: 'semesterstunden',
						hozAlign: "right",
						width: 150
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
		updateSelectedRows() {
			this.selectedRows = this.$refs.lehreTable.tabulator.getSelectedRows();
			this.selectedColumnValues = this.selectedRows.map(row => row.getData().lehreinheit_id);
			//this.selectedColumnValues = [...new Set(this.selectedRows.map(row => row.getData().lehreinheit_id))];
			this.addColorToInfoText(this.selectedColumnValues);
		},

		newSideMenuEntryHandler: function (payload)
		{
			this.appSideMenuEntries = payload;
		},
		async loadData(data) {
			this.studiensemester = this.theModel.config.semester;
			this.loadedData = data;
			await this.$api.call(ApiLehre.getLehre(data))
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
			if (!stunden || isNaN(stunden) || stunden == "0.00")
				return;
			let newFaktor = stundenfaktor/stunden

			this.formDataFaktor.faktor = parseFloat(newFaktor).toFixed(2);
			this.$refs.faktorTable.tabulator.redraw(true)

		},
		editFaktor(data, row)
		{
			this.getLehreinheiten(data.lv_id, data.lehrform_kurzbz, data.studiensemester_kurzbz)
		},
		getLehreinheiten(lv_id, lehrform_kurzbz, studiensemester_kurzbz)
		{
			let data = {
				'lehrveranstaltung_id': lv_id,
				'le_studiensemester_kurzbz': studiensemester_kurzbz,
				'studiensemester': this.studiensemester,
				'lehrform_kurzbz': lehrform_kurzbz
			}

			this.$api.call(ApiLehre.getLehreinheiten(data))
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

			this.$api.call(ApiLehre.getLehreinheit(data))
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
			this.formData.le_semester = data.studiensemester_kurzbz;

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

			/*const selectedRaumtyp = this.raumtypen.find(raumtyp => raumtyp.raumtyp_kurzbz === data.raumtyp);

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
			}*/
			this.$refs.editModal.show();
		},
		prefillFaktorModal(data)
		{
			this.formDataFaktor = {
				bezeichnung: data[0].bezeichnung,
				updatestudiensemester: data[0].studiensemester_kurzbz,
				lvstunden: data[0].lvstunden,
				faktor: isNaN(parseFloat(data[0].faktor).toFixed(2)) ? "" : parseFloat(data[0].faktor).toFixed(2),
				lvstundenfaktor: parseFloat(data[0].faktor * data[0].lvstunden).toFixed(2),
				lv_id: data[0].lehrveranstaltung_id,
				semester: data[0].studiensemester_kurzbz,
				lehrform_kurzbz: data[0].lehrform_kurzbz,
			}

			this.$refs.faktorModal.show();

			Vue.nextTick(() => {
				this.$refs.faktorTable.tabulator.setData(data);
			});
		},
		//Wird derzeit nicht benötigt
		/*getRaumtypen() {
			this.$api.call(ApiLehre.getRaumtypen())
				.then(result => result.data)
				.then(result => {
					this.raumtypen = result;
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},*/
		getLektoren() {
			this.$api.call(ApiLehre.getLektoren())
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

			window.location.href = `mailto:${emails}`;
		},
		updateLehreinheit()
		{
			/*const selectedRows = this.$refs.lehreTable.tabulator.getSelectedRows();

			selectedRows.forEach(row => {
				let rowData = row.getData()
				if (rowData.editable === false)
					return;
				this.formData.lehreinheit_ids.push(
					{row_index: rowData.row_index,
						lehreinheit_id: rowData.lehreinheit_id,
						uid: rowData.uid,
						le_semester: rowData.studiensemester_kurzbz
					})
			})*/
			this.$api.call(ApiLehre.saveLehreinheit(this.formData))
				.then(result => result.data)
				.then(updateData => {
					/*
					Note (david): wechsel soll vorerst nur pro Zeile möglich sein
					if (Array.isArray(updateData.lehreinheiten_ids) && updateData.lehreinheiten_ids.length !== 0)
					{
						updateData.lehreinheiten_ids.forEach(row => {
							this.$refs.lehreTable.tabulator.updateRow(row.id, {
								'lektor' : updateData.lektor,
								'vorname' : updateData.vorname,
								'lektor_nachname' : updateData.nachname,
								'zrm_vertraege' : updateData.zrm_vertraege,
								'zrm_wochenstunden' : updateData.zrm_wochenstunden,
								'zrm_jahresstunden' : updateData.zrm_jahresstunden,
								'le_stundensatz' : row.le_stundensatz,
								'bezeichnung' : updateData.bezeichnung,
								'akt_orgbezeichnung' : updateData.akt_orgbezeichnung,
								'akt_parentbezeichnung' : updateData.akt_parentbezeichnung,
								'akt_stunden' : updateData.akt_stunden,
								'akt_stundensaetze_lehre' : updateData.akt_stundensaetze_lehre,
								'uid' : updateData.uid,
								'updateamum' : updateData.updateamum,
							})
						});
					}
					else
					{*/
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
							'lehreinheitupdatevon' : updateData.lehreinheitupdatevon,
						})
				/*	}*/
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
				})
				.then(() => this.resetFormData())
				.then(() => this.$refs.editModal.hide())
				.then(() => this.theModel = { ...this.modelValue, needReload: true })
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		updateFaktor()
		{
			this.$api.call(ApiLehre.updateFaktor(this.formDataFaktor))
				.then(result => result.data)
				.then(updateData => {
					this.$refs.faktorModal.hide();

					this.$refs.loader.show();
					this.loadData(this.theModel.config).then(() => {
						this.$refs.loader.hide()

					});

					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
				})
				.then(() => this.theModel = { ...this.modelValue, needReload: true })
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		onCellEdited(cell)
		{
			let value = cell.getValue();
			if (!value.trim() && (cell.getOldValue() === value.trim()))
				return;

			if (value === cell.getOldValue())
				return;

			let data = cell.getRow().getData();

			let updateData = {
				'anmerkung': value,
				'uid': data.uid,
				'lehreinheit_id': data.lehreinheit_id
			}

			this.updateAnmerkung(updateData);
			cell.getRow().reformat();
		},
		updateAnmerkung(data)
		{
			this.$api.call(ApiLehre.updateAnmerkung(data))
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
		resetFaktorFormData()
		{
			this.formDataFaktor = {
				bezeichnung: '',
				faktor: '',
				lvstunden: 0,
				lvstundenfaktor: '',
				lv_id: '',
				updatestudiensemester: '',
				lehrform_kurzbz: '',
			};
			this.selectedRow = null;
		},
		addedTag(addedTag) {
			this.addTagInTable(addedTag, 'lehreTable', 'lehreinheit_id', 'response', tag => this.config.planungsstatus.includes(tag.tag_typ_kurzbz) ? 'tagstatus' : 'tags');
		},
		deletedTag(deletedTag) {
			this.deleteTagInTable(deletedTag, 'lehreTable', ['tags', 'tagstatus'])

		},
		updatedTag(updatedTag) {
			this.updateTagInTable(updatedTag, 'lehreTable', ['tags', 'tagstatus'])
		}
	},
	template: `
	
		<core-base-layout>
			<template #main>
			<h5 
			:class="theModel.config.matched ? '' : 'mismatch'"
			:title="theModel.config.matched ? '' : 'Die Auswahl des Studienjahres im Start-Tab weicht ab'"
			>{{$p.t('lehre', 'studiensemester')}}: {{studiensemester.join(', ')}} </h5>
				<core-filter-cmpt
						ref="lehreTable"
						:tabulator-options="tabulatorOptions"
						@nw-new-entry="newSideMenuEntryHandler"
						:table-only=true
						:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited},
											{ event: 'tableBuilt', handler: tableBuilt }, 
											{ event: 'rowSelectionChanged', handler: updateSelectedRows }]"
				>
					<template #actions>
						<button class="btn btn-primary" @click="lektorMail">EMail an Lektor</button>
						<button class="btn btn-primary" @click="assistenzMailButton">EMail an Assistenz</button>
						<Tag ref="tagComponent"
							:endpoint="tagEndpoint"
							:values="selectedColumnValues"
							@added="addedTag"
							@deleted="deletedTag"
							@updated="updatedTag"
							zuordnung_typ="lehreinheit_id"
						></Tag>
					</template>
				</core-filter-cmpt>
				<bs-modal ref="editModal" class="bootstrap-prompt" dialogClass="modal-lg" @hidden-bs-modal="resetFormData">
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

				<bs-modal ref="faktorModal" class="bootstrap-prompt" dialogClass="modal-xl" @hidden-bs-modal="resetFaktorFormData">
					<template #title>{{ modalTitle }} - {{ formDataFaktor.bezeichnung }}</template>
						<div class="row row-cols-5">
							<div class="col">
								<form-input
									label="Gültig ab"
									v-model="formDataFaktor.updatestudiensemester"
									readonly
								></form-input>
							</div>
							<div class="col">
								<form-input
									label="Lehrform"
									v-model="formDataFaktor.lehrform_kurzbz"
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
									@input="recalcFaktor"
								>
								</form-input>
							</div>
							
						</div>
						<core-filter-cmpt
							ref="faktorTable"
							:tabulator-options="faktorTabulatorOptions"
							:table-only=true
							:sideMenu=false
						/>
					<template #footer>
						<button type="button" class="btn btn-primary" @click="updateFaktor">{{ $p.t('ui', 'speichern') }}</button>
					</template>
				</bs-modal>
			</template>
		</core-base-layout>
		<fhc-loader ref="loader" :timeout="0"></fhc-loader>
	`
};
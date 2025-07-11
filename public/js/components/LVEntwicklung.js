import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import BsModal from '../../../../js/components/Bootstrap/Modal.js';
import FormInput from "../../../../js/components/Form/Input.js";
import FhcLoader from '../../../../js/components/Loader.js';
import { tagHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import { extendedHeaderFilter } from "../../../../js/tabulator/filters/extendedHeaderFilter.js";
import Tag from '../../../../js/components/Tag/Tag.js';
import { ApiLVEntwicklungTag } from "../api/lventwicklungTabTags.js";

import {formatter} from "../mixins/formatters.js";
import ApiLehre from "../api/lehre.js";
import ApiLVEntwicklung from "../api/lventwicklung.js";
import focusMixin from "../mixins/focus.js";
import tagMixin from "../mixins/tag.js";


export default {
	name: "LVEntwicklung",
	components: {
		CoreFilterCmpt,
		BsModal,
		FormInput,
		CoreBaseLayout,
		FhcLoader,
		Tag
	},
	mixins: [focusMixin, tagMixin],
	props: {
		config: null,
		modelValue: {
			type: Object,
			required: true
		},
	},
	data: function() {
		return {
			mitarbeiterStammdaten: {},
			mitarbeiterListe: null,
			rollenListe: null,
			statusListe: null,
			lvListe: null,
			studiensemester: [],
			filteredEmp: [],
			filteredLv: [],
			filteredRaumtypen: [],
			selectedLektor: null,
			selectedRow: null,
			selectedColumnValues: [],
			focusFields: ['anmerkung', 'status_kurzbz', 'rolle_kurzbz', 'stunden', 'studiensemester_kurzbz', 'werkvertrag_ects', 'weiterentwicklung', 'mitarbeiter_uid'],
			columnsToMark: ['anmerkung', 'status_kurzbz', 'rolle_kurzbz', 'stunden', 'studiensemester_kurzbz', 'werkvertrag_ects', 'weiterentwicklung', 'mitarbeiter_uid'],
			tagEndpoint: ApiLVEntwicklungTag,
			loaded: false,
			modalTitle: 'Neue Weiterentwicklung',
			formData: {
				mitarbeiter_uid: '',
				lehrveranstaltung_id: '',
				studiensemester_kurzbz: '',
				rolle_kurzbz: '',
				status_kurzbz: '',
				werkvertrag_ects: '',
				stunden: '',
				anmerkung: '',
			},
		}
	},
	computed: {
		tabulatorOptions()
		{
			return {
				index: "pep_lv_entwicklung_id",
				height: '60vh',
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				rowFormatter: (row) =>
				{
					if (row.getElement().classList.contains("tabulator-calcs"))
						return;

					let columns = row.getTable().getColumns();
					let data = row.getData();
					if (data.geloescht)
					{
						columns.forEach((column) => {
							let cellElement = row.getCell(column).getElement();
							cellElement.classList.add("highlight-error");
						});
					}
					else if (!data.istemplate)
					{
						let cellElement = row.getCell("lvbezeichnung").getElement();
						cellElement.classList.add("highlight-info");
					}

					this.columnsToMark.forEach((spaltenName) => {
						let column = columns.find(col => col.getField() === spaltenName);
						if (column) {
							let cellElement = row.getCell(column).getElement();
							cellElement.classList.add("highlight-warning");
						}
					});
				},
				persistenceID: "2025_06_02_v5_pep_lventwicklung_v3",
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
						title: 'Aktionen',
						field: 'actions',
						width: 100,
						formatter: (cell, formatterParams, onRendered) =>
						{
							if (!cell.getData().mitarbeiter_uid && !cell.getData().pep_lv_entwicklung_id)
								return;
							let container = document.createElement('div');
							container.className = "d-flex gap-1";
							if (cell.getData().mitarbeiter_uid)
							{
								container.className = "d-flex gap-2";
								let duplicateButton = document.createElement('button');
								duplicateButton.className = 'btn btn-outline-secondary';
								duplicateButton.innerHTML = '<i class="fa fa-plus"></i>';
								duplicateButton.addEventListener('click', (event) =>
									this.duplicateRow(cell)
								);
								container.append(duplicateButton);
							}

							if (cell.getData().pep_lv_entwicklung_id)
							{
								let deleteButton = document.createElement('button');
								deleteButton.className = 'btn btn-outline-secondary';
								deleteButton.innerHTML = '<i class="fa fa-minus"></i>';
								deleteButton.addEventListener('click', (event) => {
									deleteButton.disabled = true;
									this.deleteRow(cell);
								});
								container.append(deleteButton);
							}

							return container;
						},
					},
					{
						title: 'Weiterentwicklung',
						field: 'weiterentwicklung',
						headerFilter: "tickCross",
						headerFilterParams:{"tristate": true},
						formatter: (cell, formatterParams, onRendered) => {
							let data = cell.getData();
							if (data.pep_lv_entwicklung_id === null)
								return "";
							if (cell.getValue() === true)
								return '<i class="fa fa-check text-success"></i>'
							else
								return '<i class="fa fa-xmark text-danger"></i>'

						},


						editor: "tickCross",
						editorParams: {
							tristate: false
						},
						hozAlign: "center",
						formatterParams: {
							tickElement: '<i class="fa fa-check text-success"></i>',
							crossElement: '<i class="fa fa-xmark text-danger"></i>'
						},

					},
					{title: 'STG', field: 'stg_kuerzel', headerFilter: true},
					{
						title: 'Lehrveranstaltung',
						field: 'lvbezeichnung',
						headerFilter: "input",
						width: 400,
						formatter: (cell, formatterParams, onRendered) => {
							const rowData = cell.getRow().getData();
							return `[${rowData.lehrveranstaltung_id}] ${rowData.lvbezeichnung}`;
						},
					},
					{
						title: 'UID',
						field: 'mitarbeiter_uid',
						editor: "list",
						headerFilter: "input",
						width: 400,
						editorParams:() => {
							return {
								values: this.mitarbeiterListe,
								autocomplete: true,
								allowEmpty : true,
								clearable: true,
								listOnEmpty: true,
								dropdownAlign: "left",
							}
						},
						formatter: (cell, formatterParams, onRendered) => {
							const value = cell.getValue();
							return this.mitarbeiterListe?.[value] || "";
						},
					},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{
						title: 'Stunden',
						field: 'stunden',
						editor: "number",
						headerFilter: "input",
						bottomCalcParams: {precision: 2},
						editable: (cell) => {
							const rowData = cell.getRow().getData();
							const vertragsListe = (rowData.zrm_vertraege_kurzbz || '').split('\n');
							const editable = vertragsListe.every(
								vertrag => !this.config.allow_volume_edit_contracts.includes(vertrag)
							);
							const stundenvorhanden = !!cell.getValue();
							return !!rowData.mitarbeiter_uid && (editable || stundenvorhanden);
						},
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

					{title: 'Überarbeitungssemester', field: 'studiensemester_kurzbz',
						editable: (cell) => !!cell.getRow().getData().mitarbeiter_uid,
						headerFilter: "input", editor: "list",
						editorParams:() => {
							return {
								values: this.studiensemester,
								autocomplete: true,
								allowEmpty : false,
								clearable: true,
								listOnEmpty: true,
								dropdownAlign: "left",
							}
						},
						mutator: function(value, data, type, params, component)
						{
							if (data.pep_lv_entwicklung_id === null)
							{
								let typ = value.slice(0, 2);
								let jahr = parseInt(value.slice(2), 10);

								if (typ === "WS")
								{
									return "SS" + jahr;
								}
								else if (typ === "SS")
								{
									return "WS" + (jahr - 1);
								}
								else
								{
									return value;
								}
							}
							else
								return value;
						}
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", editor: "textarea", formatter: "textarea", editable: (cell) => !!cell.getRow().getData().mitarbeiter_uid},
					{
						title: 'Rolle',
						field: 'rolle_kurzbz',
						editor: "list",
						editable: (cell) => !!cell.getRow().getData().mitarbeiter_uid,
						width: 400,
						headerFilter: "select",
						headerFilterParams: { values: this.headerFilterRollen },
						editorParams:() => {
							return {
								values: this.rollenListe,
								autocomplete: true,
								allowEmpty : true,
								clearable: true,
								listOnEmpty: true,
								dropdownAlign: "left",
							}
						},
						formatter: (cell, formatterParams, onRendered) => {
							return this.rollenListe ? this.rollenListe[cell.getValue()] : cell.getData().rolle_kurzbz;
						},
					},
					{
						title: 'Status',
						field: 'status_kurzbz',
						editor: "list",
						headerFilter: "select",
						headerFilterParams: { values: this.headerFilterStatus },
						width: 400,
						editorParams:() => {
							return {
								values: this.statusListe,
								autocomplete: true,
								allowEmpty : true,
								clearable: true,
								listOnEmpty: true,
								dropdownAlign: "left",
							}
						},
						editable: (cell) => {
							const rowData = cell.getRow().getData();
							const vertragsListe = (rowData.zrm_vertraege_kurzbz || '').split('\n');
							const ectsvorhanden = !!cell.getValue();
							return !!rowData.mitarbeiter_uid && (vertragsListe.some(vertrag => this.config.allow_volume_edit_contracts.includes(vertrag)) || ectsvorhanden);
						},
						formatter: (cell, formatterParams, onRendered) => {
							return this.statusListe ? this.statusListe[cell.getValue()] : cell.getData().status_kurzbz;
						},
					},
					{
						title: 'Werkvertragsvolumen in ECTS',
						field: 'werkvertrag_ects',
						editor: "number",
						headerFilter: "input",
						bottomCalcParams: {precision: 2},
						editable: (cell) => {
							const rowData = cell.getRow().getData();
							const vertragsListe = (rowData.zrm_vertraege_kurzbz || '').split('\n');
							const ectsvorhanden = !!cell.getValue();
							return !!rowData.mitarbeiter_uid && (vertragsListe.some(vertrag => this.config.allow_volume_edit_contracts.includes(vertrag)) || ectsvorhanden);
						},
						bottomCalc: "sum",
						hozAlign: "right",
						sorter:"number",
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
						title: 'Werkvertragsvolumen in EUR',
						field: 'volumen_eur',
						hozAlign: "right",
						headerFilter: "input",
						bottomCalc: "sum",
						sorter:"number",
						bottomCalcParams: { precision: 2 },
						mutator: function(value, data, type, params, component) {
							let ects = parseFloat(data.werkvertrag_ects);
							let status = data.status_kurzbz;
							if (isNaN(ects)) return 0;
							let faktor = status === "new" ? 640 : (status === "kvp" ? 320 : 0);
							return ects * faktor;
						},
						formatter: function(cell) {
							let value = cell.getValue();
							return value.toFixed(2).replace(".", ",") + " €";
						}
					},
					{title: 'KF der LV', field: 'lv_oe_bezeichnung', headerFilter: true},
					{title: 'LV Semester', field: 'lv_semester', headerFilter: true, visible: false},
					{title: 'LV Kurzbz', field: 'lv_kurzbz', headerFilter: true, visible: false},
					{title: 'LV Sprache', field: 'lv_sprache', headerFilter: true},
					{title: 'LV ECTS', field: 'lv_ects', headerFilter: true},
					{title: 'LV Lehrform', field: 'lv_lehrform_kurzbz', headerFilter: true},
					{title: 'Modul', field: 'modulbezeichnung', headerFilter: "input", formatter: "textarea"},


					{title: 'Zrm - DV', field: 'zrm_vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'zrm_wochenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'zrm_jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stundensatz', field: 'zrm_stundensatz_lehre', headerFilter: "input", visible: false, hozAlign:"right", tooltip: formatter.stundensatzLehreToolTip},
					{title: 'Akt - DV', field: 'akt_bezeichnung', headerFilter: "input", formatter: "textarea",  visible: false},
					{title: 'Akt - OE Mitarbeiter*in', field: 'akt_orgbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - OE Mitarbeiter*in - Parent', field: 'akt_parentbezeichnung', headerFilter: "input", formatter: "textarea", visible: false},
					{title: 'Akt - Stunden', field: 'akt_stunden', hozAlign:"right", headerFilter: "input", formatter: "textarea", visible: false},

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
	async created() {
		await this.fetchMitarbeiter();
		await this.fetchRollen();
		await this.fetchStatus();
		this.loaded = true;
	},
	mounted() {

	},
	methods: {

		tableBuilt(){
			this.theModel = { ...this.modelValue, loadDataReady: true };
			this.addFocus("lventwicklungtable", this.focusFields);
		},
		updateSelectedRows() {
			this.selectedRows = this.$refs.lventwicklungtable.tabulator.getSelectedRows();
			this.selectedColumnValues = this.selectedRows.map(row => row.getData().pep_lv_entwicklung_id).filter((row) => row !== null);

		},

		async fetchMitarbeiter()
		{
			await this.$api.call(ApiLehre.getLektoren())
				.then(response => {
						this.mitarbeiterListe = response.data
						.reduce((acc, mitarbeiter) => {
							const key = mitarbeiter.uid;
							acc[key] = `[${mitarbeiter.uid}] ${mitarbeiter.vorname} ${mitarbeiter.nachname}`;
							this.mitarbeiterStammdaten[key] = {
								vorname: mitarbeiter.vorname,
								nachname: mitarbeiter.nachname
							};

							return acc;
						}, {});
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		async fetchRollen()
		{
			await this.$api.call(ApiLVEntwicklung.getRollen())
				.then(response => {

						this.rollenListe = response.data
						.reduce((acc, rolle) => {
							const key = rolle.rolle_kurzbz;
							acc[key] = rolle.bezeichnung;
							return acc;
						}, {});

					this.headerFilterRollen = { "": "Alle", ...this.rollenListe }
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		async fetchStatus()
		{
			await this.$api.call(ApiLVEntwicklung.getStatus())
				.then(response => {
						this.statusListe = response.data
							.reduce((acc, rolle) => {
								const key = rolle.status_kurzbz;
								acc[key] = rolle.bezeichnung;
								return acc;
							}, {});

					this.headerFilterStatus = { "": "Alle", ...this.statusListe }

				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},

		newSideMenuEntryHandler: function (payload)
		{
			this.appSideMenuEntries = payload;
		},
		async loadData(data) {
			if (JSON.stringify(data) !== JSON.stringify(this.loadedData))
			{
				this.futureLVs = null;
			}
			this.studiensemester = this.theModel.config.semester;
			this.loadedData = data;
			await this.$api.call(ApiLVEntwicklung.getLVs(data))
				.then(response => {
					if (response.data.length === 0)
					{
						this.$fhcAlert.alertInfo("LVEntwicklung: Keine Daten vorhanden");
						this.$refs.lventwicklungtable.tabulator.setData([]);
					}
					else
					{

						this.$refs.lventwicklungtable.tabulator.setData(response.data);

					}

				})

				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
		duplicateRow(cell)
		{
			let row = cell.getRow()
			let rowData = row.getData();
			if (!rowData.mitarbeiter_uid)
				return;

			let newData = { ...rowData };

			newData.pep_lv_entwicklung_id = null;
			newData.stammdaten_studiensemester = this.studiensemester;

			this.update(newData).then((id) => {
				this.$refs.lventwicklungtable.tabulator.addRow(id, false, row);
				let newRow = this.$refs.lventwicklungtable.tabulator.getRow(id.pep_lv_entwicklung_id);
				let children = newRow.getElement().childNodes;

				children.forEach((child) => {
					child.classList.add("highlight-success");
				})

				setTimeout(function(){
					children.forEach((child) => {
						child.classList.remove("highlight-success");
					})
				}, 1000);
			})


		},
		deleteRow(cell)
		{
			let row = cell.getRow();
			let data = row.getData();

			let deleteData = {
				pep_lv_entwicklung_id: data.pep_lv_entwicklung_id
			}

			let counts = this.lvCount(data.lehrveranstaltung_id)

			return this.$api.call(ApiLVEntwicklung.delete(deleteData))
				.then(() => {
					let children = row.getElement().childNodes;
					children.forEach((child) => {
						child.classList.add("highlight-alert");
					})

					if (counts === 1 && !data.geloescht)
					{
						row.update({
							mitarbeiter_uid: null,
							vorname: null,
							nachname: null,
							weiterentwicklung: false,
							rolle_kurzbz: null,
							stunden: null,
							ects: null,
							status_kurzbz: null,
							pep_lv_entwicklung_id: null,
							werkvertrag_ects: null,
							volumen_eur: null,
							zrm_vertraege_kurzbz: null,
							zrm_vertraege: null,
							zrm_wochenstunden: null,
							zrm_jahresstunden: null,
							zrm_stundensatz_lehre: null,
							akt_bezeichnung: null,
							akt_orgbezeichnung: null,
							akt_parentbezeichnung: null,
							akt_stunden: null,
							anmerkung: null
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
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
				.finally(() => {
					this.updateSelectedRows();
				});
		},

		lvCount(lv_id)
		{
			let count = 0;
			for (let row of this.$refs.lventwicklungtable.tabulator.getRows())
			{
				if (row.getData().lehrveranstaltung_id === lv_id)
				{
					count++;
				}
			}
			return count;
		},
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
		resetFormData()
		{
			this.formData = {
				mitarbeiter_uid: '',
				lehrveranstaltung_id: '',
				studiensemester_kurzbz: '',
				rolle_kurzbz: '',
				status_kurzbz: '',
				werkvertrag_ects: '',
				stunden: '',
				anmerkung: '',
				lehrveranstaltung_label: null,
				mitarbeiter_label: null,
			};
		},
		searchEmp(event)
		{
			const query = event.query.toLowerCase().trim();

			this.filteredEmp = Object.entries(this.mitarbeiterStammdaten)
				.filter(([uid, lektor]) => {
					const fullName = `${lektor.vorname.toLowerCase()} ${lektor.nachname.toLowerCase()}`;
					const reverseFullName = `${lektor.nachname.toLowerCase()} ${lektor.vorname.toLowerCase()}`;
					return fullName.includes(query) || reverseFullName.includes(query) || uid.toLowerCase().includes(query);
				})
				.map(([uid, lektor]) => ({
					label: `${lektor.nachname} ${lektor.vorname} (${uid})`,
					uid
				}));
		},
		searchLv(event)
		{
			const query = event.query.toLowerCase().trim();

			this.filteredLv = this.futureLVs
				.filter(lv =>
					lv.id.includes(query) ||
					lv.lvbezeichnung.toLowerCase().includes(query) ||
					lv.studiengang.toLowerCase().includes(query)
				)
				.map(lv => ({
					lehrveranstaltung_id: lv.id,
					label: lv.label,
					studiengang: lv.studiengang
				}));
		},
		onLvSelected(selectedLv) {
			this.formData.lehrveranstaltung_id = selectedLv.value.lehrveranstaltung_id;
		},
		onEmpSelected(selectedEmp) {
			this.formData.mitarbeiter_uid = selectedEmp.value.uid;
		},
		onCellEdited(cell)
		{
			let field = cell.getField();
			let row = cell.getRow();
			let rowData = row.getData();

			let value = cell.getValue();
			let oldValue = cell.getOldValue();

			if (value === oldValue)
				return;

			if (rowData.pep_lv_entwicklung_id === null && (field !== "mitarbeiter_uid") && field !== "weiterentwicklung")
			{
				return;
			}

			this.updateEntwicklung(row, field)
		},
		addedTag(addedTag) {
			this.addTagInTable(addedTag, 'lventwicklungtable', 'pep_lv_entwicklung_id', 'response');
		},
		deletedTag(deletedTag) {
			this.deleteTagInTable(deletedTag, 'lventwicklungtable');
		},
		updatedTag(updatedTag) {
			this.updateTagInTable(updatedTag, 'lventwicklungtable');
		},
		addWeiterentwicklung()
		{
			if (this.formData.studiensemester_kurzbz === "")
				return this.$fhcAlert.alertWarning("Studiensemester auswählen!");
			if (this.formData.mitarbeiter_uid === "")
				return this.$fhcAlert.alertWarning("Mitarbeiter auswählen!");
			if (this.formData.lehrveranstaltung_id === "")
				return this.$fhcAlert.alertWarning("LV auswählen!");

			this.formData.stammdaten_studiensemester = this.studiensemester;

			return this.$api.call(ApiLVEntwicklung.update(this.formData))
				.then(result => result.data)
				.then(updateData => {
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					updateData.geloescht = true;
					this.$refs.lventwicklungtable.tabulator.addRow(updateData, true);
					let newRow = this.$refs.lventwicklungtable.tabulator.getRow(updateData.pep_lv_entwicklung_id);
					let children = newRow.getElement().childNodes;
					children.forEach((child) => {
						child.classList.add("highlight-success");
					})
					setTimeout(function(){
						children.forEach((child) => {
							child.classList.remove("highlight-success");
						})
					}, 1000);

					this.resetFormData();
					this.$refs.weiterentwicklungModal.hide();

				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
		},
		updateEntwicklung(row, field)
		{
			let neededData = row.getData();
			neededData.stammdaten_studiensemester = this.studiensemester

			this.update(neededData).then((data) => {

				if (field === 'mitarbeiter_uid')
				{
					const vertragsListe = (data.zrm_vertraege_kurzbz || '').split('\n');
					const match = vertragsListe.filter(vertrag => this.config.allow_volume_edit_contracts.includes(vertrag));

					if (match.length === 0)
					{
						data.werkvertrag_ects = null;
						data.status_kurzbz = null;
						data.volumen_eur = null;
					} else
					{
						data.stunden = null;
					}

				}
				else if (field === "werkvertrag_ects" || field === "status_kurzbz")
				{
					let data = row.getData();

					let ects = parseFloat(data.werkvertrag_ects);
					let status = data.status_kurzbz;
					let faktor = status === "new" ? 640 : (status === "kvp" ? 320 : 0);

					data.volumen_eur = isNaN(ects) ? 0 : ects * faktor;
				}
				else if (field === 'stunden')
					this.theModel = { ...this.modelValue, needReload: true }

				row.update(data)
				row.reformat();
			})

		},
		update(data)
		{
			return this.$api.call(ApiLVEntwicklung.update(data))
				.then(result => result.data)
				.then(updateData => {
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					return updateData;
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
				.finally(() => {this.updateSelectedRows()});
		},
		addRow()
		{
			if (!this.futureLVs || this.futureLVs.length === 0)
			{
				this.$api.call(ApiLVEntwicklung.getFutureLvs(this.loadedData))
					.then(response => {
						this.futureLVs = response.data
							.map(lv => ({
								id: String(lv.lehrveranstaltung_id),
								lvbezeichnung: lv.lvbezeichnung,
								studiengang: lv.stg_kuerzel,
								label: `[${lv.lehrveranstaltung_id}] ${lv.lvbezeichnung} (${lv.stg_kuerzel})`
							}));
					})
					.catch(error => {
						this.$fhcAlert.handleSystemError(error);
					});
			}
			this.$refs.weiterentwicklungModal.show();
		},
	},
	template: `
	
		<core-base-layout>
			<template #main>
			<h5 :class="theModel.config.matched ? '' : 'mismatch'" :title="theModel.config.matched ? '' : 'Die Auswahl des Studienjahres im Start-Tab weicht ab'">{{$p.t('lehre', 'studiensemester')}}: {{studiensemester.join(', ')}} </h5>
				<core-filter-cmpt
					v-if="loaded"
					ref="lventwicklungtable"
					:newBtnShow=true
					newBtnLabel="Neu"
					@click:new=addRow
					:tabulator-options="tabulatorOptions"
					@nw-new-entry="newSideMenuEntryHandler"
					:table-only=true
					:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited},
										{ event: 'tableBuilt', handler: tableBuilt }, 
										{ event: 'rowSelectionChanged', handler: updateSelectedRows }]"
					>
					<template #actions>
						<Tag ref="tagComponent"
							:endpoint="tagEndpoint"
							:values="selectedColumnValues"
							@added="addedTag"
							@deleted="deletedTag"
							@updated="updatedTag"
							zuordnung_typ="pep_lv_entwicklung_id"
						></Tag>
					</template>
				</core-filter-cmpt>
				<bs-modal ref="weiterentwicklungModal" class="bootstrap-prompt" dialogClass="modal-xl" @hidden-bs-modal="resetFormData">
					<template #title>{{ modalTitle }}</template>
					<template #default>
						
						
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="autocomplete"
									:label="$p.t('lehre', 'lektor')"
									:suggestions="filteredEmp"
									field="label"
									v-model="formData.mitarbeiter_label"
									@complete="searchEmp"
									@item-select="onEmpSelected"
								></form-input>
							</div>
							<div class="col">
								<form-input
									type="autocomplete"
									:label="$p.t('lehre', 'lehrveranstaltung')"
									:suggestions="filteredLv"
									v-model="formData.lehrveranstaltung_label"
									field="label"
									@complete="searchLv"
									@item-select="onLvSelected"
								></form-input>
							</div>
						</div>
						
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="select"
									name="rollentyp"
									:label="$p.t('ui', 'lv_entwicklung_rolle')"
									v-model="formData.rolle_kurzbz"
									>
									<option :value="null">Bitte auswählen</option>
									<option
										v-for="(bezeichnung, kurzbz) in rollenListe"
										:key="kurzbz"
										:value="kurzbz"
										>
										{{ bezeichnung }}
									</option>
								</form-input>
							</div>
							<div class="col">
								<form-input
									type="select"
									name="statustyp"
									:label="$p.t('global', 'status')"
									v-model="formData.status_kurzbz"
									>
									<option :value="null">Bitte auswählen</option>
									<option
										v-for="(bezeichnung, kurzbz) in statusListe"
										:key="kurzbz"
										:value="kurzbz"
										>
										{{ bezeichnung }}
									</option>
								</form-input>
							</div>
						</div>
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="number"
									name="stunden"
									:label="$p.t('ui', 'stunden')"
									v-model="formData.stunden"
									>
								</form-input>
							</div>
							<div class="col">
								<form-input
									type="number"
									name="ects"
									:label="$p.t('ui', 'werksvertragsects')"
									v-model="formData.werkvertrag_ects"
									>
									
								</form-input>
							</div>
						</div>
						<div class="row row-cols-2">
							<div class="col">
								<form-input
									type="textarea"
									:label="$p.t('global', 'anmerkung')"
									v-model="formData.anmerkung"
									>
								</form-input>
							</div>
							<div class="col">
								<form-input
									type="select"
									name="studiensemester"
									:label="$p.t('lehre', 'studiensemester')"
									v-model="formData.studiensemester_kurzbz"
									>
									<option
										v-for="(key) in studiensemester"
										:key="key"
										:value="key"
										>
										{{ key }}
									</option>
								</form-input>
							</div>
						</div>
					</template>
					<template #footer>
						<button type="button" class="btn btn-primary" @click="addWeiterentwicklung">{{ $p.t('ui', 'speichern') }}</button>
					</template>
				</bs-modal>
			</template>
		</core-base-layout>
		<fhc-loader ref="loader" :timeout="0"></fhc-loader>
	`
};
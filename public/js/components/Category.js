import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import {formatter} from "../mixins/formatters";

export default {
	props: {
		config: null,
		modelValue: {
			type: Object,
			default: () => ({})
		},
	},
	components: {
		CoreFilterCmpt,
	},
	watch: {

	},
	computed: {
		tabulatorOptions() {
			return {
				maxHeight: "100%",
				layout: 'fitDataStretch',
				selectable: false,
				placeholder: "Keine Daten verfügbar",
				columns: [
					{title: 'UID', field: 'mitarbeiter_uid', headerFilter: true, visible:false},
					{title: 'Vorname', field: 'vorname', headerFilter: true},
					{title: 'Nachname', field: 'nachname', headerFilter: true},
					{title: 'Zrm - DV', field: 'vertraege', headerFilter: "input", formatter: "textarea", tooltip: ""},
					{title: 'Zrm - Stunden/Woche', field: 'wochenstundenstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Zrm - Stunden/Jahr', field: 'jahresstunden', hozAlign:"right", headerFilter: "input", formatter: "textarea"},
					{title: 'Stunden', field: 'stunden', headerFilter: true, editor: "input", bottomCalcParams: {precision:2}, bottomCalc: "sum", hozAlign: "right",
						formatter: function (cell, formatterParams, onRendered) {
							var value = cell.getValue();
							if (value !== "" && !isNaN(value))
							{
								value = parseFloat(value).toFixed(2);
								return value;
							}
							else
								return '-'
						},
					},
					{title: 'Anmerkung', field: 'anmerkung', headerFilter: "input", visible: true, editor: "textarea",
						formatter: "textarea"
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

	data: function() {
		return {
			studienjahr: null
		}
	},
	mounted()
	{
		//this.updateValue("testCate");
	},
	methods: {
		async loadData(data)
		{
			data.category_id = this.config.category_id;
			await Vue.$fhcapi.Category.get(data).then(response => {
				if (CoreRESTClient.isSuccess(response.data))
				{

					if (CoreRESTClient.hasData(response.data))
					{
						let result = CoreRESTClient.getData(response.data);
						this.$refs.categoryTable.tabulator.setData(result);
						this.studienjahr = 	data.studienjahr;
					}
					else
						this.$fhcAlert.alertWarning("Keine Daten vorhanden");
				}
			});
		},
		onCellEdited(cell)
		{
			let data = cell.getRow().getData();
			let uid = data.mitarbeiter_uid;
			let newValue = { ...this.theModel };

			// Prüfen, ob der UID Eintrag existiert, wenn nicht, erstellen
			if (!newValue[uid]) {
				newValue[uid] = [];
			}

			// Hinzufügen des neuen Eintrags
			newValue[uid].push({
				kategorie: this.config.category_id,
				studienjahr: this.studienjahr,
				stunden: data.stunden,
				anmerkung: data.anmerkung,
			});

			// Aktualisieren des theModel, was den Setter der computed Eigenschaft aufruft
			this.theModel = newValue;
			/*
			let uid = cell.getRow().getData().mitarbeiter_uid;

			if (!this.theModel[uid])
			{
				this.theModel[uid] = [];
			}

			this.theModel[uid].push({
				kategorie: this.config.category_id,
				studienjahr: '2011/12',
				stunden: cell.getRow().getData().stunden,
				anmerkung: cell.getRow().getData().anmerkung,
			});*/

			/*this.modelValue[uid].push({
				kategorie: this.config.category_id,

			});*/

			// Auslösen des update:modelValue Events, um die Änderung nach oben zu senden
			/*this.$emit('update:modelValue', this.modelValue);
*/
			/*if (mitarbeiter !== -1) {
				// Mitarbeiter existiert bereits, fügen Sie die Kategorie hinzu
				mitarbeiter[mitarbeiterIndex].kategorien.push({ kategorie, stunden });
			} else {
				// Mitarbeiter existiert nicht, fügen Sie ihn hinzu
				mitarbeiterListe.push({
					name: mitarbeiterName,
					kategorien: [
						{ kategorie, stunden }
					]
				});
			}


			console.log(this.config);
			if (!isset(this.modelValue))
				this.modelValue.uids = [
					cell.getRow().getData().mitarbeiter_uid
				]
			console.log(cell.getRow().getData());*/
			/*let mitarbeiter = this.modelValue.uid.findIndex(m => m.uid === cell.getRow().getData().mitarbeiter_uid);

			console.log(mitarbeiter);*/

			/*let anrechnungId = cell.getRow().getIndex();
			let status = cell.getValue();
			this.$fhcAlert.alertSuccess(this.$p.t('global', 'aenderungGespeichert'));*/
		},
		/*updateValue(newValue) {
			this.$emit('update:modelValue', newValue);
		},*/
		setTableData(data) {
			this.$refs.categoryTable.tabulator.setData(data);
		}
	},

	template: `
		<core-filter-cmpt
			ref="categoryTable"
			:tabulator-options="tabulatorOptions"
			:tabulator-events="[{ event: 'cellEdited', handler: onCellEdited }]"
			:table-only=true
			:side-menu="false"
			:hideTopMenu=false
			
		></core-filter-cmpt>
	`
};
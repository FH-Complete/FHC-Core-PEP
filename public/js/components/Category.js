import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
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
					{title: 'Akt - Kostenstelle', field: 'aktorgbezeichnung', headerFilter: "input", formatter: "textarea"},
					{title: 'Akt - Kostenstelle - Parent', field: 'aktparentbezeichnung', headerFilter: "input", formatter: "textarea"},
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

			if (!newValue[uid]) {
				newValue[uid] = [];
			}

			newValue[uid].push({
				kategorie: this.config.category_id,
				studienjahr: this.studienjahr,
				stunden: data.stunden,
				anmerkung: data.anmerkung,
			});

			this.theModel = newValue;

		},
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
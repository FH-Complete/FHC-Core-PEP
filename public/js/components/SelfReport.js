import FhcLoader from '../../../../js/components/Loader.js';
import FormInput from "../../../../js/components/Form/Input.js";
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';
import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import Tag from '../../../../js/components/Tag/Tag.js';
import ApiSelf from "../api/self.js";
import ApiSelfOverviewTag from "../api/selfTabTags.js";
import {formatter} from "../mixins/formatters.js";
export default {
	name: "SelfReport",
	props: {
		studienjahre: {
			type: Array,
			required: true
		},
		mitarbeiter_auswahl: {
			type: Boolean,
			required: true
		},
		mitarbeiter_auswahl_reload: {
			type: Boolean,
			required: true
		},


	},
	data: function() {
		return {
			headerMenuEntries: {},
			sideMenuEntries: {},
			selected_studienjahr: null,
			selected_lektor: null,
			selected_lektor_anzeige: null,
			filteredLektor: [],
			lektor_input: null,
			tagEndpoint: ApiSelfOverviewTag,
			showInfo: false,

		};
	},
	async created() {
		await this.$p.loadCategory(['ui']);

		if (this.mitarbeiter_auswahl === true)
			this.getLektoren()
	},

	computed: {
		tabulatorOptions()
		{
			return {
				layout: 'fitDataStretch',
				height: '60vh',
				selectableRows:true,
				placeholder: "Keine Daten verfügbar",
				persistenceID: "2025_08_13_pep_self_v2",
				persistence: true,
				columns: [
					{title: 'Typ', field: 'typ'},
					{title: 'Beschreibung', field: 'beschreibung'},
					{title: 'Anmerkung', field: 'anmerkung'},
					{title: 'Hinweis', field: 'info', formatter: "textarea"},
					{
						title: 'Tags',
						field: 'tags',
						tooltip: false,
						formatter: (cell) => formatter.tagFormatter(cell, this.$refs.tagComponent),
						width: 150,
					},
					{title: 'Stunden', field: 'stunden', bottomCalc: "sum",
						formatter: function (cell, formatterParams, onRendered)
						{
							let value = cell.getValue();
							if (value === null || isNaN(value) || value === "")
								return "-";

							if (!isNaN(value))
								return parseFloat(value).toFixed(2);
						},
						bottomCalcParams: {precision: 2},
					},
					{title: 'geplanter Zeitraum', field: 'zeit'},
					{title: 'Studiengang', field: 'stg'},
					{title: 'Lehrform', field: 'lehrform'},
					{title: 'Gruppe', field: 'gruppe'},

				],
			}
		},
	},
	watch: {
		selected_studienjahr: {
			handler(newValue, oldValue) {
				if (newValue !== oldValue)
				{
					if (this.mitarbeiter_auswahl && this.mitarbeiter_auswahl_reload)
						this.getLektoren();
					if (!newValue)
						return this.$refs.selfTable.tabulator.setData([]);
					this.loadData();
				}

			}
		},
		selected_lektor: {
			handler(newValue, oldValue) {
				if (newValue !== oldValue)
				{
					this.loadData();
				}
			}
		},

	},
	components: {
		FhcLoader,
		FormInput,
		CoreBaseLayout,
		CoreFilterCmpt,
		Tag
	},
	methods: {
		async loadData()
		{
			let studienjahr = this.selected_studienjahr;
			let uid = null;

			if (!studienjahr)
				return;
			if (this.mitarbeiter_auswahl)
				uid = this.selected_lektor
			this.$refs.loader.show()

			let data = {
				studienjahr,
				uid
			}
			await this.$api.call(ApiSelf.getSelf(data))
				.then(response => {
					if (response.data.length === 0)
					{
						this.$fhcAlert.alertInfo("Keine Daten vorhanden");
						this.$refs.selfTable.tabulator.setData([]);
					}
					else
					{
						this.$refs.selfTable.tabulator.setData(response.data);
					}
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				})
				.finally(() => {
					this.$refs.loader.hide();
				});
		},
		getLektoren() {
			let studienjahr = this.selected_studienjahr
			this.$api.call(ApiSelf.getLektoren({studienjahr}))
				.then(result => result.data)
				.then(result => {
					this.lektoren = result
					if (this.selected_lektor && !this.lektoren.some(lektor => lektor.uid === this.selected_lektor))
						this.cleanLektor()
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
		changedLektor(event)
		{
			this.selected_lektor = event.value.uid;
			this.selected_lektor_anzeige = event.value.label;
		},
		cleanLektor()
		{
			this.selected_lektor = null;
			this.selected_lektor_anzeige = null;
			this.lektor_input = null;
		},
	},
	template: `


	<div id="wrapper">
		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-12">
						<h4 class="page-header">
						Ausblick auf Ihre mögliche LV-Planung
						<i class="fa fa-info-circle ml-2" @click="showInfo = !showInfo"></i>
						</h4>
						<div v-if="showInfo" class="alert alert-info mt-2">
							<b>Achtung:</b> die vorliegenden Informationen stellen eine Vorabplanung dar und sind als Anfrage an Sie gedacht. <br /> <br />
							Die Beauftragung der tatsächlichen Lehrveranstaltungen erfolgt durch Ihre Kompetenzfeldleitung. <br /> <br />
							Ihre aktuell gültigen Lehraufträge und den LV Plan des aktuellen Semesters (Termine) finden Sie wie gewohnt unter „mein CIS“ -> „LV-Plan Hauptmenü“ bzw. „Lehrauftragsverwaltung“.
						</div>
					</div>
				</div>
				<hr />
			
				<div class="row">
					<div class="col-md-2">
						<form-input
							type="select"
							name="studienjahr"
							:label="$p.t('lehre', 'studienjahr')"
							v-model="selected_studienjahr"
						>
							<option :value="null">Bitte auswählen</option>
							<option
								v-for="studienjahr in studienjahre"
								:key="studienjahr.studienjahr_kurzbz"
								:value="studienjahr.studienjahr_kurzbz"
							>
								{{ studienjahr.studienjahr_kurzbz }}
							</option>
						</form-input>
					</div>
					
					
					<div class="col-md-3" v-if="mitarbeiter_auswahl">
						<form-input
							type="autocomplete"
							v-if="mitarbeiter_auswahl"
							:label="$p.t('lehre', 'lektor')"
							:suggestions="filteredLektor"
							placeholder="Mitarbeiter auswählen"
							field="label"
							v-model="lektor_input"
							@complete="searchLektor"
							@item-select="changedLektor"
							@clear="cleanLektor"
						></form-input>
					</div>
					<div class="col-md-2 justify-content-start d-flex">
						<button class="btn btn-outline-secondary mt-auto" aria-label="Reload" @click="loadData">
							<span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<hr />
				
				<core-base-layout>
					<template #main>
					
						<span style="color: red" v-if="mitarbeiter_auswahl && selected_lektor"> {{ selected_lektor_anzeige }}</span>
						
						<core-filter-cmpt
							ref="selfTable"
							:tableOnly=false
							:tabulator-options="tabulatorOptions"
							:table-only=true
							:side-menu="false"
						>
							<template #actions>
								<Tag ref="tagComponent"
									:readonly="true"
									:endpoint="tagEndpoint"
								></Tag>
							</template>
						</core-filter-cmpt>
							
					</template>
					
				</core-base-layout>

			</div>
		</div>
	</div>
	<fhc-loader ref="loader" :timeout="0"></fhc-loader>
	
`
};



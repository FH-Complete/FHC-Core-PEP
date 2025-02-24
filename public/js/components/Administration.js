import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';
import CoreBaseLayout from '../../../../js/components/layout/BaseLayout.js';

export default {
	components: {
		CoreFilterCmpt,
		CoreBaseLayout
	},
	data: function() {
		return{
			studienjahre: [],
			organisationen: [],
			fromSelectedStudienjahr: "",
			toSelectedStudienjahr: "",
			selectedOrganisation: "",
			selectedRecursive: false
		}
	},
	mounted() {
		this.$fhcApi.factory.pep.getCategories()
			.then(response => {
				this.$refs.administrationTable.tabulator.setData(response.data.categories)
			})
			.catch(error => {
				this.$fhcAlert.handleSystemError(error);
			});

		this.$fhcApi.factory.pep.getStudienjahre()
			.then(response => {
				this.studienjahre = response.data
			})
			.catch(error => {
				this.$fhcAlert.handleSystemError(error);
			})

		this.$fhcApi.factory.pep.getOrganisationen()
			.then(response => {
				this.organisationen = response.data
			})
			.catch(error => {
				this.$fhcAlert.handleSystemError(error);
			})

	},
	computed: {
		tabulatorOptions() {
			return {
				index: "kategorie_id",
				maxHeight: "100%",
				layout: 'fitDataStretch',
				placeholder: "Keine Daten verfügbar",
				persistenceID: "2024_12_11_pep_kategorie_administration",
				columns: [
					{
						formatter: 'rowSelection',
						titleFormatter: 'rowSelection',
						headerSort: false,
						width: 40
					},
					{title: 'ID', field: 'kategorie_id'},
					{title: 'Kategorie', field: 'beschreibung'},
				],
			}
		},
	},

	methods: {
		vorruecken() {

			let rows = this.$refs.administrationTable.tabulator.getSelectedRows();

			let data = {
				'fromStudienjahr' : this.fromSelectedStudienjahr,
				'toStudienjahr' : this.toSelectedStudienjahr,
				'organisation' : this.selectedOrganisation,
				'recursive': this.selectedRecursive,
				'kategorien' : []
			}
			rows.forEach(row => {
				data.kategorien.push(row.getData().kategorie_id);
			})

			this.saveData(data);
		},

		async saveData(data)
		{
			await this.$fhcApi.factory.pep.stundenvoerruecken(data)
				.then(response => response.data)
				.then(response => {
					if (response.length === 0)
						this.$fhcAlert.alertSuccess("Erfolgreich für alle ausgewählten Kategorien übernommen!");
					else
					{
						let ids = response.join(', ')
						this.$fhcAlert.alertWarning(`Für die Kategorien ${ids} nicht übernommen`)
					}
				})
				.catch(error => {
					this.$fhcAlert.handleSystemError(error);
				});
		},
	},
	template: `
		<core-base-layout>
			<template #main>
				<div class="row align-items-center pb-2">
					<div class="col-md-3">
						<select v-model="selectedOrganisation" class="form-select">
							<option value="">Abteilung</option>
							<option v-for="organisation in organisationen" :value="organisation.oe_kurzbz">
								[{{ organisation.organisationseinheittyp_kurzbz }}] {{ organisation.bezeichnung }}
							</option>
						</select>
					</div>
					<div class="col-auto">
						<div class="form-check">
							<input
								class="form-check-input"
								type="checkbox"
								id="recursive"
								v-model="selectedRecursive"
							>
							<label class="form-check-label" for="recursive">
								Rekursiv
							</label>
						</div>
					</div>
					<div class="col-md-1">
						<select v-model="fromSelectedStudienjahr" class="form-select">
							<option value="">Studienjahr</option>
							<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz">
								{{ studienjahr.studienjahr_kurzbz }}
							</option>
						</select>
					</div>
				
					
					<div class="col-auto">
						<i class="fa-solid fa-arrow-right"></i>
					</div>
					<div class="col-md-1">
						<select v-model="toSelectedStudienjahr" class="form-select">
							<option value="">Studienjahr</option>
							<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz">
								{{ studienjahr.studienjahr_kurzbz }}
							</option>
						</select>
					</div>
				</div>
				<core-filter-cmpt
					ref="administrationTable"
					:tabulator-options="tabulatorOptions"
					:table-only=true
					:side-menu="false">
					<template #actions>
						<div class="d-flex gap-2 align-items-baseline">
							<button class="btn btn-primary" @click="vorruecken">Vorrücken</button>
						</div>
					</template>
				</core-filter-cmpt>
			</template>
			
		</core-base-layout>
		
	`
};
/**
 * Copyright (C) 2023 fhcomplete.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import FhcTabs from '../../../../js/components/Tabs.js';
import {CoreNavigationCmpt} from '../../../../js/components/navigation/Navigation.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';

export default {
	name: "PepReport",
	props: {
		studiensemestern: {
			type: Array,
			required: true
		},
		studienjahre: {
			type: Array,
			required: true
		},
		organisationen: {
			type: Array,
			required: true
		},
		config:null,
	},
	data: function() {
		return {
			appSideMenuEntries: {},
			currentTab: null,
			showInfo: false,
			studiensemester: [],
			selectedTab: '',
			selectedOrg: "",
			modelValue: [],
			selectedStsem: [],
			selectedStjahr: "",
			tabsConfig: null,
			showStudiensemester: false,
			showStudienjahr: false,
			isRecursive: false
		};
	},
	created() {
		this.loadTabConfig();
		window.addEventListener('beforeunload', (event) => {
			if (this.checkBeforeLeave())
			{
				event.preventDefault();
			}
		})
	},
	beforeDestroy() {
		window.removeEventListener('beforeunload', this.checkBeforeLeave)
	},
	components: {
		CoreNavigationCmpt,
		CoreFilterCmpt,
		FhcTabs,
		Multiselect: primevue.multiselect
	},
	watch: {
		currentTab(newTabKey)
		{
			this.updateDropdowns(newTabKey);
		}
	},
	methods: {
		updateDropdowns(tabKey) {
			if (this.tabsConfig[tabKey]) {
				this.showStudienjahr = this.tabsConfig[tabKey].config.studienjahr ? this.tabsConfig[tabKey].config.studienjahr : false;
				this.showStudiensemester = this.tabsConfig[tabKey].config.studiensemester ? this.tabsConfig[tabKey].config.studiensemester : false;
			}
		},
		async loadTabConfig() {
			try {
				const response = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/TabsConfig/get');
				if (CoreRESTClient.isSuccess(response.data)) {
					this.tabsConfig = CoreRESTClient.getData(response.data);
					this.updateTab('start');
				}
			} catch (error) {
				this.errors = "Fehler beim Laden des Reports";
			}
		},
		newSideMenuEntryHandler: function (payload) {
			this.appSideMenuEntries = payload;
		},
		ssChanged: function (e) {
			this.selectedStsem = e.value.map(item => item.studiensemester_kurzbz);
		},

		saveButtonClick: function () {
			this.$refs.navtabs.saveTabData();
		},
		handleLoad: function (e) {
			if (this.checkBeforeLeave()) {
				if (!confirm('Es gibt ungespeicherte Änderungen. Möchten Sie diese Seite wirklich verlassen?'))
				{
					return;
				}
			}

			if (this.selectedOrg !== '' && (this.selectedStsem !== "" || this.selectedStjahr)) {
				let data = {
					'org': this.selectedOrg,
					'recursive': this.isRecursive
				}

				if (this.selectedStsem !== "" && this.tabsConfig[this.currentTab].config.studiensemester) {
					data.studiensemester = this.selectedStsem;
				} else if (this.selectedStjahr !== "" && this.tabsConfig[this.currentTab].config.studienjahr)
					data.studienjahr = this.selectedStjahr;

				this.resetModelValue();
				this.$refs.currentTab.$refs.current.loadData(data);
			}

		},
		speichern: function () {
			if (!Object.keys(this.modelValue).length)
				return;

			Vue.$fhcapi.Category.saveMitarbeiter(this.modelValue).then(response => {
				if (CoreRESTClient.isSuccess(response.data)) {
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					this.resetModelValue();
				}
			});
		},
		resetModelValue() {
			this.modelValue = {};
		},
		updateTab(newTab) {
			this.currentTab = newTab;
		},
		checkBeforeLeave() {
			if (Object.keys(this.modelValue).length > 0)
				return true;
			else
				return false;
		},
	},
	template: `

	<core-navigation-cmpt 
		v-bind:add-side-menu-entries="sideMenuEntries"
		v-bind:add-header-menu-entries="headerMenuEntries"
		leftNavCssClasses="''">	
	</core-navigation-cmpt>

	<div id="wrapper">
		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-12">
						<h4 class="page-header">
						Personaleinsatzplanung
						<i class="fa fa-info-circle text-right fa-xs" data-bs-toggle="collapse" href="#faq0"></i>
						</h4>
					</div>
				</div>
				<div class="row collapse" id="faq0">
					<div class="col-6">
						<div class="alert alert-info">
							- <b>Zrm (Zeitraum)</b>: Informationen für die festgelegten Studiensememster.
							<br />
							- <b>Akt (Aktuell)</b>: Informationen zum aktuellen Zeitpunkt.
							<br />
							- Felder mit dem <i class='fa fa-edit fa-sm'></i> - Tooltip (Weiterbildung, Admin...), können editiert und gespeichert werden. Nur bei echten DV´s. 
						</div>
					</div>
				</div>
				<hr />
				<div class="row">
					<div class="col-md-9" id="container">
						<div class="row">
							<div class="col-md-2" v-if="showStudiensemester">
								<Multiselect
									v-model="studiensemester"
									option-label="studiensemester_kurzbz" 
									:options="studiensemestern"
									placeholder="Studiensemester"
									:hide-selected="true"
									:selectionLimit="2"
									@change="ssChanged" 
								>
								</Multiselect>
							</div>
							
							
							<div class="col-md-2" v-if="showStudienjahr">
								<select v-model="selectedStjahr" class="form-control">
									<option value="">Studienjahr</option>
									<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz" >
										{{ studienjahr.studienjahr_kurzbz }}
									</option>
								</select>
							</div>
							<div class="col-md-3">
								<select v-model="selectedOrg" class="form-control">
									<option value="">Abteilung</option>
									<option v-for="organisation in organisationen" :value="organisation.oe_kurzbz" >
										[{{ organisation.organisationseinheittyp_kurzbz }}] {{ organisation.bezeichnung }}
									</option>
								</select>
							</div>
							<div class="col-md-1">
								<div class="form-check">
										<input
											class="form-check-input"
											type="checkbox"
											id="recursive"
											v-model="isRecursive"
										>
									<label class="form-check-label" for="recursive">
										Rekursiv
									</label>
								</div>
							</div>
							<div class="col-md-2">
								<button @click="handleLoad" class="form-control btn-default">
									Laden
								</button>
							</div>
							<div class="col-md-2">
								<button @click="speichern" class="form-control btn-default">Speichern</button>
								</div>
								<br/>
								<br/>
								<hr />
								</div>
							</div>
					<div class="col-md-3">
						<div class="accordion" id="accordionExample">
							<div class="accordion-item">
								<h2 class="accordion-header" id="headingOne">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
										<i class="fa fa-circle-exclamation"></i>&ensp; Info Start
									</button>
								</h2>
								<div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
									<div class="accordion-body">
										Die Übersicht zeigt die Mitarbeiter, die während des ausgewählten Semestern der Organisation oder einer untergeordneten Organisation zugeordnet (Kostenstellen und organisatorische Zuordnung) waren.
									</div>
								</div>
							</div>
							<div class="accordion-item">
								<h2 class="accordion-header" id="headingTwo">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
										<i class="fa fa-circle-exclamation"></i>&ensp; Info Lehre
									</button>
								</h2>
								<div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
									<div class="accordion-body">
										Die Übersicht enthält alle Lehrveranstaltungen, die von der Organisation für die gewählten Semestern zugeordnet waren.
										Ebenso enthält die Liste alle Lehrveranstaltungen der Mitarbeiter, die in dem ausgewählten Semestern der die Organisation zugeordnet (Kostenstellen und organisatorische Zuordnung) waren.
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<fhc-tabs v-if="tabsConfig !== ''"
					ref="currentTab"
					:config="tabsConfig"
					style="flex: 1 1 0%; height: 0%"
					:vertical="false"
					border="true"
					@changed="updateTab"
					v-model="modelValue"
				>
				</fhc-tabs>
			</div>
		</div>
	</div>
	
`
};



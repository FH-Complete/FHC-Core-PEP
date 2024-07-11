import {CoreFilterCmpt} from '../../../../js/components/filter/Filter.js';
import FhcTabs from '../../../../js/components/Tabs.js';
import {CoreNavigationCmpt} from '../../../../js/components/navigation/Navigation.js';
import FhcLoader from '../../../../js/components/Loader.js';
import FormInput from "../../../../js/components/Form/Input.js";
import BaseLayout from "../../../../js/components/layout/BaseLayout.js";


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
		config: {
			type: Array
		}
	},
	data: function() {
		return {
			currentTab: null,
			tabInstances: [],
			studiensemester: [],
			selectedOrg: "",
			modelValue: {
				config: {},
				updatedData: {},
			},
			selectedStsem: [],
			selectedStudienjahr: "",
			loadedStjahr: "",
			loadedStsem: [],
			loadedOrg: "",
			tabsConfig: null,
			isRecursive: false,
		};
	},

	created() {
		this.loadTabConfig();
		window.addEventListener('beforeunload', (event) => {
			if (this.checkBeforeLeave())
			{
				event.preventDefault();
			}
		});
	},
	beforeDestroy() {
		window.removeEventListener('beforeunload', this.checkBeforeLeave)
	},
	components: {
		CoreNavigationCmpt,
		CoreFilterCmpt,
		FhcTabs,
		Multiselect: primevue.multiselect,
		FhcLoader,
		FormInput,
		BaseLayout
	},
	watch: {
		selectedOrg: function(newOrg) {
			this.handleLoad()
		},
		selectedStudienjahr: function(newOrg) {
			this.handleLoad(true)
		},
		modelValue: {
			handler(newValue) {
				if (newValue.loadDataReady)
				{
					this.updateTab(this.currentTab, false)
				}
				let changedKeys = Object.keys(newValue.updatedData).map(Number);
				this.customTitle(changedKeys);
			},
			deep: true
		}

	},
	methods: {
		customTitle(tabs)
		{
			Object.values(this.tabsConfig).forEach(tab => {
				let category_id = tab.config?.category_id

				let title = tab.title
				if (tabs.includes(category_id))
				{
					if (title.includes('*'))
						return;
					tab.title += '*';
				}
				else if (title.includes('*'))
				{
					tab.title = title.replace('*', '')
				}

			});
		},

		async loopAllTabs(onlyCategories = false)
		{
			this.modelValue.config = {
				'studienjahr' : this.selectedStudienjahr,
				'semester': this.selectedStsem,
				'org': this.selectedOrg,
				'recursive': this.isRecursive
			};

			this.loadedStjahr =  this.selectedStudienjahr;
			this.loadedStsem =  this.selectedStsem;
			this.loadedOrg =  this.selectedOrg;
			this.loadedRecursive = this.isRecursive

			let data = {
				'org': this.selectedOrg,
				'recursive': this.isRecursive
			}

			for (const tabInstance of Object.values(this.tabInstances))
			{
				let tabInstanceConfig = tabInstance.config;
				if (tabInstance && tabInstance.loadData)
				{
					if (onlyCategories && !tabInstanceConfig?.studienjahr)
						continue;

					if (tabInstanceConfig?.studiensemester && this.loadedStsem.length === 0)
						continue;
					else
						data.semester = this.loadedStsem;

					if (tabInstanceConfig?.studienjahr && this.loadedStjahr === "")
						continue;
					else
						data.studienjahr = this.loadedStjahr;
					await tabInstance.loadData(data);
				}
			}
		},
		checkStudiensemester ()
		{
			if (this.loadedStsem !== this.selectedStsem)
				this.handleLoad()
		},
		handleLoad(onlyCategories)
		{
			if (
				(this.selectedStudienjahr === "" && this.selectedStsem.length === 0) || this.selectedOrg === "")
				return;
			if (this.checkBeforeLeave()) {
				if (!confirm('Es gibt ungespeicherte Änderungen. Möchten Sie diese Seite wirklich verlassen?')) {
					return;
				}
			}
			this.$refs.loader.show();
			this.resetValues();
			this.loopAllTabs(onlyCategories).then(() => this.$refs.loader.hide());
		},
		async loadTabConfig() {
			await this.$fhcApi.factory.pep.getConfig()
				.then(response => {this.tabsConfig = response.data})
				.then(() => this.updateTab("start", true));
		},
		semesterChanged: function (e)
		{
			this.selectedStsem = e.value.map(item => item.studiensemester_kurzbz);
		},
		async speichern () {
			await this.$fhcApi.factory.pep.saveMitarbeiter(this.modelValue.updatedData)
				.then(async () => {
					this.$fhcAlert.alertSuccess("Erfolgreich gespeichert");
					this.resetValues().then(() => this.handleLoad(false));
			});
		},
		async resetValues() {
			this.modelValue.updatedData = {};
		},
		handleLoadOneTab: function (e)
		{
			const currentTabConfig = this.tabsConfig[this.currentTab].config;
			if ((currentTabConfig?.studiensemester && this.selectedStsem.length === 0) ||
				(currentTabConfig?.studienjahr && this.selectedStudienjahr === "") ||
				this.selectedOrg === ""
			)
				return;

			this.modelValue.config = {
				'studienjahr' : this.selectedStudienjahr,
				'semester': this.selectedStsem,
				'org': this.selectedOrg,
				'recursive': this.isRecursive
			};

			this.loadedStjahr =  this.selectedStudienjahr;
			this.loadedStsem =  this.selectedStsem;
			this.loadedOrg =  this.selectedOrg;
			this.loadedRecursive = this.isRecursive

			this.$refs.loader.show();
			this.$refs.tabComponent.$refs.current.loadData(this.modelValue.config).then(() => this.$refs.loader.hide());
		},

		updateTab(newTab, firstRun = false)
		{
			this.currentTab = newTab;

			if (firstRun === false)
			{
				const tabellenNamen = ["categoryTable", "startTable", "lehreTable"];

				function getTabulatorInstance(refs) {
					for (const name of tabellenNamen) {
						if (refs?.[name]?.tabulator) {
							return refs[name].tabulator;
						}
					}
					return null;
				}

				const tabulatorInstance = getTabulatorInstance(this.$refs.tabComponent?.$refs?.current?.$refs);

				if (tabulatorInstance)
				{
					try {
						tabulatorInstance.setSort( [
							{column: "vorname", dir:"asc"}
						])
					}catch (e) {

					}
				}
				this.addTabForReload()
			}
		},
		addTabForReload()
		{
			let tabInstance = this.$refs.tabComponent.$refs.current
			if (tabInstance && !this.tabInstances.includes(tabInstance))
			{
				this.tabInstances.push(tabInstance);
				this.handleLoadOneTab();
			}
		},
		checkBeforeLeave() {
			if (Object.keys(this.modelValue.updatedData).length > 0)
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

<core-base-layout
title="Personaleinsatzplanung"
>
<template #main>

</template>
</core-base-layout>
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
					<div class="col-md-12" id="container">
						<div class="row">
							<div class="col-md-2" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.studiensemester">
								<Multiselect
									v-model="studiensemester"
									option-label="studiensemester_kurzbz" 
									:options="studiensemestern"
									placeholder="Studiensemester"
									:hide-selected="true"
									:selectionLimit="2"
									@change="semesterChanged" 
									@hide="checkStudiensemester"
									class="w-full md:w-80"
								>
								</Multiselect>
								
							</div>
							<div class="col-md-2" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.studienjahr">
								<select v-model="selectedStudienjahr" class="form-select">
									<option value="">Studienjahr</option>
									<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz" >
										{{ studienjahr.studienjahr_kurzbz }}
									</option>
								</select>
							</div>
							<div class="col-md-3">
								<select v-model="selectedOrg" class="form-select">
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
							<div class="col-md-1">
								<button class="btn btn-outline-secondary" aria-label="Reload" @click="handleLoad(false)">
									<span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
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
				<!--	<div class="col-md-3">
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
					</div>-->
				</div>

				<fhc-tabs v-if="tabsConfig"
					ref="tabComponent"
					:config="tabsConfig"
					default="start"
					:vertical="false"
					border=true
					@changed="updateTab"
					v-model="modelValue"
				>
				</fhc-tabs>
			</div>
		</div>
		<fhc-loader ref="loader" :timeout="0"></fhc-loader>
	</div>
	
`
};



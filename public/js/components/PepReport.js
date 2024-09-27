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
		var_studienjahr: {
			type: String,
			required: true
		},
		var_studiensemester: {
			type: Array,
			required: true
		},
		var_organisation: {
			type: String,
			required: true,
		},
		config: {
			type: Array
		}
	},
	data: function() {
		return {
			tabsConfig: null,
			currentTab: null,
			tabInstances: [],
			studiensemester: [],
			studiensemester_readonly: [],
			selectedOrg: "",
			selectedOrg_readonly: "",
			modelValue: {
				config: {},
				updatedData: {},
				needReload: false,
			},
			selectedStsem: [],
			selectedStudienjahr: "",
			loadedData: {
				studienjahr: "",
				semester: [],
				org: "",
				recursive: false,
				variables: false
			},
			isRecursive: false
		};
	},
	async created() {
		await this.$p.loadCategory(['ui']);

		await this.loadTabConfig()
			.then(() => this.checkVars());
	},
	computed: {

		currentStudiensemester: {
			get()
			{
				return this.tabsConfig[this.currentTab]?.config?.reload ? this.studiensemester : this.studiensemester_readonly;
			},
			set(value)
			{
				if (this.tabsConfig[this.currentTab]?.config?.reload)
					this.studiensemester = value;
				else
					this.studiensemester_readonly = value
			}
		},

		currentOrg: {
			get()
			{
				return this.tabsConfig[this.currentTab]?.config?.reload
					? this.selectedOrg
					: this.selectedOrg_readonly;
			},
			set(value)
			{
				if (this.tabsConfig[this.currentTab]?.config?.reload) {
					this.selectedOrg = value;
				} else {
					this.selectedOrg_readonly = value;
				}
			}
		},

	},
	components: {
		FhcTabs,
		CoreNavigationCmpt,
		Multiselect: primevue.multiselect,
		FhcLoader,
		FormInput,
		BaseLayout
	},
	watch: {
		modelValue: {
			handler(newValue) {
				if (newValue.loadDataReady && this.tabsConfig[this.currentTab]?.config?.reload)
				{
					this.modelValue.loadDataReady = false;
					this.collectTab()
						.then(() => this.loadOneTab())
				}
			},
			deep: true
		},

	},
	methods: {
		async checkVars()
		{
			this.currentStudiensemester = this.currentStudiensemester.length === 0 && this.tabsConfig[this.currentTab]?.config?.reload === true
				? this.studiensemestern.filter(semester => this.var_studiensemester.includes(semester.studiensemester_kurzbz))
				: this.studiensemestern.filter(semester => this.currentStudiensemester.map(item => item.studiensemester_kurzbz).includes(semester.studiensemester_kurzbz));

			this.selectedStsem = this.currentStudiensemester.map(item => item.studiensemester_kurzbz);


			this.currentOrg = (this.tabsConfig[this.currentTab]?.config?.reload === true)
				? ((this.currentOrg && this.currentOrg.length > 0) ? this.currentOrg : (this.var_organisation || ""))
				: (this.currentOrg || "");

			this.selectedStudienjahr = this.selectedStudienjahr || this.var_studienjahr || "";

			this.loadedData = {
				studienjahr: this.selectedStudienjahr,
				semester: this.selectedStsem,
				org: this.currentOrg,
				recursive: this.isRecursive,
			};

			this.modelValue.config = {
				studienjahr: this.selectedStudienjahr,
				semester: this.selectedStsem,
				org: this.currentOrg,
				recursive: this.isRecursive,
			}
		},
		async loadTabConfig() {
			await this.$fhcApi.factory.pep.getConfig()
				.then(response => {this.tabsConfig = response.data})
				.then(() => this.updateTab("start"))
		},
		async collectTab()
		{
			let tabInstance = this.$refs.tabComponent.$refs.current
			if (tabInstance && !this.tabInstances.includes(tabInstance) &&
				this.tabsConfig[this.currentTab]?.config?.reload
			)
			{
				this.tabInstances.push(tabInstance);
			}
		},
		async loadOneTab()
		{
			await this.checkVars();
			const currentTabConfig = this.tabsConfig[this.currentTab].config;

			if (this.checkForLoad(currentTabConfig) === false)
				return;

			let request = {...this.loadedData};

			if (currentTabConfig.studienjahr === undefined)
				delete(request.studienjahr)
			if (currentTabConfig.studiensemester === undefined)
				delete(request.semester)

			this.$refs.loader.show();
			this.$refs.tabComponent.$refs.current.loadData(request).then(() => this.$refs.loader.hide());
		},
		changedStudienjahr()
		{
			this.setVariables()
				.then(() => this.checkVars())
				.then(() => this.reloadTabs(['studienjahr']))
		},
		changedOrg()
		{
			if (this.tabsConfig[this.currentTab]?.config?.reload === false)
			{
				this.loadOneTab()
			}
			else
			{
				this.setVariables()
					.then(() => this.checkVars())
					.then(() => this.reloadTabs(['studiensemester', 'studienjahr']))
			}
		},
		checkStudiensemester ()
		{


			this.selectedStsem = (this.currentStudiensemester || []).map(item => item.studiensemester_kurzbz);

			if (this.tabsConfig[this.currentTab]?.config?.reload === false)
			{
				this.loadOneTab()
			}
			else
			{
				this.setVariables()
					.then(() => this.checkVars())
					.then(() => this.reloadTabs(['studiensemester']))
			}
		},
		async reloadTabs(needChange)
		{
			const currentTabConfig = this.tabsConfig[this.currentTab].config;
			if (this.checkForLoad(currentTabConfig) === false)
				return;
			this.$refs.loader.show();

			await this.loopTabs(needChange)
				.then(() => this.$refs.loader.hide())
		},
		async loopTabs(needChange)
		{
			for (const tabInstance of Object.values(this.tabInstances))
			{
				let tabInstanceConfig = tabInstance.config;
				if (tabInstance && tabInstance.loadData)
				{
					let request = {...this.loadedData};

					if (tabInstanceConfig.studienjahr === undefined)
						delete(request.studienjahr)
					if (tabInstanceConfig.studiensemester === undefined)
						delete(request.semester)

					if (needChange.includes('studienjahr') &&
						!needChange.includes('studiensemester') &&
						request.studienjahr === undefined)
						continue;
					else if (needChange.includes('studiensemester') &&
						!needChange.includes('studienjahr') &&
						request.semester === undefined)
						continue;

					await tabInstance.loadData(request);
				}
			}
		},
		checkForLoad(currentTabConfig)
		{
			if ((currentTabConfig?.studiensemester && this.loadedData.semester?.length === 0) ||
				(currentTabConfig?.studienjahr && this.loadedData.studienjahr.selectedStudienjahr === "") ||
				this.loadedData.org === ""
			)
				return false;
		},
		updateTab(newTab, firstRun = false)
		{
			this.currentTab = newTab;

			if (this.currentTab === 'start' && this.modelValue.needReload === true)
				this.loadOneTab().then(() =>  this.modelValue.needReload = false)
		},
		async setVariables()
		{
			let variables = {
				'var_studienjahr' : this.selectedStudienjahr,
				'var_studiensemester' : this.selectedStsem,
				'var_organisation' : this.selectedOrg
			}
			this.$fhcApi.factory.pep.setVar(variables);
		},
		shouldCheckVars(currentTabConfig)
		{
			return (currentTabConfig?.studiensemester && this.loadedData.semester.length === 0) ||
				(currentTabConfig?.studienjahr && this.loadedData.studienjahr === "") ||
				this.loadedData.org === "";
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
				<div class="row" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.dropdowns">
					<div class="col-md-12" id="container">
						<div class="row">
							<div class="col-md-2" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.studiensemester">
								<Multiselect
									v-model="currentStudiensemester"
									option-label="studiensemester_kurzbz" 
									:options="studiensemestern"
									placeholder="Studiensemester"
									:hide-selected="true"
									:selectionLimit="2"
									@hide="checkStudiensemester"
									class="w-full md:w-80"
								>
								</Multiselect>
							</div>
							<div class="col-md-2" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.studienjahr">
								<select v-model="selectedStudienjahr" class="form-select" @change="changedStudienjahr">
									<option value="">Studienjahr</option>
									<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz" >
										{{ studienjahr.studienjahr_kurzbz }}
									</option>
								</select>
							</div>
							<div class="col-md-3">
								<select v-model="currentOrg" class="form-select" @change="changedOrg">
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
								<button class="btn btn-outline-secondary" aria-label="Reload" @click="loadOneTab">
									<span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
								</button>
							</div>
							<div class="col-md-2">
								<!--<button @click="speichern" class="form-control btn-default">Speichern</button>-->
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



import FhcTabs from '../../../../js/components/Tabs.js';
import {CoreNavigationCmpt} from '../../../../js/components/navigation/Navigation.js';
import FhcLoader from '../../../../js/components/Loader.js';
import FormInput from "../../../../js/components/Form/Input.js";
import ApiSetup from "../api/setup.js"


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
			headerMenuEntries: {},
			sideMenuEntries: {},
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
				organisationen: this.organisationen
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
			isRecursive: false,
			filteredOrganisations: [],
			orgAutocomplete: null,
			filteredOrgs: null,
			orgSearchText: "",


		};
	},
	async created() {
		await this.$p.loadCategory(['ui']);

		await this.loadTabConfig()
			.then(() => this.checkVars());
	},

	computed: {
		formattedOrgs() {
			return this.organisationen.map(org => ({
				oe_kurzbz: org.oe_kurzbz,
				bezeichnung: org.bezeichnung,
				active: org.aktiv,
				displayName: `[${org.organisationseinheittyp_kurzbz}] ${org.bezeichnung} ${org.stgbezeichnung}`
			}));
		},

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

		filteredStudiensemestern()
		{
			if (this.currentStudiensemester.length === 0) {
				return this.studiensemestern;
			}

			const selectedStudienjahr = this.currentStudiensemester[0].studienjahr_kurzbz;

			return this.studiensemestern.filter(
				(semester) => semester.studienjahr_kurzbz === selectedStudienjahr
			);
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
				if (this.tabsConfig[this.currentTab]?.config?.reload)
				{
					this.selectedOrg = value;
				}
				else
				{
					this.selectedOrg_readonly = value;
				}
			}
		},
		autocompleteOrg: {
			get() {
				const orgValue = this.tabsConfig[this.currentTab]?.config?.reload
					? this.selectedOrg
					: this.selectedOrg_readonly;
				return this.getAutocompleteOrg(orgValue);
			},
			set(value) {
				const newOrg = value && value.oe_kurzbz ? value.oe_kurzbz : value;
				if (this.tabsConfig[this.currentTab]?.config?.reload) {
					this.selectedOrg = newOrg;
				} else {
					this.selectedOrg_readonly = newOrg;
				}
			}
		},
	},
	components: {
		FhcTabs,
		CoreNavigationCmpt,
		Multiselect: primevue.multiselect,
		FhcLoader,
		FormInput
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
		getAutocompleteOrg(orgValue) {
			if (!orgValue) return null;
			const selected = this.organisationen.find(org => org.oe_kurzbz === orgValue);

			return selected
				? {
					displayName: `[${selected.organisationseinheittyp_kurzbz}] ${selected.bezeichnung} ${selected.stgbezeichnung}`,
					active: selected.aktiv,
					oe_kurzbz: selected.oe_kurzbz
				}
				: orgValue;
		},
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

			let studienjahrMatch = true;
			if (this.selectedStudienjahr)
			{
				studienjahrMatch = this.currentStudiensemester.every(semester => {
					return semester.studienjahr_kurzbz && semester.studienjahr_kurzbz === this.selectedStudienjahr;
				});

			}

			this.modelValue.config = {
				studienjahr: this.selectedStudienjahr,
				semester: this.selectedStsem,
				org: this.currentOrg,
				recursive: this.isRecursive,
				matched: studienjahrMatch
			}
		},
		async loadTabConfig() {
			await this.$api.call(ApiSetup.getConfig())
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
					.then(() => this.reloadTabs(['org']))
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
						(request.studienjahr === undefined || request.studienjahr === ""))
						continue;
					else if (needChange.includes('studiensemester') &&
						!needChange.includes('studienjahr') &&
						(request.semester === undefined || request.semester?.length === 0))
						continue;
					else if ((needChange.includes('org') && tabInstanceConfig.studienjahr !== undefined && request.studienjahr === "") ||
						(needChange.includes('org') && tabInstanceConfig.studiensemester !== undefined && request.semester?.length === 0))
						continue;
					await tabInstance.loadData(request);
				}
			}
		},
		checkForLoad(currentTabConfig)
		{
			if ((currentTabConfig?.studiensemester && this.loadedData.semester?.length === 0) ||
				(currentTabConfig?.studienjahr && this.loadedData.studienjahr === "") ||
				this.loadedData.org === ""
			)
				return false;
		},
		updateTab(newTab)
		{
			this.currentTab = newTab;
			if (this.currentTab === 'start' && this.modelValue.needReload === true)
				this.loadOneTab().then(() =>  this.modelValue.needReload = false)
			else
				this.redrawTabulator();
		},
		//TODO (david) andere läsung finden
		//INFO: beim wechseln zwischen den tabs bleibt der inhalt ansonsten leer
		redrawTabulator()
		{
			let tabInstance = this.$refs.tabComponent?.$refs?.current?.$refs
			if (tabInstance !== undefined)
			{
				const tableRefs = Object.keys(tabInstance)
					.filter(refName => refName.endsWith('Table'))
					.map(refName => tabInstance[refName]);

				tableRefs.forEach(table => {
					if (table && table.tabulator) {
						try {
							table.tabulator.redraw(true);
						} catch (error) {

						}
					}
				});
			}
		},
		searchOrg(event)
		{
			if (!event.query) {
				this.filteredOrgs = [...this.formattedOrgs];
				return;
			}

			this.orgSearchText = event.query

			const query = event.query.toLowerCase();
			this.filteredOrgs = this.formattedOrgs.filter(org =>
				org.displayName.toLowerCase().includes(query)
			);

			if (this.filteredOrgs.length === 0) {
				this.filteredOrgs = [...this.formattedOrgs];
			}
		},
		async setVariables()
		{
			let variables = {
				'var_studienjahr' : this.selectedStudienjahr,
				'var_studiensemester' : this.selectedStsem,
				'var_organisation' : this.selectedOrg
			}
			this.$api.call(ApiSetup.setVar(variables));
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
						<!--<i class="fa fa-info-circle text-right fa-xs" data-bs-toggle="collapse" href="#faq0"></i>-->
						</h4>
					</div>
				</div>
				<div class="row collapse" id="faq0">
					<div class="col-6">
						<div class="alert alert-info">
							
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
									:options="filteredStudiensemestern"
									placeholder="Studiensemester"
									:hide-selected="true"
									:selectionLimit="2"
									@hide="checkStudiensemester"
									class="timeAuswahl"
								>
								</Multiselect>
							</div>
							<div class="col-md-2" v-if="currentTab !== null && tabsConfig[this.currentTab]?.config?.studienjahr">
								<select v-model="selectedStudienjahr" class="form-select timeAuswahl" @change="changedStudienjahr">
									<option value="">Studienjahr</option>
									<option v-for="studienjahr in studienjahre" :value="studienjahr.studienjahr_kurzbz" >
										{{ studienjahr.studienjahr_kurzbz }}
									</option>
								</select>
							</div>
							<div class="col-md-3">
								<form-input
									type="autocomplete"
									:suggestions="filteredOrgs"
									field="displayName"
									placeholder="Abteilung auswählen"
									v-model="autocompleteOrg"
									@complete="searchOrg"
									@item-select="changedOrg"
									dropdown
								>
								<template #option="slotProps">
									<div :class="{ 'inactive-item': !slotProps.option.active }">
										{{ slotProps.option.displayName }}
									</div>
								</template>
            

								</form-input>

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
								</div>
								<br/>
								<br/>
								<hr />
								</div>
							</div>
				</div>

				<fhc-tabs v-if="tabsConfig"
					ref="tabComponent"
					:config="tabsConfig"
					default="start"
					:vertical="false"
					:border="true"
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



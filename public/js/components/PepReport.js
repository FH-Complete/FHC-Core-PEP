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
import {CoreNavigationCmpt} from '../../../../js/components/navigation/Navigation.js';
import {CoreRESTClient} from '../../../../js/RESTClient.js';

import {StudienjahrDropdown} from './StudienjahrDropdown.js';
import {OrganisationDropdown} from './OrganisationDropdown.js';
import {Button} from './Button.js';
import {NavTabs} from './NavTabs.js';

export const PepReport = {
	data: function() {
		return {
			appSideMenuEntries: {},
			studienjahr: null,
			org: null,
			currentTab: null,
		};
	},
	components: {
		CoreNavigationCmpt,
		CoreFilterCmpt,
		Studienjahr : StudienjahrDropdown,
		Organisation : OrganisationDropdown,
		ButtonCmpt : Button,
		NavTabs
	},
	methods: {
		newSideMenuEntryHandler: function(payload) {
			this.appSideMenuEntries = payload;
		},
		sjChangedHandler: function(sj) {
			this.studienjahr = sj;
		},
		orgChangedHandler: function(org) {
			this.org = org;
		},
		handleButtonClick: function() {
			this.loadReport();
		},
		handleTabChange(tab) {
			this.currentTab = tab;
		},

		async loadReport() {
			try {
				const res = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/' + this.currentTab.action,
					{
						'org' : this.org,
						'studienjahr' : this.studienjahr
					});
				if (CoreRESTClient.isSuccess(res.data))
				{
					this.$refs.navtabs.updateTabData(CoreRESTClient.getData(res.data));
				}
				else
				{
					this.$refs.navtabs.updateTabData();
				}
			} catch (error) {
				this.errors = "Fehler beim Laden der Studiengaenge";
			}
		},
	},
	template: `
	<core-navigation-cmpt 
		v-bind:add-side-menu-entries="sideMenuEntries"
		v-bind:add-header-menu-entries="headerMenuEntries">	
	</core-navigation-cmpt>

	<div id="content">
		<div class="row">
			<Studienjahr @sjChanged="sjChangedHandler"></Studienjahr>
			<Organisation @orgChanged="orgChangedHandler"></Organisation>
			<ButtonCmpt @click="handleButtonClick">Laden</ButtonCmpt>
		</div>
		<hr />
		<div class="row">
			<nav-tabs ref="navtabs" @tabChanged="handleTabChange"></nav-tabs>
		</div>

		
	</div>
`
};



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

import {StudiensemesterDropdown} from './StudiensemesterDropdown.js';
import {OrganisationDropdown} from './OrganisationDropdown.js';
import {Button} from './Button.js';
import {NavTabs} from './NavTabs.js';

export const PepReport = {
	data: function() {
		return {
			appSideMenuEntries: {},
			studienjahr: null,
			studiensemester: [],
			org: null,
			currentTab: null,
			showInfo: false
		};
	},
	components: {
		CoreNavigationCmpt,
		CoreFilterCmpt,
		Organisation : OrganisationDropdown,
		Semester : StudiensemesterDropdown,
		ButtonCmpt : Button,
		NavTabs
	},
	methods: {
		newSideMenuEntryHandler: function(payload) {
			this.appSideMenuEntries = payload;
		},
		orgChangedHandler: function(org) {
			this.org = org;
		},
		ssChangedHandler: function(studiensemester) {
			this.studiensemester = studiensemester;
		},
		handleButtonClick: function() {
			this.loadReport();
		},
		handleTabChange(tab) {
			this.currentTab = tab;
		},
		saveButtonClick: function() {
			this.$refs.navtabs.saveTabData();
		},
		async loadReport() {
			try {
				const res = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/' + this.currentTab.action,
					{
						'org' : this.org,
						'studiensemester' : this.studiensemester
					});
				if (CoreRESTClient.isSuccess(res.data))
				{
					this.$refs.navtabs.updateTabData(CoreRESTClient.getData(res.data));
				}
				else if (CoreRESTClient.isError(res.data))
				{
					this.$fhcAlert.handleSystemMessage(res.data.retval);
					this.$refs.navtabs.updateTabData();
				}
			} catch (error) {
				this.errors = "Fehler beim Laden des Reports";
			}
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
							<Semester @ssChanged="ssChangedHandler"></Semester>
							<Organisation @orgChanged="orgChangedHandler"></Organisation>
							<ButtonCmpt @click="handleButtonClick">Laden</ButtonCmpt>
							<ButtonCmpt @click="saveButtonClick" v-if="currentTab  && currentTab.name === 'Start'">Speichern</ButtonCmpt>
						</div>
						<hr />
						<div class="row">
							<nav-tabs ref="navtabs" @tabChanged="handleTabChange"></nav-tabs>
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
			</div>
		</div>
	</div>
	
`
};



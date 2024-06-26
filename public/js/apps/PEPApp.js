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

import PepReport from '../components/PepReport.js';
import FhcAlert from '../../../../js/plugin/FhcAlert.js';
import fhc_anwesenheitenapifactory from "../api/fhc-anwesenheitenapifactory.js";
import Phrasen from '../../../../js/plugin/Phrasen.js';

Vue.$fhcapi = fhc_anwesenheitenapifactory;

const pepAPP = Vue.createApp({
	components: {
		PepReport,
		Phrasen
	}
});

pepAPP.use(primevue.config.default).use(FhcAlert).use(Phrasen);
pepAPP.mount('#main');

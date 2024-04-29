import {Start} from "./Start.js";
//import {Lehre} from "./Lehre.js";

export const NavTabs = {
	emits: [
		'tabChanged'
	],
	components: {
		Start,
	//	Lehre
	},
	data: function() {
		return {
			currentTab: 'Start',
			tabs: [
				{name: 'Start', action: 'loadReport'},
				{name: 'Lehre', action: 'lehreReport'},
			]
		}
	},
	created () {
		this.$emit('tabChanged', {name: 'Start', action: 'loadReport'});
	},
	methods: {
		emitNewFilterEntry(payload) {
			this.$emit('newFilterEntry', payload);
		},
		changeTab(tab) {
			this.currentTab = tab.name;
			this.$emit('tabChanged', tab);
		},
		updateTabData(data) {
			this.$refs.currentTab.setTableData(data);
		},
		saveTabData() {
			this.$refs.currentTab.saveTableData();
		},
	},
	template: `
	<div class="row">
		<div class="col-md-12">
			<div id="navTabs">
				<ul class="nav nav-tabs" class="mb-5">
					<li class="nav-item" v-for="tab in tabs" :key="tab">
						<a :class="['nav-link', { active: currentTab === tab.name }]" @click="changeTab(tab)">{{ tab.name }} </a>
					</li>
				</ul>
				<component :is="currentTab" ref="currentTab" @new-filter-entry="emitNewFilterEntry"></component>
			</div>
  		</div>
  </div>
	`
};

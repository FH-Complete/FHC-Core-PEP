import {CoreRESTClient} from '../../../../js/RESTClient.js';

export const StudiensemesterDropdown = {
	emits: [
		'ssChanged'
	],
	components: {
		Multiselect: primevue.multiselect
	},
	data () {
		return {
			options: [],
			selectedOption: [],
			errors: null,
			optionsLimit: 2
		};
	},
	created() {
		this.loadDropdown();
	},
	methods: {
		async loadDropdown() {
			try {
				const res = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/getStudiensemester');
				if (CoreRESTClient.isSuccess(res.data))
				{
					this.options = CoreRESTClient.getData(res.data);
					this.selectedOption = [];
					this.$emit("ssChanged", this.selectedOption);
				}
			} catch (error) {
				console.log(error);
			}
		},
		ssChanged(e) {
			const selectedKurzbz = e.value.map(item => item.studiensemester_kurzbz);
			this.$emit("ssChanged", selectedKurzbz);
		}
	},

	template: `
		<div class="col-md-2">
			<Multiselect
					v-model="selectedOption"
					:options="options"
					option-label="studiensemester_kurzbz" 
					@change="ssChanged" 
					placeholder="Studiensemester"
					:hide-selected="true"
					:selectionLimit="2"
					>
			</Multiselect>
		</div>
	`
}
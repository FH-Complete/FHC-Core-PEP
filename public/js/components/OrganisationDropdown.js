import {CoreRESTClient} from '../../../../js/RESTClient.js';

export const OrganisationDropdown = {
	emits: [
		'orgChanged'
	],
	data () {
		return {
			options: [],
			selectedOption: null,
			errors: null
		};
	},
	created() {
		this.loadDropdown();
	},
	methods: {
		async loadDropdown() {
			try {
				const res = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/getOrg');

				if (CoreRESTClient.isSuccess(res.data))
				{
					let data = CoreRESTClient.getData(res.data);
					this.options = data;
					this.selectedOption = data.retval[0];
					this.$emit("orgChanged", this.selectedOption);
				}
			} catch (error) {
				console.log(error);
			}
		},
		orgChanged(e) {
			this.$emit("orgChanged", e.target.value);
		}
	},

	template: `
		<div class="col-md-2">
			<select @change="orgChanged" class="form-control">
				<option>Abteilung</option>
				<option v-for="option in options" :value="option" >
					{{ option }}
				</option>
			</select>
		</div>
	`
}
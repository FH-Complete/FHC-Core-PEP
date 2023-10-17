import {CoreRESTClient} from '../../../../js/RESTClient.js';

export const StudienjahrDropdown = {
	emits: [
		'sjChanged'
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
				const res = await CoreRESTClient.get('/extensions/FHC-Core-PEP/components/PEP/getStudienjahr');
				if (CoreRESTClient.isSuccess(res.data))
				{
					let data = CoreRESTClient.getData(res.data);
					this.options = data;
					this.selectedOption = data[0].studienjahr_kurzbz;
					this.$emit("sjChanged", this.selectedOption);
				}
			} catch (error) {
				console.log(error);
			}
		},
		sjChanged(e) {
			this.$emit("sjChanged", e.target.value);
		}
	},

	template: `
		<div class="col-md-2">
			<select @change="sjChanged" class="form-control">
				<option>Studienjahr</option>
				<option v-for="option in options" :value="option.studienjahr_kurzbz" >
					{{ option.studienjahr_kurzbz }}
				</option>
			</select>
		</div>
	`
}
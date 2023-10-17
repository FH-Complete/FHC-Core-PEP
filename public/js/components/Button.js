export const Button = {
	emits: [
		'click'
	],
	data () {
		return {
			options: [],
			selectedOption: null,
			errors: null
		};
	},
	methods: {
		handleClick(e) {
			this.$emit("click");
		}
	},

	template: `
		<div class="col-md-1">
			<button @click="handleClick" class="form-control btn-default">
				<slot></slot>
			</button>
		</div>
	`
}
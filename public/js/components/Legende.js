export default {
	name: "Legende",
	props: {
		config: null,
	},
	computed: {
		conenturl() {
			return `${this.config.content_url}`;
		}
	},
	template: `
		<core-base-layout>
			<iframe
				:src="conenturl"
				class="full-screen-iframe"
			></iframe>
		</core-base-layout>
	`
};
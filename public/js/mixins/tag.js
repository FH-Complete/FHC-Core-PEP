export default {
	methods: {
		addColorToInfoText(values)
		{
			let span = document.getElementById('selected-info-text');
			if (span)
			{
				span.style.color = values.length > 0 ? 'red' : '';
				span.style.fontWeight = values.length > 0 ? 'bold' : '';
			}
		}
	}
}

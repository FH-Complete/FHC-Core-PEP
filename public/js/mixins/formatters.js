export const formatter = {
	datumFormatter: function(e, cell)
	{
		let value = cell.getValue();
		if (istGueltig(value))
			return "[" + value.gueltig_von + " - " + (value.gueltig_bis === null ? "" : value.gueltig_bis) + "]";
	},
	aktStundensatzFormatter: function(cell, formatterParams, onRendered)
	{
		var value = cell.getValue();

		if (istGueltig(value))
			return value.stundensatz;
		else
			return '-';

	},
	aktStundensatzTooltip: function(e, cell)
	{
		var value = cell.getValue();

		if (istGueltig(value))
			return "[" + value.gueltig_von + " - " + (value.gueltig_bis === null ? "" : value.gueltig_bis) + "]";
		else
			return '-';

	},

	aktDVFormatter: function(cell, formatterParams, onRendered)
	{
		var value = cell.getValue();
		if (istGueltig(value))
			return value
		else
			return '-';
	},

	aktKostenstelleFormatter: function(cell)
	{
		var value = cell.getValue();

		if (istGueltig(value))
			return value;
		else
			return '-';
	},
	aktParentKostenstelleFormatter: function(cell, formatterParams, onRendered)
	{
		var value = cell.getValue();

		if (istGueltig(value))
			return value;
		else
			return '-';
	},
	aktStundenFormatter: function(cell, formatterParams, onRendered)
	{
		var value = cell.getValue();
		if (istGueltig(value))
			return value.wochenstunden;
		else
			return '-';

	},
	aktStundenFormatterTooltip: function(e, cell,)
	{
		var value = cell.getData().aktuelles_dv.stunden;
		if (istGueltig(value))
			return "[" + value.von + " - " + (value.bis === null ? "" : value.bis) + "]";
		else
			return '-';
	},

	dvFormatter: function(cell, formatterParams, onRendered) {


		return (cell.getValue());
		var value = cell.getValue();
		if (!istGueltig(value))
			return "-"
		else if (value.length === 1)
			return value[0].bezeichnung;
		else if (Array.isArray(value))
		{
			let i = 0;
			var dvs = value.map(function(item) {
				i++;
				return "[" + i + "]" +item.bezeichnung;
			}).join('<br />');
			return dvs;
		}
	},

	stundenFormatter: function(cell, formatterParams, onRendered) {
		var value = cell.getData().dv;
		if (!istGueltig(value))
		{
			return '-';
		}
		else if (value.length === 1)
		{
			if (value[0].stunden === null)
			{
				return '-'
			}
			else if (value[0].stunden.length === 1)
			{
				let item = value[0].stunden[0];
				item.bis = (item.bis === null ? "" : item.bis);
				return parseFloat(item.wochenstunden).toFixed(2);
			}
			else if (Array.isArray(value[0].stunden))
			{
				var wochenstundenArray = value[0].stunden.map(function(item) {
					item.bis = (item.bis === null ? "" : item.bis);
					return parseFloat(item.wochenstunden).toFixed(2);
				}).join('<br />');

				return wochenstundenArray;
			}
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item.stunden && item.stunden.length > 0) {
					let stunden_item = item.stunden[0];
					stunden_item.bis = (stunden_item.bis === null ? "" : stunden_item.bis);
					return parseFloat(stunden_item.wochenstunden).toFixed(2);
				}
				return null;
			}).join('<br /> ')
		}
	},
	stundensatzLehre: function(cell, formatterParams, onRendered) {
		var value = cell.getData().stundensaetze_lehre;
		if (!istGueltig(value))
		{
			return '-';
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item) {
					return parseFloat(item.stundensatz).toFixed(2);
				}
				return null;
			}).join('<br /> ')
		}
	},
	stundensatzLehreToolTip: function(e, cell) {
		var value = cell.getData().stundensaetze_lehre;
		if (!istGueltig(value))
		{
			return "";
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item) {
					item.gueltig_bis = (item.gueltig_bis === null ? "" : item.gueltig_bis);
					return " [" + item.gueltig_von + " - " + item.gueltig_bis + "]";
				}
				return null;
			}).join('<br /> ')
		}
	},
	stundenFormatterToolTip: function(e, cell) {
		var value = cell.getData().dv;
		if (!istGueltig(value))
		{
			return "";
		}
		else if (value.length === 1)
		{
			if (value[0].stunden === null)
			{
				return "";
			}
			else if (value[0].stunden.length === 1)
			{
				let item = value[0].stunden[0];
				item.bis = (item.bis === null ? "" : item.bis);
				return "[" + item.von + " - " + item.bis + "]";
			}
			else if (Array.isArray(value[0].stunden))
			{
				var wochenstundenArray = value[0].stunden.map(function(item) {
					item.bis = (item.bis === null ? "" : item.bis);
					return "[" + item.von + " - " + item.bis + "]";
				}).join('<br />');

				return wochenstundenArray;
			}
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item.stunden && item.stunden.length > 0) {
					let stunden_item = item.stunden[0];
					stunden_item.bis = (stunden_item.bis === null ? "" : stunden_item.bis);
					return " [" + stunden_item.von + " - " + stunden_item.bis + "]";
				}
				return null;
			}).join('<br /> ')
		}
	},


	stundenJahrFormatter: function(cell, formatterParams, onRendered) {

		var value = cell.getData().dv;
		if (!istGueltig(value))
		{
			return '-';
		}
		else if (value.length === 1)
		{
			if (value[0].stunden === null)
			{
				return '-';
			}
			else if (value[0].stunden.length === 1)
			{
				let item = value[0].stunden[0];
				item.bis = (item.bis === null ? "" : item.bis);
				return parseFloat(item.jahresstunden).toFixed(2)
			}
			else if (Array.isArray(value[0].stunden))
			{
				var wochenstundenArray = value[0].stunden.map(function(item) {
					item.bis = (item.bis === null ? "" : item.bis);
					return parseFloat(item.jahresstunden).toFixed(2);
				}).join('<br />');

				return wochenstundenArray;
			}
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item.stunden && item.stunden.length > 0) {
					let stunden_item = item.stunden[0];
					stunden_item.bis = (stunden_item.bis === null ? "" : stunden_item.bis);
					return parseFloat(stunden_item.jahresstunden).toFixed(2)
				}
				return null;
			}).join('<br /> ')
		}
	},

	stundenJahrFormatterTooltip: function(e, cell) {

		var value = cell.getData().dv;
		if (!istGueltig(value))
		{
			return "";
		}
		else if (value.length === 1)
		{
			if (value[0].stunden === null)
			{
				return "";
			}
			else if (value[0].stunden.length === 1)
			{
				let item = value[0].stunden[0];
				item.bis = (item.bis === null ? "" : item.bis);
				return " [" + item.von + " - " + item.bis + "]";
			}
			else if (Array.isArray(value[0].stunden))
			{
				var wochenstundenArray = value[0].stunden.map(function(item) {
					item.bis = (item.bis === null ? "" : item.bis);
					return" [" + item.von + " - " + item.bis + "]";
				}).join('<br />');

				return wochenstundenArray;
			}
		}
		else if (Array.isArray(value))
		{
			return value.map(function(item) {
				if (item.stunden && item.stunden.length > 0) {
					let stunden_item = item.stunden[0];
					stunden_item.bis = (stunden_item.bis === null ? "" : stunden_item.bis);
					return stunden_item.jahresstunden + " [" + stunden_item.von + " - " + stunden_item.bis + "]";
				}
				return null;
			}).join('<br /> ')
		}
	},


	checkLehrauftraegeStunden: function(cell, formatterParams, onRendered)
	{
		var row = cell.getRow().getData();
		let value = cell.getValue();
		if (istGueltig((row.releavante_vertragsart)) && istGueltig(row.releavante_vertragsart))
		{
			if (row.releavante_vertragsart === 'externerlehrender')
			{
				if (cell.getValue() > 130)
				{
					return "<span style='color:red; font-weight:bold;'>" + value + "</span>";
				}
			}
		}
		return value;
	},
	karenzFormatter: function(cell, formatterParams, onRendered)
	{
		let value = cell.getValue();
		if (istGueltig(value) && value !== false)
		{
			return "[" + value[0].von + " - " + value[0].bis + "]";
		}
		else
			return "-";
	},

	berechneSumme: function(cell, formatterParams, onRendered)
	{
		var row = cell.getRow().getData();

		if (istGueltig((row.releavante_vertragsart)) && istGueltig(row.releavante_vertragsart))
		{
			if (row.releavante_vertragsart !== 'echterdv')
				return '';
		}
		else
			return '-';

		var praefix = "studiensemester_";

		var summe = cell.getRow().getData().summe;
		for (var key in row)
		{
			if (row.hasOwnProperty(key) && key.startsWith(praefix))
			{
				var wert = row[key];
				if (!isNaN(parseFloat(wert)))
				{
					summe -= parseFloat(wert);
				}
			}
		}
		let calcsum = parseFloat(summe).toFixed(2);
		if (calcsum < 0)
			cell.getElement().classList.add('text-danger');
		else if (calcsum == 0)
			cell.getElement().classList.add('text-success');
		return calcsum;
	},

	berechneSummeBottom: function(values, data, calcParams)
	{
		let bottomsum = 0;
		var praefix = "studiensemester_";
		data.forEach((row) => {
			if (istGueltig((row.releavante_vertragsart)) && istGueltig(row.releavante_vertragsart))
			{
				if (row.releavante_vertragsart !== 'echterdv')
					return;
			}
			else
				return;
			var summe = row.summe;
			for (var key in row)
			{
				if (row.hasOwnProperty(key) && key.startsWith(praefix))
				{
					var wert = row[key];
					if (!isNaN(parseFloat(wert)))
					{
						summe -= parseFloat(wert);
					}
				}
			}
			bottomsum += summe;
		});

		return parseFloat(bottomsum).toFixed(2);
	},
	bottomCalcFormatter: function(cell, formatterParams)
	{
		let value = cell.getValue();

		if (value < 0)
			cell.getElement().classList.add('text-danger');
		else if (value == 0)
			cell.getElement().classList.add('text-success');
		return value;
	},
}


function istGueltig(value)
{
	return value !== null && value !== undefined;
}

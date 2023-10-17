const stundenFormatter = function(cell, formatterParams, onRendered) {
	var value = cell.getValue();

	if (value.length === 1)
	{
		if (value[0].stunden.length === 1)
		{
			return value[0].stunden[0].wochenstunden;
		}
		else
		{
			var wochenstundenArray = value[0].stunden.map(function(item) {
				return item.wochenstunden;
			}).join('<br />');

			return wochenstundenArray;
		}
	}
	else
	{
		return value.map(function(item) {
			if (item.stunden && item.stunden.length > 0) {
				return item.stunden[0].wochenstunden;
			}
			return null;
		}).join('<br /> ')
	}
};

const dvFormatter = function(cell, formatterParams, onRendered) {
	var value = cell.getValue();
	if (value.length === 1)
	{
		return value[0].bezeichnung;
	}
	else
	{
		let i = 0;
		var dvs = value.map(function(item) {
			i++;
			return "[" + i + "]" +item.bezeichnung;
		}).join('<br />');
		return dvs;
	}
}

const aktDVFormatter = function(cell, formatterParams, onRendered)
{
	var value = cell.getValue();

	if (value.bezeichnung === null)
		return "Kein Vertrag vorhanden";
	else
		return value.bezeichnung;
}

const aktStundenFormatter = function(cell, formatterParams, onRendered)
{
	var value = cell.getValue();
	if (value.wochenstunden === null)
		return "Keine Stunden vorhanden";
	else
		return value.wochenstunden;
}

const aktKostenstelleFormatter = function(cell, formatterParams, onRendered)
{
	var value = cell.getValue();
	if (value.kst_oe_kurzbz === null)
		return "Keiner Kostenstelle zugeteilt";
	else
		return value.kst_oe_kurzbz;

}

const jahresFormatter = function() {
	return 1680;
}

const groupFormatter = function(cell, formatterParams, onRendered)
{
	var value = cell.getValue();

	if (value === null)
	{
		var cellData = cell.getData();

		var stg_kuerzel = cellData.stg_kuerzel ? cellData.stg_kuerzel.trim() : "";
		var legrp_semester = cellData.legrp_semester ? cellData.legrp_semester : "";
		var legrp_verband = cellData.legrp_verband ? cellData.legrp_verband.trim() : "";
		var legrp_gruppe = cellData.legrp_gruppe ? cellData.legrp_gruppe.trim() : "";
		return stg_kuerzel + legrp_semester + legrp_verband + legrp_gruppe;
	}
	else
		return value;
}

export const pepViewTabulatorOptions = {
	height: "100%",
	layout: 'fitColumns',
	selectable: true,
	placeholder: "Keine Daten verfügbar",
	headerFilter: true,
	formatterParams: {
	},
	columns: [
		{title: 'Vorname', field: 'vorname', headerFilter: false},
		{title: 'Nachname', field: 'nachname', headerFilter: false},
		{title: 'Zeitraum - DV`s', field: 'dv', headerFilter: false, formatter:dvFormatter},
		{title: 'Zeitraum - Stunden', field: 'dv', headerFilter: false, formatter:stundenFormatter},
		{title: 'Aktuelles - DV', field: 'aktuellstesDV', headerFilter: false, formatter:aktDVFormatter},
		{title: 'Aktuelle - Stunden', field: 'aktuellstesDV', headerFilter: false, formatter:aktStundenFormatter},
		{title: 'Aktuelle - Kostenstelle', field: 'aktuellstesDV', headerFilter: false, formatter:aktKostenstelleFormatter},
		/*{title: 'Lehre', columns: [
				{title: 'WS'},
				{title: 'SS'},
			]
		},*/
		/*{title: 'Laufende PRJ'},
		{title: 'Geplane Projekte'},
		{title: 'F&E Allg'},
		{title: 'Summe'},*/
		//{title: 'Stunden/Jahr', field: '', headerFilter: false, formatter:jahresFormatter},
	],
};

export const lehreViewTabulatorOptions = {
	height: "100%",
	layout: 'fitColumns',
	selectable: true,
	placeholder: "Keine Daten verfügbar",
	headerFilter: true,
	groupStartOpen:false,
	columns: [
		{title: 'Gruppe', field: 'legrp_gruppekz', headerFilter: false, formatter:groupFormatter},
		{title: 'Lektor', field: 'lektor', headerFilter: false},
		{title: 'Lektor', field: 'lektor', headerFilter: false},
		{title: 'Vorname', field: 'lektor_vorname', headerFilter: false},
		{title: 'Nachname', field: 'lektor_nachname', headerFilter: false},
	],

	groupBy:['fakultaet','stg_kuerzel', 'lv_bezeichnung']

};

export const personalViewTabulatorOptions = {
	height: "100%",
	layout: 'fitColumns',
	selectable: true,
	placeholder: "Keine Daten verfügbar",
	headerFilter: true,
	groupStartOpen:false,
	columns: [
		{title: 'Gruppe', field: 'vorname', headerFilter: false, formatter:groupFormatter},
		{title: 'Lektor', field: 'nachname', headerFilter: false},
	],
	groupBy:['fakultaet','department', 'kompetenzfeld']

};

export const pepViewerTabulatorEventHandlers = [
	{
		event: "rowClick",

	}
];


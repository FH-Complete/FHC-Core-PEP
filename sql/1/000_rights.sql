INSERT INTO system.tbl_berechtigung(berechtigung_kurzbz, beschreibung)
SELECT 'mitarbeiter/pep', 'Um auf die Extension PEP zuzugreifen'
WHERE
	NOT EXISTS(SELECT 1 FROM system.tbl_berechtigung WHERE berechtigung_kurzbz='mitarbeiter/pep');

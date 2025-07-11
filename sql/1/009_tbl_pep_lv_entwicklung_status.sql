CREATE TABLE IF NOT EXISTS extension.tbl_pep_lv_entwicklung_status
(
    status_kurzbz  varchar(32) not null primary key,
    bezeichnung_mehrsprachig varchar(128)[]
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_lv_entwicklung_status ADD CONSTRAINT tbl_pep_lv_entwicklung_status_pkey PRIMARY KEY (status_kurzbz);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

INSERT INTO extension.tbl_pep_lv_entwicklung_status(status_kurzbz, bezeichnung_mehrsprachig)
SELECT 'new', '{Neu, New}'
    WHERE
        NOT EXISTS(SELECT 1 FROM extension.tbl_pep_lv_entwicklung_status WHERE status_kurzbz='new');

INSERT INTO extension.tbl_pep_lv_entwicklung_status(status_kurzbz, bezeichnung_mehrsprachig)
SELECT 'kvp', '{KVP, KVP}'
    WHERE
	NOT EXISTS(SELECT 1 FROM extension.tbl_pep_lv_entwicklung_status WHERE status_kurzbz='kvp');



GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_status TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_status TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_lv_entwicklung_status TO web;

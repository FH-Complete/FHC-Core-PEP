CREATE TABLE IF NOT EXISTS extension.tbl_pep_lv_entwicklung_rolle
(
    rolle_kurzbz  varchar(32) not null primary key,
    bezeichnung_mehrsprachig varchar(128)[]
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_lv_entwicklung_rolle ADD CONSTRAINT tbl_pep_lv_entwicklung_rolle_pkey PRIMARY KEY (rolle_kurzbz);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

INSERT INTO extension.tbl_pep_lv_entwicklung_rolle(rolle_kurzbz, bezeichnung_mehrsprachig)
SELECT 'lead', '{Lead, Lead}'
    WHERE
        NOT EXISTS(SELECT 1 FROM extension.tbl_pep_lv_entwicklung_rolle WHERE rolle_kurzbz='lead');

INSERT INTO extension.tbl_pep_lv_entwicklung_rolle(rolle_kurzbz, bezeichnung_mehrsprachig)
SELECT 'member', '{Teammitglied, Team Member}'
    WHERE
	NOT EXISTS(SELECT 1 FROM extension.tbl_pep_lv_entwicklung_rolle WHERE rolle_kurzbz='member');



GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_rolle TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_rolle TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_lv_entwicklung_rolle TO web;

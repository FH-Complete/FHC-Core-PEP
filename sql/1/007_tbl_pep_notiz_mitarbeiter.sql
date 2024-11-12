CREATE TABLE extension.tbl_pep_notiz_mitarbeiter
(
    notiz_id integer NOT NULL,
    mitarbeiter_uid varchar(32) NOT NULL
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_notiz_mitarbeiter ADD CONSTRAINT tbl_pep_notiz_mitarbeiter_pkey PRIMARY KEY (notiz_id, mitarbeiter_uid);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_notiz_mitarbeiter ADD CONSTRAINT tbl_pep_kategorie_mitarbeiter_notiz_id_fkey
            FOREIGN KEY (notiz_id) REFERENCES public.tbl_notiz (notiz_id) ON UPDATE CASCADE ON DELETE RESTRICT ;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
    ALTER TABLE extension.tbl_pep_notiz_mitarbeiter ADD CONSTRAINT tbl_pep_notiz_mitarbeiter_mitarbeiter_uid_fkey
        FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_notiz_mitarbeiter TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_notiz_mitarbeiter TO fhcomplete;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_notiz_mitarbeiter TO web;
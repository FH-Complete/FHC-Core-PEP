CREATE TABLE IF NOT EXISTS extension.tbl_pep_kategorie_notiz
(
    notiz_id                 integer NOT NULL,
    kategorie_mitarbeiter_id integer NOT NULL
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_notiz ADD CONSTRAINT tbl_pep_kategorie_notiz_pkey PRIMARY KEY (notiz_id, kategorie_mitarbeiter_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_notiz ADD CONSTRAINT tbl_pep_kategorie_notiz_notiz_id_fkey
            FOREIGN KEY (notiz_id) REFERENCES public.tbl_notiz (notiz_id) ON UPDATE CASCADE ON DELETE CASCADE ;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_notiz ADD CONSTRAINT tbl_pep_kategorie_notiz_kategorie_mitarbeiter_id_fkey
            FOREIGN KEY (kategorie_mitarbeiter_id) REFERENCES extension.tbl_pep_kategorie_notiz(kategorie_mitarbeiter_id) ON UPDATE CASCADE ON DELETE CASCADE;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_notiz TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_notiz TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_kategorie_notiz TO web;

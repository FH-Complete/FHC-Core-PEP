CREATE TABLE IF NOT EXISTS extension.tbl_pep_lv_entwicklung_notiz
(
    notiz_id                integer NOT NULL,
    pep_lv_entwicklung_id   integer NOT NULL
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_lv_entwicklung_notiz ADD CONSTRAINT tbl_pep_lv_entwicklung_notiz_pkey PRIMARY KEY (notiz_id, pep_lv_entwicklung_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_lv_entwicklung_notiz ADD CONSTRAINT tbl_pep_lv_entwicklung_notiz_notiz_id_fkey
            FOREIGN KEY (notiz_id) REFERENCES public.tbl_notiz (notiz_id) ON UPDATE CASCADE ON DELETE CASCADE ;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_lv_entwicklung_notiz ADD CONSTRAINT tbl_pep_lv_entwicklung_notiz_lv_entwicklung_id_fkey
            FOREIGN KEY (pep_lv_entwicklung_id) REFERENCES extension.tbl_pep_lv_entwicklung(pep_lv_entwicklung_id) ON UPDATE CASCADE ON DELETE CASCADE;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_notiz TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung_notiz TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_lv_entwicklung_notiz TO web;

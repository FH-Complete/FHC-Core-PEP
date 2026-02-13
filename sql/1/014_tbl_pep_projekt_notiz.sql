CREATE TABLE IF NOT EXISTS extension.tbl_pep_projekt_notiz
(
    notiz_id                    integer NOT NULL,
    pep_projects_employees_id   integer NOT NULL
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projekt_notiz ADD CONSTRAINT tbl_pep_projekt_notiz_pkey PRIMARY KEY (notiz_id, pep_projects_employees_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projekt_notiz ADD CONSTRAINT tbl_pep_projekt_notiz_notiz_id_fkey
            FOREIGN KEY (notiz_id) REFERENCES public.tbl_notiz (notiz_id) ON UPDATE CASCADE ON DELETE CASCADE ;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projekt_notiz ADD CONSTRAINT tbl_pep_projekt_notiz_pep_projects_employees_id_fkey
            FOREIGN KEY (pep_projects_employees_id) REFERENCES extension.tbl_pep_projekt_notiz(pep_projects_employees_id) ON UPDATE CASCADE ON DELETE CASCADE;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_projekt_notiz TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_projekt_notiz TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_projekt_notiz TO web;

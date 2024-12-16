CREATE SEQUENCE IF NOT EXISTS extension.tbl_pep_projects_employees_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_projects_employees_seq TO vilesci;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_projects_employees_seq TO fhcomplete;
GRANT SELECT ON SEQUENCE extension.tbl_pep_projects_employees_seq TO web;

CREATE TABLE IF NOT EXISTS extension.tbl_pep_projects_employees
(
    pep_projects_employees_id   integer NOT NULL default NEXTVAL('extension.tbl_pep_projects_employees_seq'::regClass),
    projekt_id                  varchar(42) NOT NULL,
    mitarbeiter_uid             varchar(32) NOT NULL,
    studienjahr_kurzbz          character varying(16) NOT NULL,
    stunden                     numeric(6,2) NOT NULL,
    anmerkung                   text,
    insertamum                  timestamp without time zone DEFAULT now(),
    insertvon                   varchar (32),
    updateamum                  timestamp without time zone,
    updatevon                   varchar(32)
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projects_employees ADD CONSTRAINT tbl_pep_projects_employees_pkey PRIMARY KEY (pep_projects_employees_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;


DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projects_employees ADD CONSTRAINT tbl_pep_projects_employees_projekt_id_fkey
        FOREIGN KEY (projekt_id) REFERENCES sync.tbl_sap_projects_timesheets (project_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projects_employees ADD CONSTRAINT tbl_pep_projects_employees_studienjahr_kurzbz_fkey
        FOREIGN KEY (studienjahr_kurzbz) REFERENCES public.tbl_studienjahr(studienjahr_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_projects_employees ADD CONSTRAINT tbl_pep_projects_employees_mitarbeiter_uid_fkey
        FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;




GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_projects_employees TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_projects_employees TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_projects_employees TO web;

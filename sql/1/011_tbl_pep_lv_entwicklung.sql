CREATE SEQUENCE IF NOT EXISTS extension.tbl_pep_lv_entwicklung_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_lv_entwicklung_seq TO vilesci;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_lv_entwicklung_seq TO fhcomplete;
GRANT SELECT ON SEQUENCE extension.tbl_pep_lv_entwicklung_seq TO web;

CREATE TABLE IF NOT EXISTS extension.tbl_pep_lv_entwicklung
(
    pep_lv_entwicklung_id       integer NOT NULL default NEXTVAL('extension.tbl_pep_lv_entwicklung_seq'::regClass),
    studiensemester_kurzbz      varchar(16) NOT NULL,
    lehrveranstaltung_id        integer,
    mitarbeiter_uid             varchar(32),
    rolle_kurzbz                varchar(32),
    stunden                     numeric(6,2),
    werkvertrag_ects            numeric(5,2),
    status_kurzbz               varchar(16),
    anmerkung                   text,
    weiterentwicklung           boolean default true,

    insertamum                  timestamp without time zone DEFAULT now(),
    insertvon                   varchar (32),
    updateamum                  timestamp without time zone,
    updatevon                   varchar(32)
);

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_pkey PRIMARY KEY (pep_lv_entwicklung_id);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_studiensemester_kurzbz_fkey
    FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_lehrveranstaltung_fkey
    FOREIGN KEY (lehrveranstaltung_id) REFERENCES lehre.tbl_lehrveranstaltung(lehrveranstaltung_id) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_mitarbeiter_uid_fkey
    FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_status_fkey
    FOREIGN KEY (status_kurzbz) REFERENCES extension.tbl_pep_lv_entwicklung_status(status_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE extension.tbl_pep_lv_entwicklung ADD CONSTRAINT tbl_pep_lv_entwicklung_rolle_fkey
    FOREIGN KEY (rolle_kurzbz) REFERENCES extension.tbl_pep_lv_entwicklung_rolle(rolle_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;









GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_lv_entwicklung TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_lv_entwicklung TO web;

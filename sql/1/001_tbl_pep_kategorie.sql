CREATE SEQUENCE IF NOT EXISTS extension.tbl_pep_kategorie_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_id_seq TO vilesci;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_id_seq TO fhcomplete;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_id_seq TO web;

-- ggf. Spalte gueltig ab Studiensemester
CREATE TABLE IF NOT EXISTS extension.tbl_pep_kategorie
(
    kategorie_id                integer NOT NULL default NEXTVAL('extension.tbl_pep_kategorie_id_seq'::regClass),
    bezeichnung                 varchar(32) NOT NULL,
    bezeichnung_mehrsprachig    varchar(128)[],
    default_stunden             numeric(5,2),
    gueltig_ab_studiensemester  character varying(16) NOT NULL,
    gueltig_bis_studiensemester character varying(16),
    aktiv                       BOOLEAN DEFAULT true NOT NULL,
    insertamum                  timestamp without time zone DEFAULT now(),
    insertvon                   varchar (32),
    updateamum                  timestamp without time zone,
    updatevon                   varchar(32)
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie ADD CONSTRAINT tbl_pep_kategorie_pkey PRIMARY KEY (kategorie_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie ADD CONSTRAINT tbl_pep_kategorie_gueltig_ab_studiensemester_kurzbz_fkey
            FOREIGN KEY (gueltig_ab_studiensemester) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie ADD CONSTRAINT tbl_pep_kategorie_gueltig_bis_studiensemester_kurzbz_fkey
            FOREIGN KEY (gueltig_bis_studiensemester) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;


GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie TO fhcomplete;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie TO web;


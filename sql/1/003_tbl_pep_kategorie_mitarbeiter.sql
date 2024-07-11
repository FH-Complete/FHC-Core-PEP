CREATE SEQUENCE IF NOT EXISTS extension.tbl_pep_kategorie_mitarbeiter_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_mitarbeiter_id_seq TO vilesci;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_mitarbeiter_id_seq TO fhcomplete;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_mitarbeiter_id_seq TO web;

CREATE TABLE IF NOT EXISTS extension.tbl_pep_kategorie_mitarbeiter
(
    kategorie_mitarbeiter_id    integer NOT NULL default NEXTVAL('extension.tbl_pep_kategorie_mitarbeiter_id_seq'::regClass),
    kategorie_id                integer NOT NULL,
    mitarbeiter_uid             varchar(32) NOT NULL,
    studienjahr_kurzbz          character varying(16) NOT NULL,
    stunden                     numeric(5,2) NOT NULL,
    anmerkung                   text,
    insertamum                  timestamp without time zone DEFAULT now(),
    insertvon                   varchar (32),
    updateamum                  timestamp without time zone,
    updatevon                   varchar(32)
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_mitarbeiter ADD CONSTRAINT tbl_pep_kategorie_mitarbeiter_pkey PRIMARY KEY (kategorie_mitarbeiter_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_mitarbeiter ADD CONSTRAINT tbl_pep_kategorie_mitarbeiter_studienjahr_kurzbz_fkey
        FOREIGN KEY (studienjahr_kurzbz) REFERENCES public.tbl_studienjahr(studienjahr_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_mitarbeiter ADD CONSTRAINT tbl_pep_kategorie_mitarbeiter_mitarbeiter_uid_fkey
        FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;


DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_mitarbeiter ADD CONSTRAINT tbl_pep_kategorie_mitarbeiter_kategorie_id_fkey
        FOREIGN KEY (kategorie_id) REFERENCES extension.tbl_pep_kategorie(kategorie_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;



GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_mitarbeiter TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_mitarbeiter TO fhcomplete;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_mitarbeiter TO web;


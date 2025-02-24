CREATE SEQUENCE IF NOT EXISTS extension.tbl_pep_kategorie_studienjahr_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_studienjahr_id_seq TO vilesci;
GRANT SELECT, UPDATE ON SEQUENCE extension.tbl_pep_kategorie_studienjahr_id_seq TO fhcomplete;
GRANT SELECT ON SEQUENCE extension.tbl_pep_kategorie_studienjahr_id_seq TO web;

CREATE TABLE IF NOT EXISTS extension.tbl_pep_kategorie_studienjahr
(
    kategorie_studienjahr_id    integer NOT NULL default NEXTVAL('extension.tbl_pep_kategorie_studienjahr_id_seq'::regClass),
    kategorie_id                integer NOT NULL,
    default_stunden             numeric(5,2) NOT NULL,
    gueltig_ab_studienjahr      character varying(16) NOT NULL,
    gueltig_bis_studienjahr     character varying(16),
    insertamum                  timestamp without time zone DEFAULT now(),
    insertvon                   varchar (32),
    updateamum                  timestamp without time zone,
    updatevon                   varchar(32)
);

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_studienjahr ADD CONSTRAINT tbl_pep_kategorie_studienjahr_pkey PRIMARY KEY (kategorie_studienjahr_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie ADD CONSTRAINT tbl_pep_kategorie_gueltig_ab_studienjahr_kurzbz_fkey
        FOREIGN KEY (gueltig_ab_studienjahr) REFERENCES public.tbl_studienjahr(studienjahr_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie ADD CONSTRAINT tbl_pep_kategorie_gueltig_bis_studienjahr_kurzbz_fkey
        FOREIGN KEY (gueltig_bis_studienjahr) REFERENCES public.tbl_studienjahr(studienjahr_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE extension.tbl_pep_kategorie_studienjahr ADD CONSTRAINT tbl_pep_kategorie_studienjahr_kategorie_id_fkey
        FOREIGN KEY (kategorie_id) REFERENCES extension.tbl_pep_kategorie(kategorie_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;



GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_studienjahr TO vilesci;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE extension.tbl_pep_kategorie_studienjahr TO fhcomplete;
GRANT SELECT ON TABLE extension.tbl_pep_kategorie_studienjahr TO web;

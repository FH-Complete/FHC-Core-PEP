INSERT INTO public.tbl_variablenname (name, defaultwert)
VALUES ('pep_studiensemester', NULL),
       ('pep_studienjahr', NULL),
       ('pep_abteilung', NULL)
    ON CONFLICT (name) DO NOTHING;


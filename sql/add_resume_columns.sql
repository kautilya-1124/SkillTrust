-- Resume file paths (relative to project root, e.g. uploads/resumes/xxx.pdf)
ALTER TABLE users ADD COLUMN general_cv VARCHAR(512) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN specialization_cv VARCHAR(512) NULL DEFAULT NULL;

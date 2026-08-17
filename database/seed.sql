-- Default roles
INSERT INTO roles (name) VALUES ('Admin'), ('Plant Head');

-- Sample masters matching the actual plant products - each Product is a
-- full SKU (name + size together), there is no separate Bag Size master.
INSERT INTO products (name, size_kg) VALUES ('MG Cem', 10), ('MG+', 25);
INSERT INTO tokens (token_value) VALUES ('50'), ('70'), ('100'), ('200'), ('N/A');

-- Sample raw material (edit/add more from Admin > Raw Materials screen)
INSERT INTO raw_materials (name) VALUES ('Clinker'), ('Gypsum'), ('Fly Ash');

-- NOTE: no default user is inserted here because passwords must be hashed
-- by PHP (password_hash), not written as plain text in SQL.
-- Run database/create-admin.php once in the browser to create the first Admin user, then DELETE that file.

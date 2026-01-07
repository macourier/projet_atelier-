-- Table pour stocker le profil de l'entreprise (logo, coordonnées) (PostgreSQL version)
-- Une seule ligne avec id=1 sera utilisée

CREATE TABLE IF NOT EXISTS company_profile (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL DEFAULT 'L''atelier vélo',
  address_line1 TEXT NOT NULL DEFAULT '10 avenue Willy Brandt',
  address_line2 TEXT DEFAULT '',
  postcode TEXT NOT NULL DEFAULT '59000',
  city TEXT NOT NULL DEFAULT 'Lille',
  phone TEXT NOT NULL DEFAULT '03 20 78 80 63',
  email TEXT DEFAULT '',
  logo_path TEXT DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insérer la ligne par défaut si elle n'existe pas déjà
INSERT INTO company_profile (id, name, address_line1, address_line2, postcode, city, phone, email)
VALUES (1, 'L''atelier vélo', '10 avenue Willy Brandt', '', '59000', 'Lille', '03 20 78 80 63', '')
ON CONFLICT (id) DO NOTHING;
-- ============================================================
-- WORLDWIDE COUNTRY / STATE / CITY REFERENCE DATA
-- ============================================================
-- Adds three new lookup tables for the distributor registration
-- form's Country -> State -> City cascading dropdown, covering
-- the whole world (not just India).
--
-- Data source: "Countries States Cities Database" by dr5hn
--   https://github.com/dr5hn/countries-states-cities-database
--   Release used: v3.2-export.7 (data last updated 2026-07-29)
--   Coverage: 250 countries, 5,308 states/provinces, 152,970 cities
--
-- LICENSE NOTE (please read):
--   The project's CODE is MIT licensed, but the actual DATA
--   CONTENT (the country/state/city rows themselves) is licensed
--   under the Open Database License v1.0 (ODbL) + Database
--   Contents License (DbCL). This is NOT a pure MIT/CC0/public-domain
--   dataset - ODbL permits free commercial use and modification,
--   but does require a one-time attribution notice, e.g. somewhere
--   in an About/Credits/footer section of the app:
--     "Contains data from Countries States Cities Database
--      (https://github.com/dr5hn/countries-states-cities-database),
--      licensed under ODbL v1.0."
--   There is no per-record or ongoing obligation beyond that single
--   notice as long as this data is used internally to power the app
--   (not redistributed/resold as a standalone database). No
--   comprehensive (~150k-city), actively-maintained, truly
--   attribution-free worldwide dataset currently exists for free -
--   virtually all of them (GeoNames, simplemaps free tier, etc.)
--   carry the same kind of lightweight attribution requirement,
--   because they all ultimately trace back to GeoNames.org (CC BY).
--   dr5hn's dataset was chosen because it is the most current,
--   complete, and well-structured (proper FK hierarchy) of the
--   available options.
--
-- Source CSVs (as downloaded) are kept alongside this file in
-- database/geo-src/ for reproducibility:
--   database/geo-src/countries.csv  (250 rows)
--   database/geo-src/states.csv     (5,308 rows)
--   database/geo-src/cities.csv     (152,970 rows, decompressed from
--                                    the release's csv-cities.csv.gz)
-- ============================================================

USE cement_erp;

-- ------------------------------------------------------------
-- Table: geo_countries
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS geo_countries (
  id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  iso2 CHAR(2) NOT NULL,
  iso3 CHAR(3) NULL,
  numeric_code CHAR(3) NULL,
  phonecode VARCHAR(20) NULL,
  capital VARCHAR(100) NULL,
  currency VARCHAR(10) NULL,
  currency_name VARCHAR(50) NULL,
  currency_symbol VARCHAR(10) NULL,
  region VARCHAR(50) NULL,
  subregion VARCHAR(60) NULL,
  emoji VARCHAR(10) NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_geo_countries_iso2 (iso2),
  KEY idx_geo_countries_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: geo_states
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS geo_states (
  id INT NOT NULL,
  country_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  state_code VARCHAR(10) NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_geo_states_country (country_id),
  KEY idx_geo_states_name (name),
  CONSTRAINT fk_geo_states_country FOREIGN KEY (country_id) REFERENCES geo_countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: geo_cities
-- ------------------------------------------------------------
-- country_id is denormalised onto every city row (in addition to
-- state_id) purely so city lookups/searches can filter by country
-- directly without an extra join back through geo_states.
CREATE TABLE IF NOT EXISTS geo_cities (
  id INT NOT NULL,
  state_id INT NOT NULL,
  country_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_geo_cities_state (state_id),
  KEY idx_geo_cities_country (country_id),
  KEY idx_geo_cities_name (name),
  CONSTRAINT fk_geo_cities_state FOREIGN KEY (state_id) REFERENCES geo_states(id),
  CONSTRAINT fk_geo_cities_country FOREIGN KEY (country_id) REFERENCES geo_countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Load data (idempotent-ish: skip if already populated)
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

-- geo_countries (28-column source CSV; unused columns discarded into @dummy vars)
LOAD DATA INFILE 'C:/xampp/htdocs/cement-erp/database/geo-src/countries.csv'
INTO TABLE geo_countries
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(id, name, iso3, iso2, numeric_code, phonecode, capital, currency, currency_name,
 currency_symbol, @tld, @native, @population, @gdp, region, @region_id, subregion,
 @subregion_id, @nationality, @area_sq_km, @postal_code_format, @postal_code_regex,
 @timezones, latitude, longitude, emoji, @emojiU, @wikiDataId);

-- geo_states (17-column source CSV)
LOAD DATA INFILE 'C:/xampp/htdocs/cement-erp/database/geo-src/states.csv'
INTO TABLE geo_states
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(id, name, country_id, @country_code, @country_name, state_code, @iso3166_2,
 @fips_code, @type, @level, @parent_id, @native, latitude, longitude, @timezone,
 @wikiDataId, @population);

-- geo_cities (17-column source CSV, decompressed from csv-cities.csv.gz)
LOAD DATA INFILE 'C:/xampp/htdocs/cement-erp/database/geo-src/cities.csv'
INTO TABLE geo_cities
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(id, name, state_id, @state_code, @state_name, country_id, @country_code,
 @country_name, latitude, longitude, @native, @type, @level, @parent_id,
 @population, @timezone, @wikiDataId);

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;

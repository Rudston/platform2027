I have a MySQL database called 'vision_summit' with non-Laravel-compliant
tables (e.g. 'City' with primary key 'ID', foreign keys like 'CityID').
This database is in the .env file as DB_VISION_DATABASE=vision_summit and
in the database config connections.

Before doing anything, confirm you understand the full task by summarising
it back to me. Then begin Step 1 only. Stop after each step for my review
before proceeding.

1. Check that you can connect to the 'vision_summit' database.

2. Read the table structures from 'vision_summit' for ONLY these tables:
   Province, DistrictMunicipality, LocalMunicipality, City, UrbanPlace, CoordinateData, Location

3. Create Laravel migrations for 'platform2027' with proper naming:
   Province             → provinces
   DistrictMunicipality → district_municipalities
   LocalMunicipality    → local_municipalities
   City                 → cities
   UrbanPlace           → urban_places (suburbs of large cities)
   CoordinateData       → coordinate_data
   Location             → locations

   Locations belong to:
    - province_id (always, required)
    - district_municipality_id (always, required)
    - local_municipality_id (nullable)
    - city_id (nullable)
    - urban_place_id (nullable, only if city_id is set)
      Rule: either local_municipality_id OR city_id is set, never both.

   Field rules:
    - ID → id (primary key)
    - CityID → city_id (foreign key), etc. for all foreign keys
    - ClassName: exclude this field entirely
    - LastEdited → updated_at (fill with current datetime)
    - Created → created_at (fill with current datetime)
    - Exclude these fields from Location: Count,SubsiteID,Demo,DemoCount

   Migration order (to respect foreign key constraints):
    1. provinces
    2. district_municipalities
    3. locations
    4. local_municipalities
    5. cities
    5. urban_places
   

4. Create Eloquent models for each table in app/Models/Demography

5. Create seeders that read from 'vision_summit' using
   DB::connection('vision_summit') and insert into 'platform2027'

6. Show me the list of migration files created, then wait for my
   confirmation before running them.

7. Show me the record counts to be copied, then wait for my
   confirmation before running the seeders.

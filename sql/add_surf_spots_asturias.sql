USE reto_asturias_activa;

SET NAMES utf8mb4;

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Rodiles',
  'Villaviciosa',
  'Spot emblematico junto a la ria de Villaviciosa, conocido por izquierdas largas cerca de la desembocadura y picos mas nobles con marea alta. Revisa parte de olas, marea y corrientes antes de entrar.',
  1.00,
  5,
  'Alta',
  'Surf',
  210,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20Rodiles.jpg',
  '[{"lat":43.5327,"lng":-5.3858},{"lat":43.5323,"lng":-5.3799},{"lat":43.5320,"lng":-5.3745}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Rodiles' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Salinas - El Espartal',
  'Castrillon',
  'Arenal largo y muy surfero, con muchos picos de derechas e izquierdas. Buena opcion para progresar cuando el mar esta ordenado y exigente cuando sube el tamano.',
  2.10,
  5,
  'Media',
  'Surf',
  140,
  'https://commons.wikimedia.org/wiki/Special:FilePath/10%20Playa%20de%20Salinas.jpg',
  '[{"lat":43.5774,"lng":-5.9633},{"lat":43.5794,"lng":-5.9530},{"lat":43.5820,"lng":-5.9425}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Salinas - El Espartal' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en La Grande de Tapia',
  'Tapia de Casariego',
  'Playa historica del surf asturiano, ligada a campeonatos y con secciones de arena y roca. Ideal para surfistas con experiencia en dias de mar consistente.',
  0.60,
  5,
  'Alta',
  'Surf',
  200,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Tapia-playa.jpg',
  '[{"lat":43.5687,"lng":-6.9481},{"lat":43.5689,"lng":-6.9448},{"lat":43.5692,"lng":-6.9419}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en La Grande de Tapia' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en San Lorenzo',
  'Gijon',
  'Spot urbano con mucha tradicion, varios picos a lo largo de la playa y ambiente constante. Las escaleras centrales son referencia habitual para mirar condiciones.',
  1.50,
  4,
  'Media',
  'Surf',
  130,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20san%20lorenzo%20gijon.JPG',
  '[{"lat":43.5414,"lng":-5.6636},{"lat":43.5421,"lng":-5.6558},{"lat":43.5430,"lng":-5.6485}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en San Lorenzo' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Xago',
  'Gozon',
  'Playa abierta, expuesta y con dunas, muy sensible al mar del noroeste. Ofrece picos rapidos de derechas e izquierdas y conviene entrar con el parte claro.',
  1.80,
  12,
  'Media',
  'Surf',
  150,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20Xago%20%28Asturias%29.jpg',
  '[{"lat":43.6052,"lng":-5.9250},{"lat":43.6042,"lng":-5.9177},{"lat":43.6030,"lng":-5.9100}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Xago' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Santa Marina',
  'Ribadesella',
  'Playa urbana de Ribadesella, con varios picos y escuelas cercanas. Buena para sesiones de aprendizaje o progresion cuando el oleaje entra limpio.',
  1.20,
  4,
  'Media',
  'Surf',
  120,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Ribadesella-plage.jpg',
  '[{"lat":43.4657,"lng":-5.0740},{"lat":43.4654,"lng":-5.0690},{"lat":43.4650,"lng":-5.0640}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Santa Marina' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Vega',
  'Ribadesella',
  'Arenal amplio y natural, muy abierto al Cantabrico. Suele funcionar mejor con mar ordenado y es especialmente interesante fuera de los dias mas masificados.',
  1.50,
  8,
  'Media',
  'Surf',
  145,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20la%20Vega%20general%20view%202.jpg',
  '[{"lat":43.4800,"lng":-5.1450},{"lat":43.4800,"lng":-5.1389},{"lat":43.4798,"lng":-5.1300}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Vega' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en San Antolin',
  'Llanes',
  'Playa muy abierta y expuesta al oleaje, frecuentada por surfistas. Puede ser potente, asi que es mejor reservarla para dias controlados o para gente con base.',
  1.20,
  6,
  'Alta',
  'Surf',
  185,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20San%20Antol%C3%ADn.jpg',
  '[{"lat":43.4430,"lng":-4.8760},{"lat":43.4430,"lng":-4.8682},{"lat":43.4430,"lng":-4.8620}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en San Antolin' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Penarronda',
  'Castropol - Tapia de Casariego',
  'Playa orientada al norte, con barras y picos de derecha e izquierda. Suele tener calidad a media marea, pero las corrientes piden entrar con respeto.',
  0.60,
  5,
  'Alta',
  'Surf',
  180,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa-Penarronda.jpg',
  '[{"lat":43.5532,"lng":-6.9990},{"lat":43.5532,"lng":-6.9960},{"lat":43.5532,"lng":-6.9910}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Penarronda' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Frejulfe',
  'Navia',
  'Playa natural del occidente, con fondo de arena, derechas e izquierdas y oleaje frecuente. Ojo a las corrientes y a los cambios rapidos de viento.',
  0.80,
  4,
  'Media',
  'Surf',
  135,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20Frejulfe%28Asturias%29.jpg',
  '[{"lat":43.5592,"lng":-6.6790},{"lat":43.5591,"lng":-6.6745},{"lat":43.5590,"lng":-6.6700}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Frejulfe' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Playon de Bayas',
  'Castrillon - Soto del Barco',
  'Arenal larguisimo y abierto, continuacion natural de Los Quebrantos. Recibe bien el mar y permite repartir picos, aunque el oleaje y las corrientes exigen prudencia.',
  2.80,
  5,
  'Media',
  'Surf',
  150,
  'https://commons.wikimedia.org/wiki/Special:FilePath/33%20Play%C3%B3n%20de%20Bayas.jpg',
  '[{"lat":43.5747,"lng":-6.0520},{"lat":43.5747,"lng":-6.0424},{"lat":43.5747,"lng":-6.0340}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Playon de Bayas' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Verdicio',
  'Gozon',
  'Spot corto e intenso en entorno dunar, con olas fuertes que rompen cerca de la orilla. En buenas condiciones puede dar izquierdas potentes y secciones huecas.',
  0.30,
  6,
  'Alta',
  'Surf',
  175,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20Verdicio.jpg',
  '[{"lat":43.6293,"lng":-5.8780},{"lat":43.6290,"lng":-5.8756},{"lat":43.6288,"lng":-5.8730}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Verdicio' AND activity_type = 'Surf');

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
SELECT
  'Surf en Otur',
  'Valdes',
  'Playa expuesta del occidente con picos variables sobre arena. Funciona bien con mar del noroeste y marea baja o media, pero conviene vigilar fuerza y corrientes.',
  0.60,
  4,
  'Media',
  'Surf',
  135,
  'https://commons.wikimedia.org/wiki/Special:FilePath/Playa%20de%20Otur%20este.jpg',
  '[{"lat":43.5531,"lng":-6.6000},{"lat":43.5531,"lng":-6.5975},{"lat":43.5528,"lng":-6.5940}]',
  1,
  'approved',
  1,
  NOW()
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'Surf en Otur' AND activity_type = 'Surf');

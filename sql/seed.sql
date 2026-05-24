USE reto_asturias_activa;

SET NAMES utf8mb4;

INSERT INTO users (id, name, email, password, email_verified_at, is_admin, is_active, total_points, level, created_at)
VALUES
  (1, 'Administrador', 'admin@retoasturiasactiva.es', '$2y$10$evX2X.Emoo6IIzeHjno8aOXc5a.E8PaHw2ayMy2kBkDTGcTdq8jCy', NOW(), 1, 1, 0, 1, NOW()),
  (2, 'Aventurero Demo', 'usuario@retoasturiasactiva.es', '$2y$10$AwPCQqerxg3Sv.fLJb73K.t5lgtPgMxsHwHqt8eAfOXU6fOTG6pja', NOW(), 0, 1, 350, 1, NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password = VALUES(password),
  email_verified_at = VALUES(email_verified_at),
  is_admin = VALUES(is_admin),
  is_active = VALUES(is_active);

INSERT INTO routes
  (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, created_by)
VALUES
  ('Ruta del Cares','Picos de Europa','Recorrido emblematico entre Poncebos y Cain, con desfiladeros espectaculares.',11.80,320,'Alta','Senderismo',200,'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?q=80&w=1400&auto=format&fit=crop','[{"lat":43.2542,"lng":-4.8105},{"lat":43.2714,"lng":-4.7990},{"lat":43.2882,"lng":-4.7764},{"lat":43.3018,"lng":-4.7522}]',1,1),
  ('Senda del Oso','Trubia - Teverga','Via verde familiar entre tuneles, bosques y puentes sobre el rio Trubia.',22.40,280,'Media','Ciclismo',120,'https://images.unsplash.com/photo-1455156218388-5e61b526818b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.3508,"lng":-5.9872},{"lat":43.2985,"lng":-6.0061},{"lat":43.2259,"lng":-6.1132},{"lat":43.1628,"lng":-6.1025}]',1,1),
  ('Lagos de Covadonga','Cangas de Onis','Circular de alta montana por Enol y Ercina con vistas panoramicas.',7.20,410,'Media','Senderismo',110,'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?q=80&w=1400&auto=format&fit=crop','[{"lat":43.2712,"lng":-4.9954},{"lat":43.2801,"lng":-4.9892},{"lat":43.2888,"lng":-4.9821},{"lat":43.2767,"lng":-4.9754}]',1,1),
  ('Ruta de las Xanas','Santo Adriano','Desfiladero corto y precioso ideal para iniciacion.',8.10,260,'Baja','Senderismo',70,'https://images.unsplash.com/photo-1511497584788-876760111969?q=80&w=1400&auto=format&fit=crop','[{"lat":43.2983,"lng":-6.0140},{"lat":43.2915,"lng":-6.0248},{"lat":43.2809,"lng":-6.0395},{"lat":43.2722,"lng":-6.0501}]',1,1),
  ('Camin Encantau','Llanes','Ruta tematica entre bosque y costa con esculturas mitologicas.',9.60,180,'Baja','Trail',65,'https://images.unsplash.com/photo-1472396961693-142e6e269027?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4192,"lng":-4.8605},{"lat":43.4139,"lng":-4.8501},{"lat":43.4071,"lng":-4.8387},{"lat":43.4024,"lng":-4.8269}]',1,1),
  ('Subida al Picu Pienzu','Sierra del Sueve','Ascension exigente con vistas completas del Cantabrico y la Cordillera.',14.20,980,'Muy Alta','Senderismo',300,'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4749,"lng":-5.2875},{"lat":43.4671,"lng":-5.2744},{"lat":43.4590,"lng":-5.2610},{"lat":43.4507,"lng":-5.2478}]',1,1),
  ('Ruta del Alba','Sobrescobio','Recorrido por desfiladero y bosque de Redes junto al rio Alba.',14.50,520,'Media','Senderismo',120,'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.1898,"lng":-5.4551},{"lat":43.1822,"lng":-5.4365},{"lat":43.1714,"lng":-5.4158},{"lat":43.1621,"lng":-5.4012}]',1,1),
  ('Hoces del Esva','Valdes - Tineo','Tramo de rio encajado con pasarelas, puentes y bosque atlantico.',16.20,600,'Alta','Senderismo',200,'https://images.unsplash.com/photo-1472396961693-142e6e269027?q=80&w=1400&auto=format&fit=crop','[{"lat":43.5088,"lng":-6.5304},{"lat":43.4927,"lng":-6.5092},{"lat":43.4771,"lng":-6.4925},{"lat":43.4592,"lng":-6.4703}]',1,1),
  ('Foces del Pino','Aller','Garganta rocosa en el concejo de Aller, ideal para media montana.',9.40,430,'Media','Senderismo',110,'https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0662,"lng":-5.6217},{"lat":43.0580,"lng":-5.6066},{"lat":43.0478,"lng":-5.5892},{"lat":43.0401,"lng":-5.5738}]',1,1),
  ('Brana de Mumian','Somiedo','Ruta tradicional de branas vaqueiras con panoramicas del parque.',8.60,390,'Media','Senderismo',100,'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?q=80&w=1400&auto=format&fit=crop','[{"lat":43.1016,"lng":-6.2621},{"lat":43.0935,"lng":-6.2468},{"lat":43.0862,"lng":-6.2325},{"lat":43.0799,"lng":-6.2191}]',1,1),
  ('Lago del Valle','Somiedo','Ascension al mayor lago natural de Asturias desde Valle de Lago.',12.80,720,'Alta','Senderismo',210,'https://images.unsplash.com/photo-1506197603052-3cc9c3a201bd?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0497,"lng":-6.2335},{"lat":43.0364,"lng":-6.2149},{"lat":43.0242,"lng":-6.1986},{"lat":43.0115,"lng":-6.1831}]',1,1),
  ('Pico Monsacro','Morcin','Ruta de media-alta montana con ermitas historicas y vistas sobre Oviedo.',10.10,650,'Alta','Senderismo',190,'https://images.unsplash.com/photo-1465311440653-ba9b1d9b0f5b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.2381,"lng":-5.9049},{"lat":43.2299,"lng":-5.8902},{"lat":43.2210,"lng":-5.8748},{"lat":43.2141,"lng":-5.8610}]',1,1),
  ('Cascadas de Oneta','Villayon','Itinerario corto para visitar varias cascadas en un valle frondoso.',6.20,240,'Baja','Senderismo',75,'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?q=80&w=1400&auto=format&fit=crop','[{"lat":43.3051,"lng":-6.7195},{"lat":43.2988,"lng":-6.7082},{"lat":43.2929,"lng":-6.6997},{"lat":43.2864,"lng":-6.6905}]',1,1),
  ('Bufones de Pria','Llanes','Recorrido costero por acantilados y bufones con fuerte influencia marina.',7.80,120,'Baja','Senderismo',65,'https://images.unsplash.com/photo-1474511320723-9a56873867b5?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4547,"lng":-4.9981},{"lat":43.4491,"lng":-4.9868},{"lat":43.4432,"lng":-4.9744},{"lat":43.4380,"lng":-4.9630}]',1,1),
  ('Senda Costera de Llanes','Llanes','Tramo litoral con playas, miradores y praderia sobre acantilado.',12.30,210,'Media','Trail',105,'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4224,"lng":-4.7549},{"lat":43.4161,"lng":-4.7308},{"lat":43.4104,"lng":-4.7062},{"lat":43.4047,"lng":-4.6835}]',1,1),
  ('Senda del Cervigon','Gijon','Paseo litoral entre Rinconin y La Nora, apto para todos los niveles.',7.00,95,'Baja','Senderismo',60,'https://images.unsplash.com/photo-1473116763249-2faaef81ccda?q=80&w=1400&auto=format&fit=crop','[{"lat":43.5554,"lng":-5.6519},{"lat":43.5492,"lng":-5.6384},{"lat":43.5427,"lng":-5.6256},{"lat":43.5371,"lng":-5.6122}]',1,1),
  ('Cabo Penas Circular','Gozon','Circular costera por prados y acantilados del punto mas septentrional de Asturias.',8.90,165,'Baja','Senderismo',70,'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.6588,"lng":-5.8440},{"lat":43.6534,"lng":-5.8295},{"lat":43.6471,"lng":-5.8158},{"lat":43.6419,"lng":-5.8021}]',1,1),
  ('Camin Real de la Mesa','Teverga','Ruta historica de montana con grandes vistas y patrimonio pastoril.',18.70,780,'Alta','Senderismo',220,'https://images.unsplash.com/photo-1482192505345-5655af888cc4?q=80&w=1400&auto=format&fit=crop','[{"lat":43.1228,"lng":-6.0161},{"lat":43.1116,"lng":-5.9939},{"lat":43.1014,"lng":-5.9688},{"lat":43.0918,"lng":-5.9437}]',1,1),
  ('Subida al Angliru','Riosa','Ascension mitica de ciclismo con rampas extremas en su tramo final.',13.20,1240,'Muy Alta','Ciclismo',320,'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?q=80&w=1400&auto=format&fit=crop','[{"lat":43.2044,"lng":-5.8823},{"lat":43.1885,"lng":-5.8702},{"lat":43.1730,"lng":-5.8576},{"lat":43.1598,"lng":-5.8442}]',1,1),
  ('Pena Mea desde Les Campes','Laviana','Ruta exigente de montana con crestas y panoramicas del Nalon.',13.50,940,'Muy Alta','Senderismo',300,'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.1821,"lng":-5.5461},{"lat":43.1708,"lng":-5.5320},{"lat":43.1594,"lng":-5.5184},{"lat":43.1480,"lng":-5.5051}]',1,1),
  ('Ruta del Castaneu de Pombarinos','Cangas del Narcea','Sendero corto para conocer un castano monumental en Muniellos.',4.50,140,'Baja','Senderismo',55,'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0542,"lng":-6.7192},{"lat":43.0491,"lng":-6.7087},{"lat":43.0440,"lng":-6.6996},{"lat":43.0394,"lng":-6.6911}]',1,1),
  ('Bosque de Muniellos - Tablizas','Cangas del Narcea','Tramo de reserva natural con hayedos y robledales de alto valor ecologico.',20.40,720,'Alta','Senderismo',230,'https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0258,"lng":-6.7521},{"lat":43.0137,"lng":-6.7376},{"lat":43.0020,"lng":-6.7218},{"lat":42.9924,"lng":-6.7055}]',1,1),
  ('Foces del Rio Pendon','Nava','Recorrido por garganta de roca caliza y bosque mixto en zona central.',11.70,510,'Media','Senderismo',120,'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?q=80&w=1400&auto=format&fit=crop','[{"lat":43.3591,"lng":-5.5180},{"lat":43.3497,"lng":-5.5025},{"lat":43.3411,"lng":-5.4868},{"lat":43.3320,"lng":-5.4721}]',1,1),
  ('Senda Fluvial del Nora','Siero','Paseo sencillo junto al rio Nora apto para actividad familiar.',10.40,130,'Baja','Senderismo',68,'https://images.unsplash.com/photo-1472396961693-142e6e269027?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4107,"lng":-5.6954},{"lat":43.4048,"lng":-5.6783},{"lat":43.3985,"lng":-5.6617},{"lat":43.3919,"lng":-5.6450}]',1,1),
  ('Miradores del Naranco','Oviedo','Circular por pistas y senderos con vistas de la ciudad y la cordillera.',6.90,280,'Baja','Trail',72,'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.3744,"lng":-5.8586},{"lat":43.3680,"lng":-5.8464},{"lat":43.3609,"lng":-5.8349},{"lat":43.3547,"lng":-5.8241}]',1,1),
  ('Ruta de los Molinos de Villaviciosa','Villaviciosa','Itinerario rural entre aldeas, molinos historicos y arroyos.',9.80,190,'Baja','Senderismo',78,'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4702,"lng":-5.4341},{"lat":43.4626,"lng":-5.4198},{"lat":43.4555,"lng":-5.4057},{"lat":43.4489,"lng":-5.3924}]',1,1),
  ('Cascada del Cioyo','Castropol','Ruta corta de interior hasta una cascada encajada en bosque humedo.',7.10,310,'Media','Senderismo',95,'https://images.unsplash.com/photo-1511497584788-876760111969?q=80&w=1400&auto=format&fit=crop','[{"lat":43.5153,"lng":-7.0662},{"lat":43.5096,"lng":-7.0531},{"lat":43.5038,"lng":-7.0417},{"lat":43.4982,"lng":-7.0304}]',1,1),
  ('Senda Costera Naviega','Navia','Recorrido litoral entre acantilados, playas y pueblos marineros.',13.00,260,'Media','Trail',110,'https://images.unsplash.com/photo-1473116763249-2faaef81ccda?q=80&w=1400&auto=format&fit=crop','[{"lat":43.5405,"lng":-6.7191},{"lat":43.5332,"lng":-6.7029},{"lat":43.5261,"lng":-6.6862},{"lat":43.5196,"lng":-6.6700}]',1,1),
  ('Branas Vaqueiras de Salas','Salas','Ruta de media-alta montana por pastizales y branas tradicionales.',15.60,640,'Alta','Senderismo',200,'https://images.unsplash.com/photo-1482192505345-5655af888cc4?q=80&w=1400&auto=format&fit=crop','[{"lat":43.4040,"lng":-6.2655},{"lat":43.3921,"lng":-6.2478},{"lat":43.3813,"lng":-6.2304},{"lat":43.3709,"lng":-6.2128}]',1,1),
  ('Cuetu Arbas','Lena','Ascension alpina con fuerte desnivel y vistas hacia la divisoria.',11.90,860,'Alta','Senderismo',215,'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0508,"lng":-5.8707},{"lat":43.0416,"lng":-5.8546},{"lat":43.0327,"lng":-5.8388},{"lat":43.0234,"lng":-5.8230}]',1,1),
  ('Pena Ubina desde Tuiza','Lena','Ruta de alta montana a una de las cumbres mas emblematicas del Macizo de Ubina.',17.80,1320,'Muy Alta','Senderismo',340,'https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=1400&auto=format&fit=crop','[{"lat":43.0321,"lng":-5.9688},{"lat":43.0202,"lng":-5.9535},{"lat":43.0086,"lng":-5.9374},{"lat":42.9968,"lng":-5.9209}]',1,1);

INSERT INTO achievements (title, description, criteria_points, criteria_routes, bonus_points, icon, is_active)
VALUES
  ('Primer Paso', 'Completa tu primera ruta.', 0, 1, 50, 'BOOT', 1),
  ('Explorador Astur', 'Completa 5 rutas distintas.', 0, 5, 150, 'COMPASS', 1),
  ('Pulmon Verde', 'Acumula 1000 puntos.', 1000, 0, 200, 'FOREST', 1),
  ('Leyenda de la Montana', 'Acumula 3000 puntos y 12 rutas.', 3000, 12, 500, 'MOUNTAIN', 1);

INSERT INTO challenges
  (title, description, target_type, target_value, reward_points, start_date, end_date, is_active, created_by)
VALUES
  ('Reto de Verano: 80 km','Suma 80 km en rutas oficiales durante el verano. Un objetivo realista para mantener constancia sin exigir salidas extremas.','distance_km',80.00,350,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 121 DAY),1,1),
  ('Desafio 6 Rutas','Completa 6 rutas distintas durante la temporada activa.','routes_count',6.00,300,DATE_SUB(CURDATE(), INTERVAL 10 DAY),DATE_ADD(CURDATE(), INTERVAL 35 DAY),1,1),
  ('Liga de Puntos 1500','Consigue 1500 puntos en el periodo del reto.','points',1500.00,400,DATE_SUB(CURDATE(), INTERVAL 4 DAY),DATE_ADD(CURDATE(), INTERVAL 26 DAY),1,1);

INSERT IGNORE INTO challenge_participants (challenge_id, user_id, progress_value, joined_at)
VALUES
  (1, 2, 18.00, NOW()),
  (2, 2, 2.00, NOW()),
  (3, 2, 350.00, NOW());

INSERT IGNORE INTO route_completions
  (user_id, route_id, completed_at, duration_min, points_obtained, notes, gpx_filename)
VALUES
  (2, 4, DATE_SUB(NOW(), INTERVAL 6 DAY), 115, 70, 'Ruta tranquila con buen tiempo.', NULL),
  (2, 5, DATE_SUB(NOW(), INTERVAL 2 DAY), 132, 65, 'Ideal para hacer fotos.', NULL),
  (2, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 164, 215, 'Subida con niebla al inicio.', NULL);

INSERT IGNORE INTO route_favorites (user_id, route_id, created_at)
VALUES
  (2, 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
  (2, 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (2, 7, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT IGNORE INTO comments (route_id, user_id, rating, comment_text, status, created_at, moderated_at, moderated_by)
VALUES
  (4, 2, 5, 'Perfecta para empezar, muy bien senalizada.', 'approved', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 1),
  (3, 2, 4, 'Ruta preciosa, algo concurrida en fin de semana.', 'approved', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 1);

/* ============================================================
   UEP SPORTS MANAGEMENT SYSTEM (All Modules)
   Database: uep_sports_management
   Engine: MySQL 8.x (InnoDB)
   Includes: ALL listed tables + >=5 sample rows per table
   ============================================================ */

DROP DATABASE IF EXISTS uep_sports_management;
CREATE DATABASE uep_sports_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE uep_sports_management;

SET FOREIGN_KEY_CHECKS = 0;

/* =========================
   1) CORE STRUCTURE TABLES
   ========================= */

CREATE TABLE tbl_school (
  school_id INT AUTO_INCREMENT PRIMARY KEY,
  school_name VARCHAR(200) NOT NULL,
  school_address TEXT,
  school_head VARCHAR(150),
  school_head_cp VARCHAR(30),
  school_head_email VARCHAR(150),
  school_sports_director VARCHAR(150),
  sports_dir_cp VARCHAR(30),
  sports_dir_email VARCHAR(150),
  school_reg VARCHAR(150),
  school_reg_cp VARCHAR(30),
  school_reg_email VARCHAR(150)
) ENGINE=InnoDB;

CREATE TABLE tbl_college (
  college_id INT AUTO_INCREMENT PRIMARY KEY,
  college_code VARCHAR(20) NOT NULL UNIQUE,
  college_name VARCHAR(200) NOT NULL,
  college_dean VARCHAR(150),
  description TEXT,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tbl_department (
  dept_id INT AUTO_INCREMENT PRIMARY KEY,
  dept_code VARCHAR(20) NOT NULL,
  dept_name VARCHAR(200) NOT NULL,
  college_id INT NOT NULL,
  dept_head VARCHAR(150),
  description TEXT,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_dept_college
    FOREIGN KEY (college_id) REFERENCES tbl_college(college_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tbl_course (
  course_id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(20) NOT NULL UNIQUE,
  course_name VARCHAR(200) NOT NULL,
  dept_id INT NOT NULL,
  course_type VARCHAR(50),
  num_years INT,
  description TEXT,
  CONSTRAINT fk_course_dept
    FOREIGN KEY (dept_id) REFERENCES tbl_department(dept_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;


/* =========================
   2) TEAM MANAGEMENT MODULE
   ========================= */

CREATE TABLE tbl_person (
  person_id INT AUTO_INCREMENT PRIMARY KEY,
  l_name VARCHAR(100) NOT NULL,
  f_name VARCHAR(100) NOT NULL,
  m_name VARCHAR(100),
  role_type ENUM('athlete','trainee','coach','trainor','sports_director','tournament_manager','umpire','scorer') NOT NULL,
  title VARCHAR(50),
  date_birth DATE,
  college_code VARCHAR(20),
  course VARCHAR(100),
  blood_type VARCHAR(5),
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_person_collegecode
    FOREIGN KEY (college_code) REFERENCES tbl_college(college_code)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tbl_vital_signs (
  vital_id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  height DECIMAL(6,2),
  weight DECIMAL(6,2),
  b_pressure VARCHAR(20),
  b_sugar VARCHAR(20),
  b_choles VARCHAR(20),
  date_taken DATE NOT NULL,
  CONSTRAINT fk_vital_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_ath_status (
  status_id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  scholarship_name VARCHAR(100) NOT NULL,
  semester VARCHAR(20) NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  CONSTRAINT fk_status_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_team (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  team_name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_team_school
    FOREIGN KEY (school_id) REFERENCES tbl_school(school_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tbl_sports (
  sports_id INT AUTO_INCREMENT PRIMARY KEY,
  sports_name VARCHAR(120) NOT NULL,
  team_individual ENUM('team','individual') NOT NULL,
  weight_class VARCHAR(50),
  men_women ENUM('men','women','mixed') NOT NULL,
  num_req_players INT DEFAULT 0,
  num_res_players INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

/* tournament table is referenced; created later but we can define junction now with FK after tbl_tournament exists.
   We'll create tbl_tournament first (Tournament Module), then create tables that reference it.
*/


/* Equipment */
CREATE TABLE tbl_team_equipment (
  equip_id INT AUTO_INCREMENT PRIMARY KEY,
  equip_name VARCHAR(150) NOT NULL,
  date_acquired DATE,
  description TEXT,
  is_functional TINYINT(1) DEFAULT 1,
  quantity INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE tbl_equip_inventory (
  inv_id INT AUTO_INCREMENT PRIMARY KEY,
  equip_id INT NOT NULL,
  trans_type ENUM('in','out') NOT NULL,
  transdate DATETIME NOT NULL,
  trans_by INT,
  rec_rel_by INT,
  equip_cond VARCHAR(100),
  CONSTRAINT fk_inv_equip
    FOREIGN KEY (equip_id) REFERENCES tbl_team_equipment(equip_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_inv_transby
    FOREIGN KEY (trans_by) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_inv_recrelby
    FOREIGN KEY (rec_rel_by) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


/* =========================
   3) TRAINING MANAGEMENT MODULE
   ========================= */

CREATE TABLE tbl_training_activity (
  activity_id INT AUTO_INCREMENT PRIMARY KEY,
  activity_name VARCHAR(150) NOT NULL,
  sports_id INT NOT NULL,
  duration VARCHAR(50),
  repetition VARCHAR(50),
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_activity_sports
    FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tbl_train_perf (
  perf_id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  activitity_id INT NOT NULL,  -- (kept your spelling: activitity_id)
  rating DECIMAL(5,2),
  date_eval DATE NOT NULL,
  team_id INT,
  CONSTRAINT fk_perf_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_perf_activity
    FOREIGN KEY (activitity_id) REFERENCES tbl_training_activity(activity_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_perf_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tbl_team_trainees (
  team_id INT NOT NULL,
  trainee_id INT NOT NULL,
  semester VARCHAR(20) NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  date_applied DATE NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  PRIMARY KEY (team_id, trainee_id, semester, school_year),
  CONSTRAINT fk_teamtrainees_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_teamtrainees_person
    FOREIGN KEY (trainee_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_train_sked (
  sked_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  sked_date DATE NOT NULL,
  sked_time TIME NOT NULL,
  sked_venue VARCHAR(150),
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_sked_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_train_attend (
  sked_id INT NOT NULL,
  person_id INT NOT NULL,
  is_present TINYINT(1) DEFAULT 0,
  PRIMARY KEY (sked_id, person_id),
  CONSTRAINT fk_attend_sked
    FOREIGN KEY (sked_id) REFERENCES tbl_train_sked(sked_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_attend_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


/* =========================
   4) TOURNAMENT MANAGEMENT MODULE
   ========================= */

CREATE TABLE tbl_tournament (
  tour_id INT AUTO_INCREMENT PRIMARY KEY,
  tour_name VARCHAR(200) NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  tour_date DATE NOT NULL,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tbl_game_venue (
  venue_id INT AUTO_INCREMENT PRIMARY KEY,
  venue_name VARCHAR(150) NOT NULL,
  venue_building VARCHAR(150),
  venue_room VARCHAR(80),
  venue_description TEXT,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tbl_match (
  match_id INT AUTO_INCREMENT PRIMARY KEY,
  game_no INT NOT NULL,
  sked_date DATE NOT NULL,
  sked_time TIME NOT NULL,
  venue_id INT NOT NULL,
  match_umpire_id INT,
  match_sports_manager_id INT,
  match_type ENUM('EL','QF','SF','F') NOT NULL,
  sports_id INT NOT NULL,
  sports_type ENUM('individual','team') NOT NULL,
  team_a_id INT,
  team_b_id INT,
  tour_id INT NOT NULL,
  winner_id INT,
  CONSTRAINT fk_match_venue
    FOREIGN KEY (venue_id) REFERENCES tbl_game_venue(venue_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_match_umpire
    FOREIGN KEY (match_umpire_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_match_manager
    FOREIGN KEY (match_sports_manager_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_match_sports
    FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_match_teama
    FOREIGN KEY (team_a_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_match_teamb
    FOREIGN KEY (team_b_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_match_tour
    FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_match_winner
    FOREIGN KEY (winner_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tbl_comp_score (
  competetors_score_id INT AUTO_INCREMENT PRIMARY KEY,
  tour_id INT NOT NULL,
  match_id INT NOT NULL,
  team_id INT,
  athlete_id INT NOT NULL,
  score DECIMAL(10,2) DEFAULT 0,
  rank_no INT,
  medal_type ENUM('Gold','Silver','Bronze','None') DEFAULT 'None',
  CONSTRAINT fk_score_tour
    FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_score_match
    FOREIGN KEY (match_id) REFERENCES tbl_match(match_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_score_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_score_athlete
    FOREIGN KEY (athlete_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_team_standing (
  tour_id INT NOT NULL,
  sports_id INT NOT NULL,
  team_id INT NOT NULL,
  no_games_played INT DEFAULT 0,
  no_win INT DEFAULT 0,
  no_loss INT DEFAULT 0,
  no_draw INT DEFAULT 0,
  no_gold INT DEFAULT 0,
  no_bronze INT DEFAULT 0,
  no_silver INT DEFAULT 0,
  athlete_id INT,
  PRIMARY KEY (tour_id, sports_id, team_id),
  CONSTRAINT fk_stand_tour
    FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_stand_sports
    FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_stand_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_stand_athlete
    FOREIGN KEY (athlete_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


/* =========================
   5) TABLES THAT DEPEND ON TOURNAMENT
   (Back to Team Management)
   ========================= */

CREATE TABLE tbl_sports_team (
  tour_id INT NOT NULL,
  team_id INT NOT NULL,
  sports_id INT NOT NULL,
  coach_id INT,
  asst_coach_id INT,
  trainor1_id INT,
  trainor2_id INT,
  trainor3_id INT,
  PRIMARY KEY (tour_id, team_id, sports_id),
  CONSTRAINT fk_spteam_tour
    FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_spteam_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_spteam_sports
    FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_spteam_coach
    FOREIGN KEY (coach_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_spteam_asstcoach
    FOREIGN KEY (asst_coach_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_spteam_trainor1
    FOREIGN KEY (trainor1_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_spteam_trainor2
    FOREIGN KEY (trainor2_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_spteam_trainor3
    FOREIGN KEY (trainor3_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tbl_team_athletes (
  team_ath_id INT AUTO_INCREMENT PRIMARY KEY,
  tour_id INT NOT NULL,
  team_id INT NOT NULL,
  sports_id INT NOT NULL,
  person_id INT NOT NULL,
  is_captain TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_ta_tour
    FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ta_team
    FOREIGN KEY (team_id) REFERENCES tbl_team(team_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ta_sports
    FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_ta_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


/* =========================
   6) USER MANAGEMENT MODULE
   ========================= */

CREATE TABLE tbl_users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  user_role ENUM('system administrator','trainor','trainee','coach','athlete/player','sports director','umpire','Tournament manager','Spectator','scorer') NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_users_person
    FOREIGN KEY (person_id) REFERENCES tbl_person(person_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  log_event TEXT NOT NULL,
  log_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  module_name VARCHAR(100) NOT NULL,
  CONSTRAINT fk_logs_user
    FOREIGN KEY (user_id) REFERENCES tbl_users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;


/* ============================================================
   SAMPLE DATA (>=5 rows per table)
   UEP Context
   ============================================================ */

/* --- tbl_school (5) --- */
INSERT INTO tbl_school
(school_id, school_name, school_address, school_head, school_head_cp, school_head_email,
 school_sports_director, sports_dir_cp, sports_dir_email, school_reg, school_reg_cp, school_reg_email)
VALUES
(1,'University of Eastern Philippines - Main Campus','Catarman, Northern Samar','Dr. Ma. Teresa A.','09170000001','president@uep.edu.ph',
 'Mr. Jose Ariel Geriane','09170000002','sports@uep.edu.ph','Mr. Allan Reyes','09170000003','registrar@uep.edu.ph'),
(2,'UEP Laoang Campus','Laoang, Northern Samar','Dr. Luis S.','09170000004','pres_laoang@uep.edu.ph',
 'Mr. Carlo Mendez','09170000005','sports_laoang@uep.edu.ph','Ms. Joy Lina','09170000006','reg_laoang@uep.edu.ph'),
(3,'UEP Catubig Campus','Catubig, Northern Samar','Dr. Anna C.','09170000007','pres_catubig@uep.edu.ph',
 'Mr. Paul Ortega','09170000008','sports_catubig@uep.edu.ph','Ms. Marie Tan','09170000009','reg_catubig@uep.edu.ph'),
(4,'UEP Extension A','Northern Samar','Dr. Ramon D.','09170000010','exta@uep.edu.ph',
 'Mr. Ken Lopez','09170000011','exta_sports@uep.edu.ph','Ms. Fe Luna','09170000012','exta_reg@uep.edu.ph'),
(5,'UEP Extension B','Northern Samar','Dr. Gina F.','09170000013','extb@uep.edu.ph',
 'Mr. Neil Castro','09170000014','extb_sports@uep.edu.ph','Ms. Anna Lim','09170000015','extb_reg@uep.edu.ph');

/* --- tbl_college (5) --- */
INSERT INTO tbl_college (college_id, college_code, college_name, college_dean, description, is_active) VALUES
(1,'CICS','College of Information and Computing Sciences','Dr. Jose R.','IT and Computing programs',1),
(2,'CAS','College of Arts and Sciences','Dr. Lina G.','Arts and Sciences',1),
(3,'CBA','College of Business Administration','Dr. Allan C.','Business programs',1),
(4,'COE','College of Education','Dr. Fe V.','Teacher education',1),
(5,'CAF','College of Agriculture and Fisheries','Dr. Mario S.','Agriculture and Fisheries',1);

/* --- tbl_department (5) --- */
INSERT INTO tbl_department (dept_id, dept_code, dept_name, college_id, dept_head, description, is_active) VALUES
(1,'IT','Information Technology',1,'Mr. Bryan C.','IT Department',1),
(2,'CS','Computer Science',1,'Ms. Ana D.','CS Department',1),
(3,'ENG','English',2,'Ms. Joy P.','English Department',1),
(4,'MATH','Mathematics',2,'Mr. Ryan L.','Math Department',1),
(5,'PE','Physical Education',4,'Mr. Carlo R.','PE Department',1);

/* --- tbl_course (5) --- */
INSERT INTO tbl_course (course_id, course_code, course_name, dept_id, course_type, num_years, description) VALUES
(1,'BSIT','Bachelor of Science in Information Technology',1,'Undergraduate',4,'UEP BSIT Program'),
(2,'BSCS','Bachelor of Science in Computer Science',2,'Undergraduate',4,'UEP BSCS Program'),
(3,'BAENG','Bachelor of Arts in English',3,'Undergraduate',4,'UEP BA English Program'),
(4,'BSMATH','Bachelor of Science in Mathematics',4,'Undergraduate',4,'UEP BS Math Program'),
(5,'BPE','Bachelor of Physical Education',5,'Undergraduate',4,'UEP BPE Program');

/* --- tbl_person (15+ for richer linking, still valid) --- */
INSERT INTO tbl_person
(person_id,l_name,f_name,m_name,role_type,title,date_birth,college_code,course,blood_type,is_active)
VALUES
(1,'Santos','Juan','D','athlete',NULL,'2002-05-10','CICS','BSIT','O+',1),
(2,'Reyes','Maria','L','athlete',NULL,'2001-04-22','CAS','BAENG','A+',1),
(3,'Cruz','Peter','M','coach','Coach','1985-09-15','COE',NULL,'B+',1),
(4,'Lopez','Anna','G','umpire',NULL,'1990-11-05','COE',NULL,'O-',1),
(5,'Garcia','Leo','P','sports_director','Director','1975-02-20','COE',NULL,'AB+',1),
(6,'Villanueva','Kyla','S','trainor','Trainor','1992-01-12','COE',NULL,'A-',1),
(7,'Dela Cruz','Mark','T','tournament_manager','Manager','1988-03-03','CBA',NULL,'B-',1),
(8,'Lim','Paolo','R','scorer',NULL,'1999-08-30','CICS','BSIT','O+',1),
(9,'Ortega','Nina','Q','trainee',NULL,'2003-02-17','CAF','BSIT','AB-',1),
(10,'Mendoza','Carlo','B','athlete',NULL,'2002-10-19','CICS','BSCS','A+',1),
(11,'Tan','Joy','C','athlete',NULL,'2001-12-25','CAS','BSMATH','B+',1),
(12,'Castro','Neil','E','coach','Coach','1982-06-07','COE',NULL,'O+',1),
(13,'Flores','Gina','H','trainor','Trainor','1991-04-14','COE',NULL,'A+',1),
(14,'Diaz','Ramon','J','umpire',NULL,'1989-09-09','COE',NULL,'B+',1),
(15,'Perez','Luna','K','trainee',NULL,'2003-07-07','CICS','BSIT','O-',1);

/* --- tbl_vital_signs (>=5) --- */
INSERT INTO tbl_vital_signs
(vital_id,person_id,height,weight,b_pressure,b_sugar,b_choles,date_taken)
VALUES
(1,1,170.00,65.00,'120/80','90','180','2025-01-10'),
(2,2,160.00,55.00,'110/70','85','170','2025-01-10'),
(3,10,172.00,68.00,'118/78','92','175','2025-01-11'),
(4,11,158.00,54.00,'112/72','88','168','2025-01-11'),
(5,9,165.00,57.00,'115/75','86','172','2025-01-12');

/* --- tbl_ath_status (>=5) --- */
INSERT INTO tbl_ath_status (status_id, person_id, scholarship_name, semester, school_year) VALUES
(1,1,'Varsity','1st Sem','2025-2026'),
(2,2,'Varsity','1st Sem','2025-2026'),
(3,10,'Varsity','1st Sem','2025-2026'),
(4,11,'Varsity','1st Sem','2025-2026'),
(5,9,'Trainee','1st Sem','2025-2026');

/* --- tbl_team (>=5) --- */
INSERT INTO tbl_team (team_id, school_id, team_name, is_active) VALUES
(1,1,'UEP Main - CICS Tigers',1),
(2,1,'UEP Main - CAS Falcons',1),
(3,2,'UEP Laoang - Sharks',1),
(4,3,'UEP Catubig - Eagles',1),
(5,1,'UEP Main - COE Panthers',1);

/* --- tbl_sports (>=5) --- */
INSERT INTO tbl_sports
(sports_id,sports_name,team_individual,weight_class,men_women,num_req_players,num_res_players,is_active)
VALUES
(1,'Volleyball','team',NULL,'mixed',6,6,1),
(2,'Basketball','team',NULL,'men',5,7,1),
(3,'Badminton','individual',NULL,'mixed',1,1,1),
(4,'Table Tennis','individual',NULL,'mixed',1,1,1),
(5,'Athletics','individual','Varies','mixed',1,1,1);

/* --- tbl_team_equipment (>=5) --- */
INSERT INTO tbl_team_equipment
(equip_id,equip_name,date_acquired,description,is_functional,quantity)
VALUES
(1,'Volleyball (Mikasa)','2025-01-05','Official volleyballs for training and tournaments',1,12),
(2,'Basketball (Molten)','2025-01-05','Official basketballs for games and practice',1,10),
(3,'Net Set - Volleyball','2025-01-06','Volleyball net with posts and ropes',1,3),
(4,'Whistles','2025-01-06','Referee whistles',1,20),
(5,'Scoreboard (Portable)','2025-01-07','Manual portable scoreboard',1,2);

/* --- tbl_equip_inventory (>=5) --- */
INSERT INTO tbl_equip_inventory
(inv_id,equip_id,trans_type,transdate,trans_by,rec_rel_by,equip_cond)
VALUES
(1,1,'in','2025-01-05 09:00:00',5,1,'New'),
(2,2,'in','2025-01-05 09:30:00',5,2,'New'),
(3,1,'out','2025-01-10 07:00:00',8,3,'Good'),
(4,3,'out','2025-01-10 07:10:00',8,12,'Good'),
(5,4,'out','2025-01-10 08:00:00',7,4,'Good');

/* --- tbl_training_activity (>=5) --- */
INSERT INTO tbl_training_activity
(activity_id,activity_name,sports_id,duration,repetition,is_active)
VALUES
(1,'Basic Passing Drill',1,'30 mins','3 sets',1),
(2,'Serving Accuracy',1,'25 mins','4 sets',1),
(3,'Layup Drill',2,'20 mins','5 sets',1),
(4,'Footwork and Agility',3,'20 mins','4 sets',1),
(5,'Sprint Intervals',5,'15 mins','6 sets',1);

/* --- tbl_train_perf (>=5) --- */
INSERT INTO tbl_train_perf
(perf_id,person_id,activitity_id,rating,date_eval,team_id)
VALUES
(1,1,1,4.20,'2025-01-15',1),
(2,2,2,4.10,'2025-01-15',2),
(3,10,3,4.50,'2025-01-15',1),
(4,11,4,4.00,'2025-01-16',2),
(5,9,5,3.90,'2025-01-16',5);

/* --- tbl_team_trainees (>=5) --- */
INSERT INTO tbl_team_trainees
(team_id,trainee_id,semester,school_year,date_applied,is_active)
VALUES
(1,9,'1st Sem','2025-2026','2025-01-05',1),
(2,15,'1st Sem','2025-2026','2025-01-06',1),
(3,9,'1st Sem','2025-2026','2025-01-07',1),
(4,15,'1st Sem','2025-2026','2025-01-07',1),
(5,9,'1st Sem','2025-2026','2025-01-08',1);

/* --- tbl_train_sked (>=5) --- */
INSERT INTO tbl_train_sked
(sked_id,team_id,sked_date,sked_time,sked_venue,is_active)
VALUES
(1,1,'2025-01-20','16:00:00','UEP Gymnasium',1),
(2,2,'2025-01-20','17:00:00','UEP Covered Court',1),
(3,3,'2025-01-21','16:00:00','Laoang Campus Court',1),
(4,4,'2025-01-21','17:00:00','Catubig Campus Court',1),
(5,5,'2025-01-22','16:30:00','UEP Gymnasium',1);

/* --- tbl_train_attend (>=5) --- */
INSERT INTO tbl_train_attend (sked_id, person_id, is_present) VALUES
(1,1,1),
(1,10,1),
(2,2,1),
(3,9,0),
(5,11,1);

/* --- tbl_tournament (>=5) --- */
INSERT INTO tbl_tournament (tour_id,tour_name,school_year,tour_date,is_active) VALUES
(1,'UEP Intramurals 2025','2025-2026','2025-02-10',1),
(2,'UEP Friendship Games','2025-2026','2025-03-05',1),
(3,'UEP Sports Festival','2025-2026','2025-04-12',1),
(4,'UEP Inter-Campus Meet','2025-2026','2025-05-20',1),
(5,'UEP Summer Tournament','2025-2026','2025-06-15',1);

/* --- tbl_game_venue (>=5) --- */
INSERT INTO tbl_game_venue
(venue_id,venue_name,venue_building,venue_room,venue_description,is_active)
VALUES
(1,'UEP Gymnasium','Main Campus','GYM','Main indoor venue for tournaments',1),
(2,'UEP Covered Court','Main Campus','CC-1','Covered court for team sports',1),
(3,'Laoang Campus Court','Laoang Campus','COURT','Outdoor court (Laoang)',1),
(4,'Catubig Campus Court','Catubig Campus','COURT','Outdoor court (Catubig)',1),
(5,'UEP Track Oval','Main Campus','OVAL','Track and field venue',1);

/* --- tbl_match (>=5) --- */
INSERT INTO tbl_match
(match_id,game_no,sked_date,sked_time,venue_id,match_umpire_id,match_sports_manager_id,match_type,
 sports_id,sports_type,team_a_id,team_b_id,tour_id,winner_id)
VALUES
(1,1,'2025-02-10','09:00:00',1,4,7,'EL',1,'team',1,2,1,1),
(2,2,'2025-02-10','11:00:00',2,14,7,'EL',2,'team',3,4,1,3),
(3,3,'2025-03-05','10:00:00',3,4,7,'QF',1,'team',1,5,2,1),
(4,4,'2025-04-12','14:00:00',4,14,7,'SF',1,'team',2,3,3,3),
(5,5,'2025-06-15','08:00:00',5,4,7,'F',5,'individual',NULL,NULL,5,NULL);

/* --- tbl_comp_score (>=5) --- */
INSERT INTO tbl_comp_score
(competetors_score_id,tour_id,match_id,team_id,athlete_id,score,rank_no,medal_type)
VALUES
(1,1,1,1,1,25,1,'Gold'),
(2,1,1,2,2,18,2,'Silver'),
(3,1,2,3,10,30,1,'Gold'),
(4,3,4,3,11,22,1,'Gold'),
(5,2,3,1,1,24,1,'Gold');

/* --- tbl_team_standing (>=5) --- */
INSERT INTO tbl_team_standing
(tour_id,sports_id,team_id,no_games_played,no_win,no_loss,no_draw,no_gold,no_bronze,no_silver,athlete_id)
VALUES
(1,1,1,3,3,0,0,1,0,0,1),
(1,1,2,3,2,1,0,0,0,1,2),
(1,2,3,2,2,0,0,1,0,0,10),
(3,1,3,2,2,0,0,1,0,0,11),
(2,1,5,1,0,1,0,0,1,0,9);

/* --- tbl_sports_team (>=5) --- */
INSERT INTO tbl_sports_team
(tour_id,team_id,sports_id,coach_id,asst_coach_id,trainor1_id,trainor2_id,trainor3_id)
VALUES
(1,1,1,3,12,6,13,NULL),
(1,2,1,12,3,13,6,NULL),
(1,3,2,12,3,6,NULL,NULL),
(2,5,1,3,12,6,13,NULL),
(3,3,1,12,3,13,6,NULL);

/* --- tbl_team_athletes (>=5) --- */
INSERT INTO tbl_team_athletes
(team_ath_id,tour_id,team_id,sports_id,person_id,is_captain,is_active)
VALUES
(1,1,1,1,1,1,1),
(2,1,2,1,2,1,1),
(3,1,1,2,10,0,1),
(4,1,3,2,11,1,1),
(5,2,5,1,9,0,1);

/* --- tbl_users (>=5) --- */
INSERT INTO tbl_users
(user_id,person_id,username,password,user_role,is_active)
VALUES
(1,5,'uep.director','password123','sports director',1),
(2,7,'uep.tourmanager','password123','Tournament manager',1),
(3,3,'uep.coach.cruz','password123','coach',1),
(4,4,'uep.umpire.lopez','password123','umpire',1),
(5,8,'uep.scorer.lim','password123','scorer',1);

/* --- tbl_logs (>=5) --- */
INSERT INTO tbl_logs
(log_id,user_id,log_event,log_date,module_name)
VALUES
(1,1,'Created new tournament record','2025-01-10 10:00:00','Tournament Management'),
(2,2,'Assigned teams to tournament','2025-01-10 11:00:00','Team Management'),
(3,3,'Created training schedule','2025-01-15 09:00:00','Training Management'),
(4,4,'Confirmed match officiating','2025-02-10 08:30:00','Tournament Management'),
(5,5,'Recorded match scores','2025-02-10 12:00:00','Tournament Management');

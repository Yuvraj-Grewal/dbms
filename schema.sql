DROP DATABASE IF EXISTS dbms_project;
CREATE DATABASE dbms_project;
USE dbms_project;

CREATE TABLE Drone (
  drone_id INT AUTO_INCREMENT PRIMARY KEY,
  callsign VARCHAR(20) NOT NULL UNIQUE,
  model VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL CHECK (status IN ('Ready','Maintenance','In Flight','Grounded')),
  battery_percent INT NOT NULL CHECK (battery_percent BETWEEN 0 AND 100)
);
CREATE TABLE Component (
  component_id INT AUTO_INCREMENT PRIMARY KEY,
  component_name VARCHAR(50) NOT NULL,
  component_type VARCHAR(30) NOT NULL,
  serial_no VARCHAR(30) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL CHECK (status IN ('Working','Faulty','Retired'))
);
CREATE TABLE Drone_Component (
  drone_id INT NOT NULL,
  component_id INT NOT NULL,
  installation_date DATE NOT NULL,
  removal_date DATE NULL,
  PRIMARY KEY (drone_id, component_id, installation_date),
  FOREIGN KEY (drone_id) REFERENCES Drone(drone_id),
  FOREIGN KEY (component_id) REFERENCES Component(component_id),
  CHECK (removal_date IS NULL OR removal_date >= installation_date)
);
CREATE TABLE Team_Member (
  member_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  role VARCHAR(30) NOT NULL CHECK (role IN ('Pilot','Engineer','Rescue Officer','Safety Officer')),
  certification VARCHAR(50)
);
CREATE TABLE Building (
  building_id INT AUTO_INCREMENT PRIMARY KEY,
  building_name VARCHAR(50) NOT NULL,
  location VARCHAR(100) NOT NULL,
  condition_status VARCHAR(20) NOT NULL CHECK (condition_status IN ('Stable','Damaged','Critical'))
);
CREATE TABLE Room (
  building_id INT NOT NULL,
  room_no INT NOT NULL,
  floor_no INT NOT NULL,
  area_m2 DECIMAL(8,2) NOT NULL,
  hazard_level VARCHAR(20) NOT NULL CHECK (hazard_level IN ('Low','Medium','High','Critical')),
  PRIMARY KEY (building_id, room_no),
  FOREIGN KEY (building_id) REFERENCES Building(building_id) ON DELETE CASCADE
);
CREATE TABLE Mission (
  mission_id INT AUTO_INCREMENT PRIMARY KEY,
  building_id INT NOT NULL,
  objective VARCHAR(100) NOT NULL,
  start_time DATETIME NOT NULL,
  status VARCHAR(20) NOT NULL CHECK (status IN ('Planned','In Progress','Completed','Aborted')),
  FOREIGN KEY (building_id) REFERENCES Building(building_id)
);
CREATE TABLE Waypoint (
  mission_id INT NOT NULL,
  waypoint_no INT NOT NULL,
  x_coordinate DECIMAL(8,2) NOT NULL,
  y_coordinate DECIMAL(8,2) NOT NULL,
  action VARCHAR(30) NOT NULL,
  PRIMARY KEY (mission_id, waypoint_no),
  FOREIGN KEY (mission_id) REFERENCES Mission(mission_id) ON DELETE CASCADE
);
CREATE TABLE Flight (
  flight_id INT AUTO_INCREMENT PRIMARY KEY,
  mission_id INT NOT NULL,
  drone_id INT NOT NULL,
  pilot_id INT NOT NULL,
  takeoff_time DATETIME NOT NULL,
  landing_time DATETIME NULL,
  result VARCHAR(20) NOT NULL CHECK (result IN ('In Progress','Successful','Partial','Failed','Aborted')),
  FOREIGN KEY (mission_id) REFERENCES Mission(mission_id),
  FOREIGN KEY (drone_id) REFERENCES Drone(drone_id),
  FOREIGN KEY (pilot_id) REFERENCES Team_Member(member_id),
  CHECK (landing_time IS NULL OR landing_time >= takeoff_time)
);
CREATE TABLE Incident_Event (
  flight_id INT NOT NULL,
  event_no INT NOT NULL,
  incident_type VARCHAR(40) NOT NULL,
  severity VARCHAR(20) NOT NULL CHECK (severity IN ('Low','Medium','High','Critical')),
  occurred_at DATETIME NOT NULL,
  description VARCHAR(200),
  PRIMARY KEY (flight_id, event_no),
  FOREIGN KEY (flight_id) REFERENCES Flight(flight_id) ON DELETE CASCADE
);

CREATE TABLE User_Account (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL CHECK (role IN ('admin','pilot','safety','coord')),
  member_id INT NULL,
  FOREIGN KEY (member_id) REFERENCES Team_Member(member_id) ON DELETE SET NULL
);

-- ---------- sample data (from the report) ----------
INSERT INTO Drone VALUES (101,'NIDAR-01','DJI Matrice 300','Ready',92),(102,'NIDAR-02','DJI Mavic 3','Ready',84),
 (103,'NIDAR-03','Autel EVO II','Maintenance',45),(104,'NIDAR-04','DJI Matrice 350','Ready',88);
INSERT INTO Component VALUES (201,'Thermal Camera','Camera','TC-1001','Working'),(202,'GPS Module','Navigation','GPS-2001','Working'),
 (203,'Obstacle Sensor','Sensor','OS-3001','Working'),(204,'Night Vision Camera','Camera','NC-4001','Working'),
 (205,'Battery Pack','Power','BP-5001','Working'),(206,'Thermal Camera','Camera','TC-1002','Working');
INSERT INTO Drone_Component VALUES
 (103,201,'2026-08-15','2026-08-31'),(103,203,'2026-08-15','2026-08-31'),
 (101,201,'2026-09-01',NULL),(101,202,'2026-09-01','2026-09-03'),(101,203,'2026-09-02','2026-09-05'),
 (102,202,'2026-09-03','2026-09-05'),(102,204,'2026-09-03',NULL),(102,205,'2026-09-03',NULL),
 (104,202,'2026-09-05',NULL),(104,203,'2026-09-05',NULL),(104,206,'2026-09-05',NULL);
INSERT INTO Team_Member VALUES (301,'Arjun Sharma','Pilot','DGCA Certified'),(302,'Rohan Mehta','Pilot','DGCA Certified'),
 (303,'Neha Kapoor','Engineer','Drone Maintenance'),(304,'Aman Verma','Rescue Officer','Search and Rescue');
INSERT INTO Building VALUES (401,'Block A','Sector 12','Damaged'),(402,'Block B','Sector 15','Stable'),(403,'Warehouse C','Industrial Area','Critical');
INSERT INTO Room VALUES (401,101,1,35.50,'Medium'),(401,102,1,28.00,'High'),(401,201,2,42.75,'High'),
 (402,101,1,30.00,'Low'),(402,102,1,32.50,'Medium'),(402,201,2,45.50,'Medium'),(403,101,1,80.00,'Critical'),(403,102,1,65.00,'High');
INSERT INTO Mission VALUES (501,401,'Search damaged rooms','2026-10-01 09:00:00','Completed'),
 (502,402,'Inspect building structure','2026-10-02 11:00:00','Completed'),(503,403,'Search for trapped persons','2026-10-03 14:30:00','In Progress');
INSERT INTO Waypoint VALUES (501,1,10.50,20.30,'Takeoff'),(501,2,15.20,25.40,'Scan'),(501,3,20.10,30.50,'Return'),
 (502,1,12.00,18.50,'Takeoff'),(502,2,22.50,28.00,'Inspect'),(502,3,30.00,35.50,'Return'),
 (503,1,8.50,12.00,'Takeoff'),(503,2,18.00,24.50,'Search'),(503,3,27.50,32.00,'Search');
INSERT INTO Flight VALUES (601,501,101,301,'2026-10-01 09:05:00','2026-10-01 09:45:00','Successful'),
 (602,501,102,302,'2026-10-01 10:00:00','2026-10-01 10:35:00','Successful'),
 (603,502,104,301,'2026-10-02 11:05:00','2026-10-02 11:50:00','Successful'),
 (604,503,101,302,'2026-10-03 14:35:00',NULL,'In Progress');
INSERT INTO Incident_Event VALUES (601,1,'Low Battery','Medium','2026-10-01 09:38:00','Battery dropped below 25 percent'),
 (602,1,'Signal Loss','High','2026-10-01 10:20:00','Temporary communication loss'),
 (602,2,'Obstacle Detected','Low','2026-10-01 10:27:00','Obstacle detected near building'),
 (603,1,'Obstacle Detected','Low','2026-10-02 11:30:00','Obstacle detected near building'),
 (604,1,'Low Visibility','Medium','2026-10-03 15:00:00','Dust reduced camera visibility');

-- ---------- view: airworthiness (Safety Officer requirement) ----------
CREATE VIEW v_fleet_health AS
SELECT x.*, (x.status='Ready' AND x.battery_percent>=30 AND x.bad_parts=0) AS airworthy
FROM (SELECT d.*, (SELECT COUNT(*) FROM Drone_Component dc JOIN Component c ON c.component_id=dc.component_id
        WHERE dc.drone_id=d.drone_id AND dc.removal_date IS NULL AND c.status<>'Working') AS bad_parts
      FROM Drone d) x;

-- ---------- triggers ----------
DELIMITER $$
CREATE TRIGGER trg_flight_guard BEFORE INSERT ON Flight FOR EACH ROW
BEGIN
  IF IFNULL((SELECT airworthy FROM v_fleet_health WHERE drone_id=NEW.drone_id),0)=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Blocked: drone is not airworthy (status, battery below 30% or faulty component)';
  END IF;
  IF IFNULL((SELECT role FROM Team_Member WHERE member_id=NEW.pilot_id),'')<>'Pilot' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Blocked: selected team member is not a Pilot';
  END IF;
END$$
CREATE TRIGGER trg_incident_critical AFTER INSERT ON Incident_Event FOR EACH ROW
BEGIN
  IF NEW.severity='Critical' THEN
    UPDATE Drone SET status='Maintenance' WHERE drone_id=(SELECT drone_id FROM Flight WHERE flight_id=NEW.flight_id);
  END IF;
END$$
CREATE TRIGGER trg_flight_landed AFTER UPDATE ON Flight FOR EACH ROW
BEGIN
  IF OLD.landing_time IS NULL AND NEW.landing_time IS NOT NULL THEN
    UPDATE Mission SET status='Completed' WHERE mission_id=NEW.mission_id;
    UPDATE Drone SET status='Ready' WHERE drone_id=NEW.drone_id AND status='In Flight';
  END IF;
END$$
CREATE PROCEDURE sp_assign_flight(IN m INT, IN d INT, IN p INT)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  INSERT INTO Flight(mission_id,drone_id,pilot_id,takeoff_time,result) VALUES (m,d,p,NOW(),'In Progress');
  UPDATE Mission SET status='In Progress' WHERE mission_id=m;
  UPDATE Drone SET status='In Flight' WHERE drone_id=d;
  COMMIT;
END$$
DELIMITER ;

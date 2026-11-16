<?php
$pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'localhost').';dbname=dbms_project;charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function q($s,$p=[]){global $pdo;$st=$pdo->prepare($s);$st->execute($p);return $st->fetchAll();}
function e($s){return htmlspecialchars((string)$s);}
function opts($s){$o='';foreach(q($s) as $r){$v=array_values($r);$o.='<option value="'.e($v[0]).'">'.e($v[1]).'</option>';}return $o;}
function sel($n,$s){return '<select name="'.$n.'">'.opts($s).'</select>';}
function f($a,$h,$b,$c=''){return '<form method="post"'.($c?' onsubmit="return confirm(\''.$c.'\')"':'').'><input type="hidden" name="a" value="'.$a.'">'.$h.'<button>'.$b.'</button></form>';}
function tbl($rows){
 if(!$rows)return '<p class="m">No rows.</p>';
 $h='<div class="w"><table><tr>';foreach(array_keys($rows[0]) as $k)$h.='<th>'.e($k).'</th>';$h.='</tr>';
 foreach($rows as $r){$h.='<tr>';foreach($r as $k=>$v){
  if($k=='battery_percent')$c='<div class="bar"><i style="width:'.(int)$v.'%" class="'.($v<30?'lo':'ok').'"></i></div>'.(int)$v.'%';
  elseif($k=='airworthy')$c=$v?'<span class="pl p-low">AIRWORTHY</span>':'<span class="pl p-critical">GROUNDED</span>';
  elseif(in_array($k,['status','severity','hazard_level','result','condition_status']))$c='<span class="pl p-'.strtolower(preg_replace('/\W/','',(string)$v)).'">'.e($v).'</span>';
  else $c=e($v);
  $h.='<td>'.$c.'</td>';}$h.='</tr>';}
 return $h.'</table></div>';}
function show($t,$s,$p=[]){echo '<div class="card"><h3>'.e($t).'</h3>'.tbl(q($s,$p)).'<details><summary>SQL used</summary><pre>'.e($s).'</pre></details></div>';}

session_start();
if(isset($_GET['out'])){session_destroy();header('Location: ?');exit;}
$lerr='';
if(!q("SELECT 1 FROM User_Account LIMIT 1"))foreach([['admin','admin',NULL],['pilot1','pilot',301],['pilot2','pilot',302],['safety','safety',303],['coord','coord',304]] as $s)q("INSERT INTO User_Account(username,password_hash,role,member_id) VALUES (?,?,?,?)",[$s[0],password_hash('demo123',PASSWORD_DEFAULT),$s[1],$s[2]]);
if(($_POST['a']??'')=='login'){$u=q("SELECT * FROM User_Account WHERE username=?",[$_POST['u']])[0]??null;if($u&&password_verify($_POST['pw'],$u['password_hash'])){$_SESSION['u']=$u;header('Location: ?');exit;}$lerr='Invalid username or password.';}
$msg=$err='';
if($U=$_SESSION['u']??null)try{switch($_POST['a']??''){
 case 'dr':q("INSERT INTO Drone(callsign,model,status,battery_percent) VALUES (?,?,'Ready',?)",[$_POST['cs'],$_POST['mo'],$_POST['bt']]);$msg='Drone added.';break;
 case 'bat':q("UPDATE Drone SET battery_percent=? WHERE drone_id=?",[$_POST['bt'],$_POST['d']]);$msg='Battery updated.';break;
 case 'dst':q("UPDATE Drone SET status=? WHERE drone_id=?",[$_POST['s'],$_POST['d']]);$msg='Drone status updated.';break;
 case 'tm':q("INSERT INTO Team_Member(name,role,certification) VALUES (?,?,?)",[$_POST['n'],$_POST['r'],$_POST['ce']]);$msg='Team member added.';break;
 case 'bl':q("INSERT INTO Building(building_name,location,condition_status) VALUES (?,?,?)",[$_POST['bn'],$_POST['lo'],$_POST['cd']]);$msg='Building added.';break;
 case 'rm':q("INSERT INTO Room VALUES (?,?,?,?,?)",[$_POST['b'],$_POST['rn'],$_POST['fn'],$_POST['ar'],$_POST['hz']]);$msg='Room added (identified by building + room number).';break;
 case 'cp':q("INSERT INTO Component(component_name,component_type,serial_no,status) VALUES (?,?,?,'Working')",[$_POST['cn'],$_POST['ct'],$_POST['sn']]);$msg='Component added.';break;
 case 'cst':q("UPDATE Component SET status=? WHERE component_id=?",[$_POST['s'],$_POST['c']]);$msg='Component status updated; fleet health recalculated.';break;
 case 'del':$k=['Drone'=>'drone_id','Team_Member'=>'member_id','Component'=>'component_id'][$_POST['t']]??die();q("DELETE FROM {$_POST['t']} WHERE $k=?",[$_POST['id']]);$msg='Record deleted.';break;
 case 'mission':q("INSERT INTO Mission(building_id,objective,start_time,status) VALUES (?,?,?, 'Planned')",[$_POST['b'],$_POST['o'],str_replace('T',' ',$_POST['t'])]);$msg='Mission created.';break;
 case 'wp':$n=q("SELECT COALESCE(MAX(waypoint_no),0)+1 n FROM Waypoint WHERE mission_id=?",[$_POST['m']])[0]['n'];
  q("INSERT INTO Waypoint VALUES (?,?,?,?,?)",[$_POST['m'],$n,$_POST['x'],$_POST['y'],$_POST['ac']]);$msg="Waypoint #$n added.";break;
 case 'delm':q("DELETE FROM Mission WHERE mission_id=?",[$_POST['m']]);$msg='Mission deleted; its waypoints were removed by ON DELETE CASCADE.';break;
 case 'flight':q("CALL sp_assign_flight(?,?,?)",[$_POST['m'],$_POST['d'],$_POST['p']]);$msg='Flight started.';break;
 case 'land':q("UPDATE Flight SET landing_time=NOW(), result=? WHERE flight_id=? AND landing_time IS NULL",[$_POST['r'],$_POST['fl']]);$msg='Flight landed; trigger marked its mission Completed.';break;
 case 'inc':$n=q("SELECT COALESCE(MAX(event_no),0)+1 n FROM Incident_Event WHERE flight_id=?",[$_POST['fl']])[0]['n'];
  q("INSERT INTO Incident_Event VALUES (?,?,?,?,NOW(),?)",[$_POST['fl'],$n,$_POST['ty'],$_POST['sv'],$_POST['de']]);$msg="Incident #$n logged (Critical severity grounds the drone via trigger).";break;
 case 'inst':q("INSERT INTO Drone_Component(drone_id,component_id,installation_date) VALUES (?,?,CURDATE())",[$_POST['d'],$_POST['c']]);$msg='Component installed.';break;
 case 'rem':q("UPDATE Drone_Component SET removal_date=CURDATE() WHERE drone_id=? AND component_id=? AND removal_date IS NULL",[$_POST['d'],$_POST['c']]);$msg='Component removed.';break;
 case 'delb':q("DELETE FROM Building WHERE building_id=?",[$_POST['b']]);$msg='Building deleted; its rooms were removed by ON DELETE CASCADE.';break;
}}catch(PDOException $x){$err=$x->errorInfo[2]??$x->getMessage();}

$R=['admin'=>['Administrator',['dash','manage','missions','flights','fleet','incidents','parts','buildings','reports']],'pilot'=>['Pilot',['dash','missions','flights','reports']],'safety'=>['Safety Officer',['dash','fleet','incidents','parts','reports']],'coord'=>['Rescue Coordinator',['dash','buildings','reports']]];
$L=['manage'=>'Manage Data','dash'=>'Dashboard','missions'=>'Mission Planner','flights'=>'Flights','fleet'=>'Fleet Health','incidents'=>'Incidents','parts'=>'Components','buildings'=>'Buildings & Rooms','reports'=>'Reports'];
$role=$U['role']??'pilot';
$p=in_array($_GET['p']??'dash',$R[$role][1])?($_GET['p']??'dash'):'dash';
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Drone Search &amp; Rescue DBMS</title>
<style>
:root{--bg:#0f1720;--c:#182433;--t:#e6edf3;--m:#8fa3b8;--a:#38bdf8}
*{box-sizing:border-box}body{margin:0;font:14px system-ui,sans-serif;background:var(--bg);color:var(--t)}
header{padding:14px 24px;background:var(--c);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between}
h1{font-size:18px;margin:0}h1 span{color:var(--a)}nav a{color:var(--m);text-decoration:none;padding:6px 12px;border-radius:6px;margin-right:4px}
nav a.on,nav a:hover{background:var(--bg);color:var(--a)}main{padding:20px 24px;max-width:1150px;margin:auto}
.card{background:var(--c);border-radius:10px;padding:16px;margin-bottom:16px}h3{margin:0 0 10px;font-size:15px}
.w{overflow-x:auto}table{border-collapse:collapse;width:100%}th,td{padding:7px 10px;text-align:left;border-bottom:1px solid #26364a;white-space:nowrap}th{color:var(--m);font-weight:500;font-size:12px;text-transform:uppercase}
.pl{padding:2px 9px;border-radius:99px;font-size:12px;background:#334155}.p-critical,.p-damaged,.p-maintenance,.p-failed{background:#7f1d1d}.p-high{background:#9a3412}
.p-medium,.p-inprogress,.p-planned{background:#854d0e}.p-low,.p-stable,.p-ready,.p-working,.p-successful,.p-completed{background:#166534}
.bar{display:inline-block;width:80px;height:8px;background:#26364a;border-radius:4px;margin-right:6px}.bar i{display:block;height:8px;border-radius:4px}.ok{background:#22c55e}.lo{background:#ef4444}
form{display:inline-flex;flex-wrap:wrap;gap:8px;margin:0 10px 10px 0;align-items:center}input,select{background:var(--bg);color:var(--t);border:1px solid #2d4057;border-radius:6px;padding:7px}
button{background:var(--a);color:#06202e;border:0;border-radius:6px;padding:8px 14px;font-weight:600;cursor:pointer}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}.k{background:var(--c);border-radius:10px;padding:16px}.k b{display:block;font-size:28px;color:var(--a)}
.m{color:var(--m)}.g2{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}details{margin-top:8px;color:var(--m)}pre{background:var(--bg);padding:10px;border-radius:6px;overflow-x:auto;color:#a5f3fc}
.ms{padding:10px 14px;border-radius:8px;margin-bottom:14px;background:#14532d}.er{background:#7f1d1d}
</style></head><body>
<header><h1>🚁 <span>Drone Search &amp; Rescue</span> Database</h1>
<nav><?php if($U)echo '<span class="m">'.e($U['username']).' · '.$R[$role][0].'</span> <a href="?out=1">Logout</a>';?></nav></header>
<main><?php if(!$U){echo '<div class="card" style="max-width:380px;margin:60px auto"><h3>Sign in</h3>'.($lerr?'<div class="ms er">'.e($lerr).'</div>':'').f('login','<input name="u" placeholder="Username" required><input name="pw" type="password" placeholder="Password" required>','Sign in').'<p class="m">Demo accounts (password demo123): admin, pilot1, pilot2, safety, coord</p></div></main></body></html>';exit;}?><nav style="margin-bottom:16px"><?php foreach($R[$role][1] as $k)echo '<a href="?role='.$role.'&p='.$k.'"'.($k==$p?' class="on"':'').'>'.$L[$k].'</a>';?></nav>
<?php if($msg)echo '<div class="ms">'.e($msg).'</div>';if($err)echo '<div class="ms er">Database rejected this: '.e($err).'</div>';

if($p=='dash'){
 $s=q("SELECT (SELECT COUNT(*) FROM Drone) drones,(SELECT COUNT(*) FROM v_fleet_health WHERE airworthy=1) airworthy,(SELECT COUNT(*) FROM Mission WHERE status='In Progress') active_missions,(SELECT COUNT(*) FROM Flight) flights,(SELECT COUNT(*) FROM Incident_Event) incidents")[0];
 echo '<div class="g">';foreach($s as $k=>$v)echo '<div class="k"><b>'.e($v).'</b>'.e(str_replace('_',' ',$k)).'</div>';echo '</div>';
 show('Drones needing attention (not airworthy)',"SELECT callsign,status,battery_percent,bad_parts AS faulty_parts,airworthy FROM v_fleet_health WHERE airworthy=0");
 show('Missions in progress',"SELECT m.mission_id,m.objective,b.building_name,b.condition_status FROM Mission m JOIN Building b ON b.building_id=m.building_id WHERE m.status='In Progress'");
 $a=q("SELECT d.callsign l,COUNT(f.flight_id) n FROM Drone d LEFT JOIN Flight f ON f.drone_id=d.drone_id GROUP BY d.drone_id,d.callsign");
 $b=q("SELECT severity l,COUNT(*) n FROM Incident_Event GROUP BY severity");
 echo '<div class="g2"><div class="card"><h3>Flights per drone</h3><canvas id="c1"></canvas></div><div class="card"><h3>Incidents by severity</h3><canvas id="c2"></canvas></div></div><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script><script>Chart.defaults.color="#8fa3b8";new Chart(c1,{type:"bar",data:{labels:'.json_encode(array_column($a,'l')).',datasets:[{data:'.json_encode(array_map('intval',array_column($a,'n'))).',backgroundColor:"#38bdf8"}]},options:{plugins:{legend:{display:false}}}});new Chart(c2,{type:"doughnut",data:{labels:'.json_encode(array_column($b,'l')).',datasets:[{data:'.json_encode(array_map('intval',array_column($b,'n'))).',backgroundColor:["#ef4444","#f97316","#eab308","#22c55e"]}]}});</script>';
}
if($p=='missions'){
 show('Missions',"SELECT m.mission_id,b.building_name,m.objective,m.start_time,m.status,(SELECT COUNT(*) FROM Waypoint w WHERE w.mission_id=m.mission_id) AS waypoints FROM Mission m JOIN Building b ON b.building_id=m.building_id ORDER BY m.mission_id");
 echo '<div class="card"><h3>Plan a mission</h3>'.f('mission',sel('b',"SELECT building_id,building_name FROM Building").'<input name="o" placeholder="Objective" required><input type="datetime-local" name="t" required>','Create mission')
  .f('wp',sel('m',"SELECT mission_id,CONCAT('Mission ',mission_id,' - ',objective) FROM Mission").'<input name="x" type="number" step="0.01" placeholder="x" required style="width:80px"><input name="y" type="number" step="0.01" placeholder="y" required style="width:80px"><input name="ac" placeholder="Action (Scan)" required>','Add waypoint')
  .f('delm',sel('m',"SELECT mission_id,CONCAT('Mission ',mission_id,' - ',objective) FROM Mission"),'Delete mission (cascade)','Delete this mission and all its waypoints?').'</div>';
 show('Waypoints (ordered, weak entity of Mission)',"SELECT mission_id,waypoint_no,x_coordinate,y_coordinate,action FROM Waypoint ORDER BY mission_id,waypoint_no");
}
if($p=='flights'){
 echo '<div class="card"><h3>Assign a flight (trigger validates drone and pilot)</h3>'.f('flight',sel('m',"SELECT mission_id,CONCAT('Mission ',mission_id,' - ',objective) FROM Mission").sel('d',"SELECT drone_id,CONCAT(callsign,' - ',status,', ',battery_percent,'%') FROM Drone").sel('p',"SELECT member_id,CONCAT(name,' (',role,')') FROM Team_Member"),'Start flight')
  .f('land',sel('fl',"SELECT flight_id,CONCAT('Flight ',flight_id) FROM Flight WHERE landing_time IS NULL").'<select name="r"><option>Successful</option><option>Partial</option><option>Failed</option><option>Aborted</option></select>','Land &amp; set result').'</div>';
 show('Flights',"SELECT f.flight_id,m.objective,d.callsign,t.name AS pilot,f.takeoff_time,f.landing_time,f.result FROM Flight f JOIN Mission m ON m.mission_id=f.mission_id JOIN Drone d ON d.drone_id=f.drone_id JOIN Team_Member t ON t.member_id=f.pilot_id ORDER BY f.flight_id DESC");
}
if($p=='fleet'){
 $DR="SELECT drone_id,callsign FROM Drone";
 echo '<div class="card"><h3>Update battery / status</h3>'.f('bat',sel('d',$DR).'<input name="bt" type="number" min="0" max="100" placeholder="Battery %" required>','Update battery').f('dst',sel('d',$DR).'<select name="s"><option>Ready</option><option>Maintenance</option><option>Grounded</option></select>','Set status').'</div>';
 show('Fleet health (view v_fleet_health)',"SELECT drone_id,callsign,model,status,battery_percent,bad_parts AS faulty_parts,airworthy FROM v_fleet_health");
 show('Installed component status per drone',"SELECT d.callsign,c.component_name,c.serial_no,c.status FROM Drone_Component dc JOIN Drone d ON d.drone_id=dc.drone_id JOIN Component c ON c.component_id=dc.component_id WHERE dc.removal_date IS NULL ORDER BY d.callsign");
}
if($p=='incidents'){
 echo '<div class="card"><h3>Log an incident</h3>'.f('inc',sel('fl',"SELECT flight_id,CONCAT('Flight ',flight_id) FROM Flight").'<input name="ty" placeholder="Type (Crash, Near-miss)" required><select name="sv"><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select><input name="de" placeholder="Description">','Log incident').'</div>';
 show('Incidents with drone and pilot',"SELECT i.flight_id,i.event_no,d.callsign,t.name AS pilot,i.incident_type,i.severity,i.occurred_at,i.description FROM Incident_Event i JOIN Flight f ON f.flight_id=i.flight_id JOIN Drone d ON d.drone_id=f.drone_id JOIN Team_Member t ON t.member_id=f.pilot_id ORDER BY i.occurred_at DESC");
 show('Incidents by severity and type',"SELECT severity,incident_type,COUNT(*) AS incidents FROM Incident_Event GROUP BY severity,incident_type ORDER BY FIELD(severity,'Critical','High','Medium','Low')");
}
if($p=='parts'){
 echo '<div class="card"><h3>Install / remove a component (M:N Drone-Component)</h3>'.f('inst',sel('d',"SELECT drone_id,callsign FROM Drone").sel('c',"SELECT component_id,CONCAT(component_name,' ',serial_no) FROM Component"),'Install today')
  .f('rem',sel('d',"SELECT drone_id,callsign FROM Drone").sel('c',"SELECT component_id,CONCAT(component_name,' ',serial_no) FROM Component"),'Remove today').f('cst',sel('c',"SELECT component_id,CONCAT(component_name,' ',serial_no,' (',status,')') FROM Component").'<select name="s"><option>Working</option><option>Faulty</option><option>Retired</option></select>','Set component status').'</div>';
 show('Installation history (current = no removal date)',"SELECT c.serial_no,c.component_name,d.callsign,dc.installation_date,dc.removal_date FROM Drone_Component dc JOIN Component c ON c.component_id=dc.component_id JOIN Drone d ON d.drone_id=dc.drone_id ORDER BY c.serial_no,dc.installation_date");
}
if($p=='buildings'){
 show('Building condition',"SELECT building_id,building_name,location,condition_status FROM Building");
 show('Rooms ordered by hazard level',"SELECT b.building_name,r.room_no,r.floor_no,r.area_m2,r.hazard_level FROM Room r JOIN Building b ON b.building_id=r.building_id ORDER BY FIELD(r.hazard_level,'Critical','High','Medium','Low'),b.building_name,r.room_no");
 show('Missions and flights carried out per building',"SELECT b.building_name,m.mission_id,m.objective,m.status,f.flight_id,d.callsign,t.name AS pilot,f.result FROM Building b JOIN Mission m ON m.building_id=b.building_id LEFT JOIN Flight f ON f.mission_id=m.mission_id LEFT JOIN Drone d ON d.drone_id=f.drone_id LEFT JOIN Team_Member t ON t.member_id=f.pilot_id ORDER BY b.building_name,m.mission_id,f.flight_id");
 echo '<div class="card"><h3>Delete a building</h3>'.f('delb',sel('b',"SELECT building_id,building_name FROM Building"),'Delete building (cascade rooms)','Delete this building and all its rooms?').'<p class="m">Buildings that still have missions are protected by the foreign key and will be rejected.</p></div>';
}
if($p=='manage'){
 $del=fn($t,$s)=>f('del','<input type="hidden" name="t" value="'.$t.'">'.sel('id',$s),'Delete','Delete this record?');
 echo '<div class="card"><h3>Drones</h3>'.f('dr','<input name="cs" placeholder="Callsign" required><input name="mo" placeholder="Model" required><input name="bt" type="number" min="0" max="100" placeholder="Battery %" required>','Add drone').$del('Drone',"SELECT drone_id,callsign FROM Drone").'</div>';
 show('Drones',"SELECT * FROM Drone");
 echo '<div class="card"><h3>Team members</h3>'.f('tm','<input name="n" placeholder="Name" required><select name="r"><option>Pilot</option><option>Engineer</option><option>Rescue Officer</option><option>Safety Officer</option></select><input name="ce" placeholder="Certification">','Add member').$del('Team_Member',"SELECT member_id,name FROM Team_Member").'</div>';
 show('Team members',"SELECT * FROM Team_Member");
 echo '<div class="card"><h3>Components</h3>'.f('cp','<input name="cn" placeholder="Name" required><input name="ct" placeholder="Type" required><input name="sn" placeholder="Serial no" required>','Add component').$del('Component',"SELECT component_id,CONCAT(component_name,' ',serial_no) FROM Component").'</div>';
 show('Components',"SELECT * FROM Component");
 echo '<div class="card"><h3>Buildings and rooms</h3>'.f('bl','<input name="bn" placeholder="Building name" required><input name="lo" placeholder="Location" required><select name="cd"><option>Stable</option><option>Damaged</option><option>Critical</option></select>','Add building')
  .f('rm',sel('b',"SELECT building_id,building_name FROM Building").'<input name="rn" type="number" placeholder="Room no" required style="width:90px"><input name="fn" type="number" placeholder="Floor" required style="width:80px"><input name="ar" type="number" step="0.01" placeholder="Area m2" required style="width:100px"><select name="hz"><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select>','Add room').'</div>';
 show('Buildings',"SELECT * FROM Building");
}
if($p=='reports'){
 show('Flights per drone',"SELECT d.callsign,COUNT(f.flight_id) AS flights FROM Drone d LEFT JOIN Flight f ON f.drone_id=d.drone_id GROUP BY d.drone_id,d.callsign");
 show('Flights per pilot',"SELECT t.name,COUNT(f.flight_id) AS flights FROM Team_Member t JOIN Flight f ON f.pilot_id=t.member_id GROUP BY t.member_id,t.name");
 show('Incidents per drone',"SELECT d.callsign,COUNT(i.event_no) AS incidents FROM Drone d LEFT JOIN Flight f ON f.drone_id=d.drone_id LEFT JOIN Incident_Event i ON i.flight_id=f.flight_id GROUP BY d.drone_id,d.callsign");
 show('Incidents per component (while installed on the drone)',"SELECT c.serial_no,c.component_name,COUNT(i.event_no) AS incidents FROM Component c JOIN Drone_Component dc ON dc.component_id=c.component_id JOIN Flight f ON f.drone_id=dc.drone_id AND DATE(f.takeoff_time)>=dc.installation_date AND (dc.removal_date IS NULL OR DATE(f.takeoff_time)<=dc.removal_date) LEFT JOIN Incident_Event i ON i.flight_id=f.flight_id GROUP BY c.component_id,c.serial_no,c.component_name ORDER BY incidents DESC");
 show('Mission outcomes',"SELECT m.mission_id,m.objective,m.status,COUNT(f.flight_id) AS flights,SUM(f.result='Successful') AS successful FROM Mission m LEFT JOIN Flight f ON f.mission_id=m.mission_id GROUP BY m.mission_id,m.objective,m.status");
}
?></main></body></html>

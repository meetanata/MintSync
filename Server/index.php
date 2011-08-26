<?php 

require_once 'config.php';

$domain = ""; 
$returnValues = array();

$action = "retrieve";
if(isset($_REQUEST['action']))
	$action = $_REQUEST['action'];
	
$db = new PDO('sqlite:'.PASSWORD_DATABASE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//do logging if enabled
if(LOGGING && !(isset($LOGLEVEL[$action]) && $LOGLEVEL[$action]==false))		//if log level is undefined, then assume we wish to log
{
	$stmt = $db->prepare("INSERT INTO AccessLog(Timestamp,RemoteIP,Request) VALUES(strftime('%s','now'),:IP,:request);");
	$stmt->bindValue(":IP",ip2long($_SERVER['REMOTE_ADDR']));
	$stmt->bindValue(":request",var_export($_REQUEST,true));
	$stmt->execute();
}

switch($action)
{
	case "save":

		if(isset($_REQUEST['URL']) && isset($_REQUEST['rowSalt']) && isset($_REQUEST['Credentials']))
		{
			$stmt = $db->prepare("SELECT COUNT(*) AS Freq FROM auth WHERE URL=:url");
			$stmt->bindValue(":url",strtolower($_REQUEST['URL']));
			$stmt->execute();
		
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if((int)$row['Freq']>0 && empty($_REQUEST['force']))
			{
				echo json_encode(array(
									"status"=>"fail",
									"action"=>"insert",
									"data"=>array(
										"reason"=>"Record already exists"
									)
								));
				exit();
			}
			elseif((int)$row['Freq']>0 && !empty($_REQUEST['force']))
			{
				$stmt = $db->prepare("UPDATE auth SET Salt=:salt, Credentials=:credentials WHERE URL=:url;");
			
				$stmt->bindValue(":url", strtolower($_REQUEST['URL']));
				$stmt->bindValue(":salt", $_REQUEST['rowSalt']);
				$stmt->bindValue(":credentials", $_REQUEST['Credentials']);
				$stmt->execute();
			
				$stmt = $db->prepare("SELECT * FROM auth WHERE URL=:url");
				$stmt->bindValue(":url", strtolower($_REQUEST['URL']), PDO::PARAM_STR );
				$stmt->execute();
			
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
				echo json_encode(array(
									"status"=>"ok",
									"action"=>"update",
									"data"=>array()
								));
			}
			else
			{
				$stmt = $db->prepare("INSERT INTO auth(URL,Salt,Credentials) VALUES(:url,:salt,:credentials)");
			
				$stmt->bindValue(":url", strtolower($_REQUEST['URL']));
				$stmt->bindValue(":salt", $_REQUEST['rowSalt']);
				$stmt->bindValue(":credentials", $_REQUEST['Credentials']);
				$stmt->execute();
			
				$stmt = $db->prepare("SELECT * FROM auth WHERE URL=:url");
				$stmt->bindValue(":url", strtolower($_REQUEST['URL']), PDO::PARAM_STR );
				$stmt->execute();
			
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
				echo json_encode(array(
									"status"=>"ok",
									"action"=>"insert",
									"data"=>array()
								));
			}
		}
		
	break;

	case "check":
		if(isset($_REQUEST['URL']))
			$domain = strtolower($_REQUEST['URL']);
		
		$stmt = $db->prepare("SELECT COUNT(*) num FROM auth WHERE URL=:url");
		$stmt->bindValue(":url",	$domain, PDO::PARAM_STR );
		$stmt->execute();
		
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		
		if(isset($rows[0]))
			echo json_encode(array(
						"status"=>"ok", 
						"action"=>"check",
						"data"=> $rows[0]['num']
					));
				
		else 
			echo json_encode(array(
						"status"=>"fail",
						"action"=>"check",
						"data" => array(
							"reason"=>"An Unexpected Error Occurred"
							)
					));
	break;
	
	case "retrieve":
	default:
		if(isset($_REQUEST['URL']))
			$domain = strtolower($_REQUEST['URL']);

		$stmt = $db->prepare("SELECT * FROM auth WHERE URL=:url");
		$stmt->bindValue(":url",	$domain, PDO::PARAM_STR );
		$stmt->execute();
		
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		
		if(isset($rows[0]))
			echo json_encode(array(
						"status"=>"ok", 
						"action"=>"retrieve",
						"data"=> $rows[0]
					));
				
		else 
			echo json_encode(array(
						"status"=>"fail",
						"action"=>"retrieve",
						"data" => array(
							"reason"=>"URL was not found"
							)
					));
		
}

?>
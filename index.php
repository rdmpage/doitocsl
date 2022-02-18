<?php



$doi = '10.1099/ijsem.0.005232'; // CrossRef
$doi = '10.13130/2039-4942/16126';
//$doi = '10.12905/0380.sydowia74-2021-0343';
$doi = '10.21411/cbm.a.ecd1343a';
$doi = '10.5281/zenodo.4535846';

if (isset($_GET['doi']))
{
	$doi = $_GET['doi'];
}

$prefix_filename = 'prefix.json';
$json = file_get_contents($prefix_filename);
$prefix_to_agency = json_decode($json, true);

//----------------------------------------------------------------------------------------
function get($url, $user_agent='', $content_type = '')
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  
		CURLOPT_SSL_VERIFYHOST=> FALSE,
		CURLOPT_SSL_VERIFYPEER=> FALSE,
	  
	);

	if ($content_type != '')
	{
		
		$opts[CURLOPT_HTTPHEADER] = array(
			"Accept: " . $content_type, 
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
		);
		
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
function doi_to_agency($doi)
{
	global $prefix_to_agency;
	
	$agency = '';
	
	$parts = explode('/', $doi);
	$prefix = $parts[0];
		
	if (isset($prefix_to_agency[$prefix]))
	{
		$agency = $prefix_to_agency[$prefix];
	}
	else
	{
		$url = 'https://doi.org/ra/' . $doi;
	
		$json = get($url);
	
		//echo $json;
	
		$obj = json_decode($json);
	
		if ($obj)
		{
			if (isset($obj[0]->RA))
			{
				$agency = $obj[0]->RA;
		
				$prefix_to_agency[$prefix] = $agency;
			}
	
		}
	}
	
	return $agency;
}

//----------------------------------------------------------------------------------------

$json = '';

$agency = doi_to_agency($doi);

switch ($agency)
{
	case 'Crossref':
		$url = 'https://api.crossref.org/v1/works/' . $doi;	
		$json = get($url);	
		break;
		
	case 'DataCite':
		$url = 'https://data.datacite.org/application/vnd.citationstyles.csl+json/' . urlencode($doi);
		$json = get($url);
		break;	

	case 'mEDRA':
		$url = 'https://doi.org/' . $doi;	
		$json = get($url, '', 'application/vnd.citationstyles.csl+json');
		break;	
		
	default:
		break;
}
/*
		$url = 'https://data.datacite.org/application/vnd.citationstyles.csl+json/' . urlencode($doi);
		$json = get($url);
						
		$obj = json_decode($json);
*/

if ($json != '')
{
	$csl = json_decode($json);
	
	if (isset($csl->message))
	{
		$csl = $csl->message;
	}
	
	print_r($csl);
	
	echo json_encode(array($csl));
}

file_put_contents($prefix_filename, json_encode($prefix_to_agency, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


?>

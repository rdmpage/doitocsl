<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/vendor/autoload.php');

use Sunra\PhpSimple\HtmlDomParser;


$doi = '10.1099/ijsem.0.005232'; // CrossRef
//$doi = '10.13130/2039-4942/16126';
//$doi = '10.12905/0380.sydowia74-2021-0343';
//$doi = '10.21411/cbm.a.ecd1343a';
//$doi = '10.5281/zenodo.4535846';
$doi = '10.11865/zs.2021105';

if (isset($_GET['doi']))
{
	$doi = $_GET['doi'];
}

$doi = strtolower($doi);

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
function get_redirect($url, $user_agent='')
{	
	$redirect = '';

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => FALSE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HEADER => TRUE,
	  
	  CURLOPT_SSL_VERIFYHOST=> FALSE,
	  CURLOPT_SSL_VERIFYPEER=> FALSE,
	  
	);

	if ($user_agent != '')
	{		
		$opts[CURLOPT_HTTPHEADER] = array(
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
		);
		
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
		
	
	
	if (curl_errno ($ch) != 0 )
	{
		echo "CURL error: ", curl_errno ($ch), " ", curl_error($ch);
	}
	else
	{
		$info = curl_getinfo($ch);
		
		//print_r($info);
		 
		$header = substr($data, 0, $info['header_size']);
		//echo $header;
				
		$http_code = $info['http_code'];
		
		if ($http_code == 303)
		{
			$redirect = $info['redirect_url'];
		}
		
		if ($http_code == 302)
		{
			$redirect = $info['redirect_url'];
		}
	}
	
	curl_close($ch);
	
	
	return $redirect;
}

//----------------------------------------------------------------------------------------
// post
function post($url, $data = '', $content_type = '')
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
	if ($content_type != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, 
			array(
				"Content-type: " . $content_type
				)
			);
	}	
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
		
	curl_close($ch);
	
	return $response;
}


//----------------------------------------------------------------------------------------
function doi_to_agency($prefix, $doi)
{
	global $prefix_to_agency;
	
	$agency = '';
			
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
function from_wanfang($id)
{
	$csl = null;

	$data = new stdclass;
	$data->Id = $id;

	$url = 'https://d.wanfangdata.com.cn/Detail/Periodical/';
	
	//echo $url;

	$json = post($url, json_encode($data));
	
	//echo $json;

	$obj = json_decode($json);
	
	// convert
	if ($obj)
	{
		$csl = new stdclass;

		foreach ($obj->detail[0]->periodical as $k => $v)
		{
			switch ($k)
			{
				// title, authors, etc.
				case 'Title':
					foreach ($v as $value)
					{
						$language = 'en';
						if (preg_match('/\p{Han}+/u', $value))
						{
							$language = 'zh';
						}
					
						if (!isset($csl->title))
						{
							$csl->title = $value;
						}

						if (!isset($csl->multi))
						{
							$csl->multi = new stdclass;
							$csl->multi->_key = new stdclass;
						}

						if (!isset($csl->multi->_key->title))
						{
							$csl->multi->_key->title = new stdclass;
						}
						$csl->multi->_key->title->{$language} = $value;
				
					}
					break;
			
				case 'PeriodicalTitle':
					foreach ($v as $value)
					{
						$language = 'en';
						if (preg_match('/\p{Han}+/u', $value))
						{
							$language = 'zh';
						}
					
						if (!isset($csl->{'container-title'}))
						{
							$csl->{'container-title'}= $value;
						}

						if (!isset($csl->multi))
						{
							$csl->multi = new stdclass;
							$csl->multi->_key = new stdclass;
						}

						if (!isset($csl->multi->_key->{'container-title'}))
						{
							$csl->multi->_key->{'container-title'} = new stdclass;
						}
						$csl->multi->_key->{'container-title'}->{$language} = $value;
				
					}
					break;
					
				case 'Abstract':
					foreach ($v as $value)
					{
						$language = 'en';
						if (preg_match('/\p{Han}+/u', $value))
						{
							$language = 'zh';
						}
					
						if (!isset($csl->abstract))
						{
							$csl->abstract = $value;
						}

						if (!isset($csl->multi))
						{
							$csl->multi = new stdclass;
							$csl->multi->_key = new stdclass;
						}

						if (!isset($csl->multi->_key->abstract))
						{
							$csl->multi->_key->abstract = new stdclass;
						}
						$csl->multi->_key->abstract->{$language} = $value;
				
					}
					break;
					
					
		
				// simple keys
				case 'Id':
					$csl->id = $v;
					break;
								
				case 'DOI':
					$csl->DOI = $v;
					break;

				case 'ISSN':
					$csl->ISSN[] = $v;
					break;
				
				case 'Issue':
					$csl->issue = $v;
					break;

				case 'Volum':
					$csl->volume = $v;
					break;

				case 'Page':
					$csl->page = $v;
					break;
				
				// date
				case 'PublishDate':													
					$csl->issued = new stdclass;
					$csl->issued->{'date-parts'} = array();
					$csl->issued->{'date-parts'}[0] = array();
				
					$parts = preg_split('/[-|\/]/', $v);
				
					foreach ($parts as $part)
					{
						$part = preg_replace('/^0/', '', $part);
						$csl->issued->{'date-parts'}[0][] = (Integer)$part;
					}
					break;

				default:
					break;
			}
		}	
		
		// authors require some work
		if (isset($obj->detail[0]->periodical->Creator))
		{
			$csl->author = array();
			
			foreach ($obj->detail[0]->periodical->Creator as $value)
			{
				$author = new stdclass;
				$author->literal = $value;
				
				$author->literal = $value;
				$author->multi = new stdclass;
				$author->multi->_key = new stdclass;
				$author->multi->_key->literal = new stdclass;
				
				$language = 'en';
				if (preg_match('/\p{Han}+/u', $value))
				{
					$language = 'zh';
				}
								
				$author->multi->_key->literal->{$language} = $value;
				
				$csl->author[] = $author;
			}	
			
			if (isset($obj->detail[0]->periodical->ForeignCreator))
			{
				$n = count($csl->author);
				
				for ($i = 0; $i < $n; $i++)
				{
					$value = $obj->detail[0]->periodical->ForeignCreator[$i];
				
					$language = 'en';
					if (preg_match('/\p{Han}+/u', $value))
					{
						$language = 'zh';
					}
								
					$csl->author[$i]->multi->_key->literal->{$language} = $value;

				
				}
			
			}
	
		
		}
	}
	
	return $csl;
}

//----------------------------------------------------------------------------------------
function from_meta($url)
{
	$csl = null;
	
	// DOI or URL?
	if (preg_match('/^10/', $url))
	{
		$url = 'https://doi.org/'. $url;
	}
	
	$html = get($url, "", "text/html");	

	// echo $html;
		
	if ($html == '')
	{
		return $csl;
	}
	else
	{						
		$dom = HtmlDomParser::str_get_html($html);

		if ($dom)
		{
			$csl = new stdclass;

			// meta
			foreach ($dom->find('meta') as $meta)
			{
				if (isset($meta->name) && ($meta->content != ''))
				{				
					switch ($meta->name)
					{							
						case 'citation_author':
							$author = new stdclass;
							$author->literal = $meta->content;
							$author->literal = trim(strip_tags($author->literal));
							$author->literal = preg_replace('/[0-9,\*]/', '', $author->literal);
							
							if ($author->literal != '')
							{
								$csl->author[] = $author;
							}
							break;		
							
						case 'citation_authors':
							if (!isset($csl->author))
							{
								$parts = preg_split('/,\s*/', $meta->content);
								foreach ($parts as $part)
								{
									$author = new stdclass;
									$author->literal = $part;
									$csl->author[] = $author;							
								}
							}
							break;					
																		
						case 'citation_doi':
							$csl->DOI = $meta->content;
							break;	
							
						case 'citation_title':
							$csl->title = trim($meta->content);
							$csl->title = html_entity_decode($csl->title , ENT_QUOTES | ENT_HTML5, 'UTF-8');
							$csl->title = preg_replace('/\s\s+/u', ' ', $csl->title);
							break;

						case 'citation_journal_title':
							$csl->{'container-title'} = $meta->content;
							$csl->type = 'article-journal';
							break;

						case 'citation_issn':
							$csl->issn[] = $meta->content;
							break;

						case 'citation_volume':
							$csl->volume = $meta->content;
							break;

						case 'citation_issue':
							$csl->issue = $meta->content;
							break;

						case 'citation_firstpage':
							$csl->{'page-first'} = $meta->content;
							$csl->{'page'} = $meta->content;
							break;

						case 'citation_lastpage':
							if (isset($csl->{'page'}))
							{
								$csl->{'page'} .= '-' . $meta->content;
							}
							break;

						case 'citation_abstract_html_url':
							$csl->URL = $meta->content;
							break;

						case 'citation_pdf_url':
							$link = new stdclass;
							$link->URL = $meta->content;
							$link->{'content-type'} = 'application/pdf';
		
							if (!isset($csl->link))
							{
								$csl->link = array();
							}
							$csl->link[] = $link;
							break;
			
						case 'citation_fulltext_html_url':
							break;
					
						case 'citation_abstract':
							$csl->abstract =  html_entity_decode($meta->content);
							break;	

						case 'citation_date':													
							$csl->issued = new stdclass;
							$csl->issued->{'date-parts'} = array();
							$csl->issued->{'date-parts'}[0] = array();
							
							if (strlen($meta->content) == 8 && is_numeric($meta->content))
							{
								$csl->issued->{'date-parts'}[0][] = (Integer)substr($meta->content, 0, 4);
								$csl->issued->{'date-parts'}[0][] = (Integer)substr($meta->content, 4, 2);
								$csl->issued->{'date-parts'}[0][] = (Integer)substr($meta->content, 6, 2);
							}
							else
							{							
								$parts = preg_split('/[-|\/]/', $meta->content);
							
								foreach ($parts as $part)
								{
									$part = preg_replace('/^0/', '', $part);
									$csl->issued->{'date-parts'}[0][] = (Integer)$part;
								}
							}
							break;
							
						case 'DC.Identifier':
							if (preg_match('/^10\.\d+\//', $meta->content))
							{
								$csl->DOI = $meta->content;
							}
							break;
							
						case 'Description':
							$csl->abstract = $meta->content;
							break;
													
						default:
							break;		
					}		
				}
			}
			
			// hacks
			
			// if we don't have date
			if (!isset($csl->issued))
			{
				if (isset($csl->DOI) && preg_match('/\/zs\.?(?<year>[0-9]{4})/', $csl->DOI, $m))
				{
					$csl->issued = new stdclass;
					$csl->issued->{'date-parts'} = array();
					$csl->issued->{'date-parts'}[0] = array();	
					$csl->issued->{'date-parts'}[0][] = (Integer)$m['year'];
				}
			}
			
			// if we don't have ISSN add it manually
			if (!isset($csl->ISSN))
			{
				if (isset($csl->{'container-title'}))
				{
					switch ($csl->{'container-title'})
					{
						case '广西植物':
							$csl->ISSN[] = '1000-3142';
							break;
					
						default:
							break;
					}
				
				
				}
			}
			
			// DOI-specific things
			if (isset($csl->DOI) && preg_match('/10.11833/', $csl->DOI))
			{
				foreach ($dom->find('footer a') as $a)
				{
					$link = new stdclass;
					$link->URL = 'https://zlxb.zafu.edu.cn' . $a->href;
					$link->{'content-type'} = 'application/pdf';

					if (!isset($csl->link))
					{
						$csl->link = array();
					}
					$csl->link[0] = $link;
				}
				
				// English title
				foreach ($dom->find('section[class=articleEn] h2') as $h2)
				{
					$csl->multi = new stdclass;
					$csl->multi->_key = new stdclass;
					$csl->multi->_key->title = new stdclass;
					
					$language = 'zh';
					$csl->multi->_key->title->{$language} = $csl->title;
				
					$language = 'en';
					$csl->multi->_key->title->{$language} = $h2->plaintext;
					
				}
				
				
			
			}
			
		}			
	}
		
	return $csl;
	
}

//----------------------------------------------------------------------------------------
function cnki_multi_to_url($doi)
{
	$url = '';
		
	$html = get('https://doi.org/'. $doi, "", "text/html");	

	//echo $html;
		
	if ($html == '')
	{
		return $url;
	}
	else
	{						
		$dom = HtmlDomParser::str_get_html($html);

		if ($dom)
		{
			foreach ($dom->find('td ul li a') as $a)
			{
				if (preg_match('/magtech.com.cn/', $a->href))
				{
					$url = $a->href;
				}
			}
		}
	}
	
	return $url;
}

//----------------------------------------------------------------------------------------
function from_cnki($doi)
{
	$csl = null;
		
	$html = get('https://doi.org/' . $doi, "", "text/html");	

	// echo $html;
		
	if ($html == '')
	{
		return $csl;
	}
	else
	{						
		$dom = HtmlDomParser::str_get_html($html);

		if ($dom)
		{
			$csl = new stdclass;
			
			// title
			foreach ($dom->find('h1') as $h1)
			{			
				$csl->title = $h1->plaintext;
			}
			
			// authors
			foreach ($dom->find('h3[class=author] span') as $span)
			{			
				$author = new stdclass;
				$author->literal = $span->plaintext;
				$author->literal = preg_replace('/[0-9,]/', '', $author->literal);
				$author->literal = preg_replace('/\s+$/', '', $author->literal);
				$csl->author[] = $author;
			}
			
			// abstract
			foreach ($dom->find('div[class=row] span[class=abstract-text]') as $span)
			{
				$csl->abstract = $span->plaintext;
			}
			
			// DOI
			foreach ($dom->find('div[class=row] ul li[class=top-space]') as $li)
			{		
				if (preg_match('/DOI：<\/span><p>(?<doi>.*)<\/p>/', $li->outertext, $m))	
				{
					$csl->DOI = $m['doi'];
				}
			}
			
			// collation
			foreach ($dom->find('div[class=top-tip] span a') as $a)
			{		
				if (isset($a->onclick))
				{
					if (preg_match('/getKns8NaviLink/', $a->onclick, $m))	
					{
						$csl->{'container-title'} = $a->plaintext;
					}
					
					if (preg_match('/getKns8YearNaviLink/', $a->onclick))	
					{
						if (preg_match('/(?<year>[0-9]{4}),(?<volume>\d+)\(?0(?<issue>\d+)\)/', $a->plaintext, $m))	
						{
							$csl->issued = new stdclass;
							$csl->issued->{'date-parts'} = array();
							$csl->issued->{'date-parts'}[0] = array();
							$csl->issued->{'date-parts'}[0][] = (Integer)$m['year'];
												
							$csl->volume = $m['volume'];
							$csl->issue = $m['issue'];
					
						}
						
					}
					
				}
			}
			
			// CNKI
			foreach ($dom->find('input[id=paramfilename]') as $input)
			{		
				$csl->CNKI = $input->value;
			}

		}			
	}
		
	return $csl;
	
}



//----------------------------------------------------------------------------------------

// test cases
if (0)
{
	// test
	$csl = from_meta($doi);

	print_r($csl);
	
	exit();

}

$force = false;
$force = true;

$cache_dir = dirname(__FILE__) . '/cache';

// go
$json = '';

$parts = explode('/', $doi);
$prefix = $parts[0];

$doi_filename = preg_replace('/[\/|:]/', '-', $doi);
$doi_filename .= '.json';

$cache_dir = $cache_dir . '/' . $prefix;

if (!file_exists($cache_dir))
{	
	$oldumask = umask(0); 
	mkdir($cache_dir, 0777);
	umask($oldumask);
}

$doi_filename  = $cache_dir . '/' . $doi_filename;

if (file_exists($doi_filename) && !$force)
{
	$json = file_get_contents($doi_filename);
	
	header("Content-type: text/plain");
	echo $json;
}
else
{
	$agency = doi_to_agency($prefix, $doi);

	switch ($agency)
	{
		case 'Airiti':
			// 10.6165/tai.2021.66.1
			// custom  and parse
			break;

		case 'CNKI':
			// 10.13346/j.mycosystema.210345
			// dual resolution
			// http://doi.cnki.net/Resolution/Handler?doi=10.13346/j.mycosystema.210345
			// http://manu40.magtech.com.cn/Jwxb/article/2021/1672-6472/1672-6472-40-12-3118.shtml has metadata
		
			// 10.16373/j.cnki.ahr.200025
			// CNKI site, custom parser (Endnote format is blocked by X-Frame-Options: SAMEORIGIN)
		
			switch ($prefix)
			{
				case '10.13346':
					$url = cnki_multi_to_url($doi);
					if ($url != '')
					{
						$csl = from_meta($url);
						$json = json_encode($csl);				
					}
					break;
				
				default:
					$csl = from_cnki($doi);
					$json = json_encode($csl);		
					break;
			}
		
			break;
	
		case 'Crossref':
			$url = 'https://api.crossref.org/v1/works/' . $doi;	
			$json = get($url);	
			break;
		
		case 'DataCite':
			$url = 'https://data.datacite.org/application/vnd.citationstyles.csl+json/' . urlencode($doi);
			$json = get($url);
			break;	
		
		case 'ISTIC':
			switch ($prefix)
			{
				case '10.11865':
				case '10.11931':
				case '10.11833':
					$csl = from_meta($doi);
					$json = json_encode($csl);
					break;
				
				// Wanfang		
				// 10.3969/j.issn.1005-9628.2021.01.001
				// dual resolution
				// http://www.chinadoi.cn/portal/mr.action?doi=10.3969/j.issn.1005-9628.2021.01.001
				// chinadoi has metadata
				case '10.3969':
				case '10.3321':
					$url = get_redirect('https://doi.wanfangdata.com.cn/' . $doi);
					if (preg_match('/periodical\/(?<id>.*)$/', $url, $m))
					{
						$csl = from_wanfang($m['id']);
						$json = json_encode($csl);
					}				
					break;
				
				default:
					break;
			}
			break;
	
		case 'JaLC':
			$url = 'https://doi.org/' . $doi;	
			$json = get($url, '', 'application/vnd.citationstyles.csl+json');
			
			break;	

		case 'mEDRA':
			$url = 'https://doi.org/' . $doi;	
			$json = get($url, '', 'application/vnd.citationstyles.csl+json');
			break;	
		
		default:
			break;
	}


	if ($json != '')
	{
		$csl = json_decode($json);
	
		if (isset($csl->message))
		{
			$csl = $csl->message;
		}
	
		// fixes
		if (isset($csl->ISSN))
		{
			foreach ($csl->ISSN as &$issn)
			{
				// JaLC
				if (strlen($issn) == 8)
				{
					$issn = substr($issn, 0, 4) . '-' . substr($issn, 4);
				}
			}
		}
	
		//print_r($csl);
	
		header("Content-type: text/plain");
		echo json_encode(array($csl), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		
		file_put_contents($doi_filename, json_encode(array($csl), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}
	else
	{
		// failed to resolve
		$obj = array();
		header("Content-type: text/plain");
		echo json_encode($obj);
	}

	file_put_contents($prefix_filename, json_encode($prefix_to_agency, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

}

?>

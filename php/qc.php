<?
/******************************************************************************
The script can check features in gene, mRNA, exon, and CDS levels of a gff3 
retrieved from JBrowse.

Suggestion of annotation.gff3 is generated from JBrowse. The script CANNOT 
guarantee to work well with gff files generated from any other programs.

Version 1.0.2

Change log:
1.0.2 [Fix] Support web-based version. Command line version stops to maintain.
1.0.1 [Fix] Regular expression description for getting a feature ID/Parent ID.
1.0.0 The first released version.

Feb/09/2015
(c) Chien-Yueh Lee 2014-2015 / MIT Licence
kinomoto[AT]sakura[DOT]idv[DOT]tw
******************************************************************************/
require_once('config.inc');

// Checking methods enable/disable
$CHECK_REDUNDANT = false;
$CHECK_MINUS_COORDINATE = false;
$CHECK_ZERO_START = false;
$CHECK_INCOMPLETE = false;
$CHECK_COORDINATE_BOUNDARY = false;
$CHECK_REDUNDANT_LENGTH = false;
$CHECK_MRNA_IN_PSEUDOGENE = false;

// Definition of sorting direction
define("CSORT_ASC", 1);
define("CSORT_DESC", -1);

// Input summary reports
$summary_gene = array();
$summary_pseudogene = array();
$arr_summary_report = array('pass'=>array('gene'=>array('total'=>0), 'pseudogene'=>array('total'=>0)),
							'warning'=>array('total'=>0, 'zero_start'=>array()),
							'fail'=>array('total'=>0, 'redundant'=>array(), 'minus_coordinate'=>array(), 'coordinate_boundary'=>array(), 'redundant_length'=>array(), 'mRNA_in_pseudogene'=>array(), 'incomplete'=>array()), 
							'filename'=>'');

// Input files processing
if(!isset($_GET['pid']))	{exit;}
if(!preg_match('/[a-f0-9]/', $_GET['pid'])) {exit;}

$output_dir = $upload_dir . '/' . $_GET['pid'] . '/';
if(isset($_POST['gff']))
{
	$anno_gff_name = preg_replace('/\["(.+)"\]/','\1',$_POST['gff']);
	$anno_gff_name = str_replace("..",".",$anno_gff_name); //required. if somebody is trying parent folder files
	$PATH_anno_gff = $output_dir . $anno_gff_name;

}
else
{
	echo nl2br( "Reading GFF files error!\n");
	exit;
}

// Preferences processing
if(isset($_POST['ckbox']))
{
	foreach($_POST['ckbox'] as $ck_val)
	{
		switch($ck_val)
		{
			case 'CHECK_REDUNDANT':
				$CHECK_REDUNDANT = true;
				break;
				
			case 'CHECK_MINUS_COORDINATE':
				$CHECK_MINUS_COORDINATE = true;
				break;
				
			case 'CHECK_ZERO_START':
				$CHECK_ZERO_START = true;
				break;
				
			case 'CHECK_INCOMPLETE':
				$CHECK_INCOMPLETE = true;
				break;
			
			case 'CHECK_COORDINATE_BOUNDARY':
				$CHECK_COORDINATE_BOUNDARY = true;
				break;
			
			case 'CHECK_REDUNDANT_LENGTH':
				$CHECK_REDUNDANT_LENGTH = true;
				break;
			
			case 'CHECK_MRNA_IN_PSEUDOGENE':
				$CHECK_MRNA_IN_PSEUDOGENE = true;
				break;
				
		}
	}
}


/***************************************************
	Annotated gff analysis start
****************************************************/
global $anno_gff;
$anno_gff = read_anno_gff($PATH_anno_gff);
$arr_anno_gff_structure = make_anno_gff_structure($anno_gff);

// Print the processed file name
//echo nl2br("File name: <b>".$anno_gff_name."</b>\n\n");
$arr_summary_report['filename'] = $anno_gff_name;

// Check gene type
$summary_gene['gene'] = 0;
$summary_gene['exon'] = 0;
$summary_gene['CDS'] = 0;

if(isset($arr_anno_gff_structure['gene']))
{
	foreach($arr_anno_gff_structure['gene'] as $arr_features)
	{
		// Check features of minus coordinates (For Web Apollo)
		if($CHECK_MINUS_COORDINATE)
		{
			if(check_minus_coordinate($arr_features, $arr_summary_report))	continue;
		}
		
		// Check features of zero start coordinate (For Web Apollo)
		if($CHECK_ZERO_START)
		{
			if(check_zero_start($arr_features, $arr_summary_report))	continue;
		}
		
		// Check incomplete features (A legal feature should contain gene, mRNA, exon, and CDS, simultaneously.)
		if($CHECK_INCOMPLETE)
		{
			if(check_incomplete($arr_features, $arr_summary_report))	continue;
		}
		
		// Check features over coordinate boundary of a gene
		if($CHECK_COORDINATE_BOUNDARY)
		{
			if(check_coordinate_boundary($arr_features, $arr_summary_report))	continue;
		}
		
		// Check gene features with redundant length
		if($CHECK_REDUNDANT_LENGTH)
		{
			if(check_redundant_length($arr_features, $arr_summary_report))	continue;
		}
		
		// Check redundant features
		if($CHECK_REDUNDANT)
		{
			if(!isset($arr_features['mRNA']))	$arr_features['mRNA'] = array();
			if(!isset($arr_features['exon']))	$arr_features['exon'] = array();
			if(!isset($arr_features['CDS']))	$arr_features['CDS'] = array();
			
			if(check_redundant($arr_features['mRNA'], array_merge($arr_features['exon'], $arr_features['CDS']), $arr_mRNA_nr, $arr_exon_CDS_nr, $arr_summary_report))
			{
				$summary_gene['gene']++;	// The types of redundant features are mRNA and exon/CDS, not including a gene. So, it should be additional plus one into counting variable before "continue".
				
				$summary_gene['mRNA'] += count($arr_mRNA_nr);	// Counting unique mRNAs
				
				foreach($arr_exon_CDS_nr as $f)	// Counting unique exons/CDSs
				{
					if($f[2] == 'exon')
					{
						$summary_gene['exon']++;
					}
					elseif($f[2] == 'CDS')
					{
						$summary_gene['CDS']++;
					}
				}
				
				continue;
			}
		}
		
		// Counting the input summary
		foreach($arr_features as $str_type=>$features)
		{
			if(isset($summary_gene[$str_type]))
			{
				$summary_gene[$str_type] += count($features);
			}
			else
			{
				$summary_gene[$str_type] = count($features);
			}
		}
	}
}

// Check pseudogene type
$summary_pseudogene['pseudogene'] = 0;

if(isset($arr_anno_gff_structure['pseudogene']))
{
	foreach($arr_anno_gff_structure['pseudogene'] as $arr_features)
	{
		// Check any mRNA in the type of pseudogene (For Web Apollo)
		if($CHECK_MRNA_IN_PSEUDOGENE)
		{
			if(check_mRNA_in_pseudogene($arr_features, $arr_summary_report))
			{
				foreach($arr_features as $t=>$f)
				{
					if($t == 'mRNA')
					{
						if(isset($summary_pseudogene['pseudogenic_transcript']))
						{
							$summary_pseudogene['pseudogenic_transcript'] += count($f);
						}
						else
						{
							$summary_pseudogene['pseudogenic_transcript'] = count($f);
						}
					}
				}
				
				$summary_pseudogene['pseudogene']++;
				continue;
			}
		}

		// Counting the input summary
		foreach($arr_features as $str_type=>$features)
		{
			if(isset($summary_pseudogene[$str_type]))
			{
				$summary_pseudogene[$str_type] += count($features);
			}
			else
			{
				$summary_pseudogene[$str_type] = count($features);
			}
		}
	}
}

// Print summary reports
//echo nl2br("\nSummary: (Passed features)\n-------------------Gene-------------------\n");
foreach($summary_gene as $t=>$num)
{
	//echo nl2br("$t: $num\n");
	$arr_summary_report['pass']['gene'][$t] = $num;
	$arr_summary_report['pass']['gene']['total'] += $num;
}
//echo nl2br("----------------Pseudogene----------------\n");
foreach($summary_pseudogene as $t=>$num)
{
	//echo nl2br("$t: $num\n");
	$arr_summary_report['pass']['pseudogene'][$t] = $num;
	$arr_summary_report['pass']['pseudogene']['total'] += $num;
}
//echo nl2br("\n");
echo json_encode($arr_summary_report);
//echo var_dump($arr_summary_report);

/***************************************************
	Functions start
****************************************************/
function get_line_num($type, $id)	// Type = ID, Name, or Parent
{
	global $anno_gff;
	//var_dump($anno_gff);
	foreach($anno_gff as $line_num=>$line)
	{
		if(preg_match("/($type=$id);*/", $line))
		{
			return ($line_num+1);
		}
	}
	
	return -1;
}

function read_anno_gff($path)
{
	try
	{
		$fp_anno_gff = fopen($path, 'r');
		$arr_gff_content = array();
		
		// Open an annotated gff file
		while(!feof($fp_anno_gff))
		{
			$arr_gff_content[] = trim(fgets($fp_anno_gff));
			
		}	//end while
		fclose($fp_anno_gff);
	
		return $arr_gff_content;
	}
	catch (Exception $e)
	{
		echo nl2br('Error: ',  $e->getMessage(), "\n");
		exit;
	}
}

function make_anno_gff_structure(&$arr_anno_gff)
{
	$arr_features = array();
	$int_gene_num = 0;
	$int_pseudogene_num = 0;
	$int_others_num = 1;
	$counter = 0;
	foreach($arr_anno_gff as $line)
	{
		if($line == '')  {continue;}
		if(substr($line, 0, 1) == '#')	{continue;}
		// Fields info: 0:Scaffolds ID, 1:Category, 2:Type, 3:Start, 4:End, 5:##, 6:Strand, 7:Phase, 8:Annotation
		$line_content = explode("\t", $line);
		//var_dump($line_content);
		switch ($line_content[2])
		{
			case 'gene':
				$current_type = 'gene';
				
				$arr_features[$current_type][++$int_gene_num]['gene'][] = $line_content;
				//$int_gene_num++;
				break;
				
			case 'pseudogene':
				$current_type = 'pseudogene';
				
				$arr_features[$current_type][++$int_pseudogene_num]['pseudogene'][] = $line_content;
				//$int_pseudogene_num++;
				break;
				
			default:
				// To distinguish mRNAs, transcripts, or CDSs corresponding to what their parent is.
				if($current_type == 'gene')
				{
					$arr_features[$current_type][$int_gene_num][$line_content[2]][] = $line_content;
				}
				elseif($current_type == 'pseudogene')
				{
					$arr_features[$current_type][$int_pseudogene_num][$line_content[2]][] = $line_content;
				}
				else	// Others
				{
					$arr_features['others'][$int_others_num][$line_content[2]][] = $line_content;
					$int_others_num++;
				}
		}
		$counter++;
		//var_dump($arr_features);
	}
	return $arr_features;
	// The structure of $arr_features: 
	// [gene][1][gene][0][0-8]
	// [gene][1][mRNA][0][0-8]
	// [gene][1][exon][0][0-8]
	// [gene][1][exon][1][0-8]
	// [gene][1][CDS][0][0-8]
	// [gene][1][CDS][1][0-8]
	// [gene][1][mRNA][1][0-8]
	// [gene][1][exon][2][0-8]
	// [gene][1][exon][3][0-8]
	// [gene][1][exon][4][0-8]
	// [gene][1][CDS][2][0-8]
	// [gene][1][CDS][3][0-8]
	// [gene][1][CDS][4][0-8]

	// [gene][2][gene][0][0-8]
	// [gene][2][mRNA][0][0-8]
	// [gene][2][exon][0][0-8]
	// [gene][2][exon][1][0-8]
	// [gene][2][exon][2][0-8]
	// [gene][2][CDS][0][0-8]
	// [gene][2][CDS][1][0-8]
	// [gene][2][CDS][2][0-8]
	// [gene][2][mRNA][1][0-8]
	// [gene][2][exon][3][0-8]
	// [gene][2][exon][4][0-8]
	// [gene][2][CDS][3][0-8]
	// [gene][2][CDS][4][0-8]
}

function check_minus_coordinate(&$arr_features, &$arr_summary_report)
{
	$check_flag = false;
	foreach($arr_features as $type=>$f)
	{
		foreach($f as $feature)
		{
			if($feature[3]<0 || $feature[4]<0)
			{
				preg_match('/ID=([\w-]+);*/', $feature[8], $m);
				$str_err_msg = '[Line '.get_line_num('ID', $m[1]).']: Minus start/end coordinate.';
				$arr_summary_report['fail']['minus_coordinate'][] = $str_err_msg;
				$arr_summary_report['fail']['total'] += 1;
				
				$check_flag = true;
			}
		}
	}
	
	return $check_flag;
}

function check_coordinate_boundary($arr_features, &$arr_summary_report)
{
	$check_flag = false;
	
	foreach($arr_features as $type=>$f)
	{
		if($type == 'gene')
		{
			$gene_start = $f[0][3];
			$gene_end = $f[0][4];
		}
		elseif($type == 'mRNA' || $type == 'exon' || $type == 'CDS')
		{
			foreach($f as $feature)
			{
				if($feature[3] < $gene_start || $feature[4] > $gene_end)
				{
					preg_match('/ID=([\w-]+);*/', $feature[8], $m);
					$str_err_msg = '[Line '.get_line_num('ID', $m[1]).']: A child feature over a coordinate boundary of its related gene.';
					$arr_summary_report['fail']['coordinate_boundary'][] = $str_err_msg;
					$arr_summary_report['fail']['total'] += 1;
					
					$check_flag = true;
				}
			}
		}
	}
	
	return $check_flag;
}

function check_redundant_length(&$arr_features, &$arr_summary_report)
{
	$check_flag = false;

	foreach($arr_features as $type=>$f)
	{
		if($type == 'gene')
		{
			$gene_start = $f[0][3];
			$gene_end = $f[0][4];
			$gene_len = $gene_end - $gene_start + 1;
			
			if(preg_match('/ID=([\w-]+);*/', $f[0][8], $m))
			{
				$gene_id = $m[1];
			}
			else
			{
				$gene_id = '';
			}
		}
		else
		{
			foreach($f as $feature)
			{
				if(isset($min_start) || isset($max_end))
				{
					if($feature[3] < $min_start)
					{
						$min_start = $feature[3];
					}
					elseif($feature[4] > $max_end)
					{
						$max_end = $feature[4];
					}
				}
				else
				{
					$min_start = $feature[3];
					$max_end = $feature[4];
				}
			}
		}
	}
	
	$child_len =  $max_end - $min_start + 1;
	
	if(($min_start != $gene_start || $max_end != $gene_end) && ($gene_len > $child_len))
	{
		$str_err_msg = '[Line '.get_line_num('ID', $gene_id)."]: Found ".($gene_len-$child_len).' bp redundant length of the gene.';
		$arr_summary_report['fail']['redundant_length'][] = $str_err_msg;
		$arr_summary_report['fail']['total'] += 1;
		
		$check_flag = true;
	}
	
	return $check_flag;
}

function check_zero_start(&$arr_features, &$arr_summary_report)
{
	$check_flag = false;
	foreach($arr_features as $type=>$f)
	{
		foreach($f as $feature)
		{
			if($feature[3] == 0)
			{
				preg_match('/ID=([\w-]+);*/', $feature[8], $m);
				$str_err_msg = '[Line '.get_line_num('ID', $m[1]).']: Zero start coordinate.';
				$arr_summary_report['warning']['zero_start'][] = $str_err_msg;
				$arr_summary_report['warning']['total'] += 1;
				
				$check_flag = true;
			}
		}
	}
	
	return $check_flag;
}

function check_incomplete(&$arr_features, &$arr_summary_report)
{
	if(isset($arr_features['gene']) && isset($arr_features['mRNA']))
	{
		if(isset($arr_features['exon']) && isset($arr_features['CDS']))
		{return false;}
		else
		{
			preg_match('/ID=([\w-]+);*/', $arr_features['gene'][0][8], $m);
			$str_err_msg = '[Line '.get_line_num('ID', $m[1]).']: Incomplete gene feature that should be contain at least one mRNA, exon, and CDS.';
			$arr_summary_report['fail']['incomplete'][] = $str_err_msg;
			$arr_summary_report['fail']['total'] += 1;
		
			return true;
		}
	}
	else {return false;}
}

/***************************************************
	Suppose:
	1. Each gene is unique and no redundant.
	2. Only check types of exon and CDS.
	3. Exons/CDSs without any multiple parent IDs.
****************************************************/
function check_redundant($arr_mRNAs, $arr_exon_CDSs, &$arr_nr_mRNAs=array(), &$arr_nr_exon_CDSs=array(), &$arr_summary_report)
{
	$check_flag = false;
	$arr_checked_mRNAs_result = array();
	$arr_nr_mRNAs_tmp = array();
	$arr_nr_exon_CDSs_tmp = $arr_exon_CDSs;
	$remove_exon_CDS_list = array();
	//var_dump($arr_mRNAs);
	for($i=count($arr_mRNAs)-1; $i>=0; $i--)
	{
		$arr_tmp_targets_id = array();
	
		preg_match('/ID=([\w-]+);*/', $arr_mRNAs[$i][8], $m);
		$mRNA_id_source = $m[1];
		$arr_nr_mRNAs_tmp[$mRNA_id_source] = $arr_mRNAs[$i];
		
		for($j=$i-1; $j>=0; $j--)
		{
			preg_match('/ID=([\w-]+);*/', $arr_mRNAs[$j][8], $n);
			$mRNA_id_target = $n[1];
		 
			if(!strcmp($arr_mRNAs[$i][0]."|".$arr_mRNAs[$i][1]."|".$arr_mRNAs[$i][2]."|".$arr_mRNAs[$i][3]."|".$arr_mRNAs[$i][4]."|".$arr_mRNAs[$i][5]."|".$arr_mRNAs[$i][6]."|".$arr_mRNAs[$i][7], $arr_mRNAs[$j][0]."|".$arr_mRNAs[$j][1]."|".$arr_mRNAs[$j][2]."|".$arr_mRNAs[$j][3]."|".$arr_mRNAs[$j][4]."|".$arr_mRNAs[$j][5]."|".$arr_mRNAs[$j][6]."|".$arr_mRNAs[$j][7]))
			{
				//Potential redundant
				$arr_tmp_targets_id[] = $mRNA_id_target;
				
			}
			else
			{
				$arr_tmp_targets_id[] = 'NA';
			}
		}
		
		$arr_checked_mRNAs_result[$mRNA_id_source] = $arr_tmp_targets_id;
	}

	foreach($arr_checked_mRNAs_result as $mRNA_id_source=>$arr_targets_id)
	{
		$is_the_same_target = false;

		foreach($arr_targets_id as $mRNA_id_target)
		{
			if($mRNA_id_target == 'NA')	continue;
			
			if($is_the_same_target)	break;
			
			$arr_exon_CDSs_source = array();
			$arr_exon_CDSs_taregt = array();
			
			foreach($arr_exon_CDSs as $arr_exon_CDS)
			{
				preg_match('/Parent=([\w-]+);*/', $arr_exon_CDS[8], $m);
				$exon_CDS_parent = $m[1];
				
				// Pick the corresponding exon/CDS of an mRNA id
				if($mRNA_id_source == $exon_CDS_parent)
				{
					$arr_exon_CDSs_source[] = $arr_exon_CDS;
				}
				elseif($mRNA_id_target == $exon_CDS_parent)
				{
					$arr_exon_CDSs_taregt[] = $arr_exon_CDS;
				}
			}
			// Sorting
			csort($arr_exon_CDSs_source, array(3, 4, 2), array(CSORT_ASC, CSORT_DESC, CSORT_DESC));
			csort($arr_exon_CDSs_taregt, array(3, 4, 2), array(CSORT_ASC, CSORT_DESC, CSORT_DESC));
						
			// Using for debug
			//echo "$mRNA_id_source\n";
			//echo count($arr_exon_CDSs_source).", ".count($arr_exon_CDSs_taregt)."\n";
			//var_dump($arr_exon_CDSs_source);
			//var_dump($arr_exon_CDSs_taregt);
			
			// Make a comparison between $arr_exon_CDSs_source and $arr_exon_CDSs_target
			// If they are the same start and end coordinate but different number of exons/CDSs, it means that they are isoforms
			if(count($arr_exon_CDSs_source) == count($arr_exon_CDSs_taregt))
			{
				for($i=0; $i<count($arr_exon_CDSs_source); $i++)
				{
					for($j=0; $j<8; $j++)
					{
						// strcmp returns 0 when A is equal to B
						//if(!strcmp($arr_exon_CDSs_source[$i][$j], $arr_exon_CDSs_taregt[$i][$j]))
						if($arr_exon_CDSs_source[$i][$j] == $arr_exon_CDSs_taregt[$i][$j])
						{
							$is_the_same_target = true;
						}
						else
						{
							$is_the_same_target = false;
							break;
						}
					}
					//echo $i.":".(int)$is_the_same_target." ";
					if(!$is_the_same_target)
					{
						break;
					}
				}
			}
			//echo "\n".(int)$is_the_same_target."\n";
		
			// Remove redundant mRNA
			if($is_the_same_target)
			{
				//echo "$mRNA_id_source\n";
				//var_dump($arr_nr_mRNAs_tmp);
				
				$str_err_msg = '[Line '.get_line_num('ID', $mRNA_id_source).']: Duplicate mRNAs found between ID='.$mRNA_id_source.' and '.$mRNA_id_target.'.';
				$arr_summary_report['fail']['redundant'][] = $str_err_msg;
				$arr_summary_report['fail']['total'] += 1;
				unset($arr_nr_mRNAs_tmp[$mRNA_id_source]);			
				
				$first_exon_CDS_line_num = get_line_num('Parent', $mRNA_id_source);
				$last_exon_CDS_line_num = $first_exon_CDS_line_num;
				for($i=0; $i<count($arr_nr_exon_CDSs_tmp); $i++)
				{
					if(preg_match("/(Parent=$mRNA_id_source);*/", $arr_nr_exon_CDSs_tmp[$i][8]))
					{
						$remove_exon_CDS_list[] = $i;
						
						$last_exon_CDS_line_num++;
					}
				
				}
				$str_err_msg = '[Line '.$first_exon_CDS_line_num.'-'.($last_exon_CDS_line_num-1).']: Duplicate exon/CDS found.';
				$arr_summary_report['fail']['redundant'][] = $str_err_msg;
				$arr_summary_report['fail']['total'] += $last_exon_CDS_line_num - $first_exon_CDS_line_num;
				$check_flag = true;
			}
		}
	}
	
	foreach($remove_exon_CDS_list as $k)
	{
		unset($arr_nr_exon_CDSs_tmp[$k]);
	}

	$arr_nr_mRNAs = $arr_nr_mRNAs_tmp;
	$arr_nr_exon_CDSs = $arr_nr_exon_CDSs_tmp;
	return $check_flag;
}

function check_mRNA_in_pseudogene(&$arr_features, &$arr_summary_report)
{
	$check_flag = false;
	foreach($arr_features as $type=>$f)
	{
		if($type == 'mRNA')
		{
			foreach($f as $feature)
			{
				if($feature[2] == 'mRNA')
				{
					preg_match('/ID=([\w-]+);*/', $feature[8], $m);
					$str_err_msg = '[Line '.get_line_num('ID', $m[1]).']: mRNA in the type of pseudogene found.';
					$arr_summary_report['fail']['mRNA_in_pseudogene'][] = $str_err_msg;
					$arr_summary_report['fail']['total'] += 1;
					
					$check_flag = true;
				}
			}
		}
	}
	
	return $check_flag;
}

// Multiple sorting
function csort(&$in_arr, $k, $sort_direction)
{
	if(count($k) != count($sort_direction)) {return 0;}

    //global $csort_cmp;

    $csort_cmp = array(
        'keys'           => $k,
        'directions'     => $sort_direction
    );

    //usort($in_arr, "csort_cmp");
	usort($in_arr, function(&$a, &$b) use($csort_cmp) {
		$key = array_shift($csort_cmp['keys']);
		$direction = array_shift($csort_cmp['directions']);
		$cmp = 0;

		if ($a[$key] > $b[$key])
			return $direction;

		if ($a[$key] < $b[$key])
			return -1 * $direction;
			
		while(count($csort_cmp['keys'])>0 && $cmp==0)
		{
			$key = array_shift($csort_cmp['keys']);
			$direction = array_shift($csort_cmp['directions']);
			
			if ($a[$key] > $b[$key]) {$cmp = $direction;}
			else if ($a[$key] < $b[$key]) {$cmp = -1 * $direction;}
			else {$cmp = 0;}
		}

		return $cmp;
	});

    //unset($csort_cmp);
	
	return 1;
} 
?>

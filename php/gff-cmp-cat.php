<?
/******************************************************************************
Gff-cmp-cat can calculate the differences between overlapping genes and mRNAs 
between two gff3 files. It provides an easy way to make a comparison between an 
original gene model and a curated file; then shows the differences categorized 
into eight action types from features in gene, mRNA, exon, and CDS levels.

Version 1.0.6

Change log:
1.0.6 [Fix] Support web-based version. Command line version stops maintaining.
1.0.5 [Fix] Rename internally modified to modified within boundary coordinates.
1.0.4 [Add] Identify merge and split cases with UTR overlapping.
1.0.3 [Add] Output exon/CDS detailed results, users can find and switch it to
			enable/disable in the "Customized parameters" block.
	  [Fix] Revoke the condition rule "Overlapping to only one old" for
			exon/CDS in the check_extend_reduce() function.
1.0.2 [Fix] Reduce memory usage.
	  [Fix] Regular expression description for getting a feature ID/Parent ID.
1.0.1 [Add] memory limit setting with 2GB by default.
1.0.0 The first released version.

Feb/09/2015
(c) Chien-Yueh Lee 2014-2015 / MIT Licence
kinomoto[AT]sakura[DOT]idv[DOT]tw
******************************************************************************/

require_once('config.inc');

/*************************************
       Customized parameters
*************************************/
// Output exon/CDS detailed results
global $OUTPUT_EXON_CDS_DETAILS;
$OUTPUT_EXON_CDS_DETAILS = false;

/*************************************
       Checking input arguments
*************************************/
global $output_dir;
global $PATH_old_gff;
global $PATH_new_gff;

if(!isset($_GET['pid']))	{exit;}
if(!preg_match('/[a-f0-9]/', $_GET['pid'])) {exit;}

$input_dir = $upload_dir . '/' . $_GET['pid'] . '/';
$output_dir = $upload_dir . '/' . $_GET['pid'] . '/';

// Input files processing
if(isset($_POST['gff_maker']) && isset($_POST['gff_anno']))
{
	$gff_maker = preg_replace('/\["(.+)"\]/','\1',$_POST['gff_maker']);
	$gff_anno = preg_replace('/\["(.+)"\]/','\1',$_POST['gff_anno']);
	$gff_maker = str_replace("..",".",$gff_maker); //required. if somebody is trying parent folder files
	$gff_anno = str_replace("..",".",$gff_anno);
	$PATH_old_gff = $input_dir . $gff_maker;
	$PATH_new_gff = $input_dir . $gff_anno;
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
			case 'OUTPUT_EXON_CDS_DETAILS':
				$OUTPUT_EXON_CDS_DETAILS = true;
				break;
		}
	}
}

/*************************************
      Preparing related variables
*************************************/
// Reading old gff file
$old_gff = read_anno_gff($PATH_old_gff);

// Creating old gff structure
$arr_old_gff_structure = make_gff_structure($old_gff);
unset($old_gff);

// Reading new gff file
$new_gff = read_anno_gff($PATH_new_gff);

// Creating new gff structure
$arr_new_gff_structure = make_gff_structure($new_gff);
unset($new_gff);

// Allocating memory space for analytic result
$arr_new_statistics_results = init_statistics_results($arr_new_gff_structure);

// Get overlapping between the old and new. (The same function of BedTools intersect.)
$arr_old_overlapping = array();
$arr_new_overlapping = array();
overlapping($arr_old_gff_structure, $arr_new_gff_structure, $arr_old_overlapping, $arr_new_overlapping);

/*************************************
           Starting analysis
*************************************/
check_extend_reduce($arr_old_gff_structure, $arr_new_gff_structure, $arr_new_overlapping, $arr_new_statistics_results);
check_add($arr_new_gff_structure, $arr_new_overlapping, $arr_new_statistics_results);
check_merge($arr_new_overlapping, $arr_new_statistics_results);
check_split($arr_new_gff_structure, $arr_old_overlapping, $arr_new_statistics_results);
check_modified_within_boundary_coordinates($arr_old_gff_structure, $arr_new_gff_structure, $arr_new_overlapping, $arr_new_statistics_results);	// This should be put for the last checking

/*************************************
            Summary report
*************************************/
summary($arr_old_gff_structure, $arr_new_gff_structure, $arr_new_overlapping, $arr_new_statistics_results);

/*************************************
     Archiving all detailed files
*************************************/
zip_archive($_POST['timestamp']);

/*************************************
      Removing all detailed files
*************************************/
remove_all_txt_files();

/*************************************
               Functions
*************************************/
function overlapping(&$arr_old_gff_structure, &$arr_new_gff_structure, &$arr_old_overlapping, &$arr_new_overlapping)
{
	// Checking overlapping

	$arr_old_gff_structure_idx = make_structure_index($arr_old_gff_structure); //Creating old gff structure indexes
	
	foreach($arr_new_gff_structure['gene'] as $str_new_gene_id=>$arr_new_gene_feature)
	{
		$overlapping_len = 0;

		if(!isset($arr_old_gff_structure_idx[$arr_new_gene_feature[0]]))
		{
			continue;
		}

		foreach($arr_old_gff_structure_idx[$arr_new_gene_feature[0]]['gene'] as $str_old_gene_id)
		{
			if($arr_new_gene_feature[6] == $arr_old_gff_structure['gene'][$str_old_gene_id][6])
			{
				$is_gene_overlapping = test_overlapping($arr_new_gene_feature[3], $arr_new_gene_feature[4], $arr_old_gff_structure['gene'][$str_old_gene_id][3], $arr_old_gff_structure['gene'][$str_old_gene_id][4], $overlapping_len);
				if($is_gene_overlapping)
				{
					$arr_new_overlapping['gene'][$str_new_gene_id][] = array($str_old_gene_id, $overlapping_len);
					$arr_old_overlapping['gene'][$str_old_gene_id][] = array($str_new_gene_id, $overlapping_len);
				}
				else
					continue;
			}
			else
				continue;
				
			// mRNA
			if(isset($arr_new_gff_structure['mRNA'][$str_new_gene_id]))
			{
				foreach($arr_new_gff_structure['mRNA'][$str_new_gene_id] as $str_new_mRNA_id=>$arr_new_mRNA_feature)
				{
					if(isset($arr_old_gff_structure['mRNA'][$str_old_gene_id]))
					{
						foreach($arr_old_gff_structure['mRNA'][$str_old_gene_id] as $str_old_mRNA_id=>$arr_old_mRNA_feature)
						{
							if($arr_new_mRNA_feature[6] == $arr_old_mRNA_feature[6])
							{
								$is_mRNA_overlapping = test_overlapping($arr_new_mRNA_feature[3], $arr_new_mRNA_feature[4], $arr_old_mRNA_feature[3], $arr_old_mRNA_feature[4], $overlapping_len);
								if($is_mRNA_overlapping)
								{
									$arr_new_overlapping['mRNA'][$str_new_gene_id][$str_new_mRNA_id][] = array($str_old_gene_id, $str_old_mRNA_id, $overlapping_len);
									$arr_old_overlapping['mRNA'][$str_old_gene_id][$str_old_mRNA_id][] = array($str_new_gene_id, $str_new_mRNA_id, $overlapping_len);
								}
								else
									continue;
							}
							else
								continue;
							
							// exon
							foreach($arr_new_gff_structure['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_new_exon_feature)
							{
								foreach($arr_old_gff_structure['exon'][$str_old_mRNA_id] as $str_old_exon_id=>$arr_old_exon_feature)
								{
									if($arr_new_exon_feature[6] == $arr_old_exon_feature[6])
									{
										$is_exon_overlapping = test_overlapping($arr_new_exon_feature[3], $arr_new_exon_feature[4], $arr_old_exon_feature[3], $arr_old_exon_feature[4], $overlapping_len);
										if($is_exon_overlapping)
										{
											$arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id][] = array($str_old_mRNA_id, $str_old_exon_id, $overlapping_len);
											$arr_old_overlapping['exon'][$str_old_mRNA_id][$str_old_exon_id][] = array($str_new_mRNA_id, $str_new_exon_id, $overlapping_len);
										}
										else
											continue;
									}
									else
										continue;
								}
							}
							
							// CDS
							foreach($arr_new_gff_structure['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_new_CDS_feature)
							{
								foreach($arr_old_gff_structure['CDS'][$str_old_mRNA_id] as $str_old_CDS_idx=>$arr_old_CDS_feature)
								{
									if($arr_new_CDS_feature[6] == $arr_old_CDS_feature[6])
									{
										$is_CDS_overlapping = test_overlapping($arr_new_CDS_feature[3], $arr_new_CDS_feature[4], $arr_old_CDS_feature[3], $arr_old_CDS_feature[4], $overlapping_len);
										if($is_CDS_overlapping)
										{
											$arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx][] = array($str_old_mRNA_id, $str_old_CDS_idx, $overlapping_len);
											$arr_old_overlapping['CDS'][$str_old_mRNA_id][$str_old_CDS_idx][] = array($str_new_mRNA_id, $str_new_CDS_idx, $overlapping_len);
										}
									}
								}
							}
								
							
						}	// mRNA old
					}
				}	// mRNA new
			}
		}	// gene old

	}	// gene new
}

function check_self_overlapping(&$arr_gff_structure, $arr_sub_overlapping)
{
	$arr_overlapping_count = array();
	
	// Figure out the type from $arr_sub_overlapping
	if(count($arr_sub_overlapping[0]) == 2)
	{
		$type = 'gene';
	}
	else
	{
		foreach(array('mRNA','exon','CDS') as $type)
		{
			if(isset($arr_gff_structure[$type][$arr_sub_overlapping[0][0]][$arr_sub_overlapping[0][1]]))
			{
				break;
			}
		}
	}
	
	for($i=0; $i<count($arr_sub_overlapping); $i++)
	{
		$int_overlapping_count = 0;
	
		for($j=0; $j<count($arr_sub_overlapping); $j++)
		{	
			if($i == $j) {continue;}
			
			if($type == 'gene')
			{
				$f1_start = $arr_gff_structure[$type][$arr_sub_overlapping[$i][0]][3];
				$f1_end = $arr_gff_structure[$type][$arr_sub_overlapping[$i][0]][4];
				$f2_start = $arr_gff_structure[$type][$arr_sub_overlapping[$j][0]][3];
				$f2_end = $arr_gff_structure[$type][$arr_sub_overlapping[$j][0]][4];
			}
			else
			{
				$f1_start = $arr_gff_structure[$type][$arr_sub_overlapping[$i][0]][$arr_sub_overlapping[$i][1]][3];
				$f1_end = $arr_gff_structure[$type][$arr_sub_overlapping[$i][0]][$arr_sub_overlapping[$i][1]][4];
				$f2_start = $arr_gff_structure[$type][$arr_sub_overlapping[$j][0]][$arr_sub_overlapping[$j][1]][3];
				$f2_end = $arr_gff_structure[$type][$arr_sub_overlapping[$j][0]][$arr_sub_overlapping[$j][1]][4];
			}
			
			if(test_overlapping($f1_start, $f1_end, $f2_start, $f2_end, $len))
			{
				$int_overlapping_count++;
			}
		}
		
		if($type == 'gene')
		{
			$arr_overlapping_count[$arr_sub_overlapping[$i][0]] = $int_overlapping_count;
		}
		else
		{
			$arr_overlapping_count[$arr_sub_overlapping[$i][0]][$arr_sub_overlapping[$i][1]] = $int_overlapping_count;
		}
	}
	
	return $arr_overlapping_count;
}

function check_split(&$arr_new_gff_structure, &$arr_old_overlapping, &$arr_new_statistics_results)
{
	// Checking split
	
	global $output_dir;
	
	$fp = fopen($output_dir.'split(CDS).txt', 'w');
	$fp_UTR = fopen($output_dir.'split(UTR).txt', 'w');
	fputs($fp, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n"); // header
	fputs($fp_UTR, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n"); // header

	foreach($arr_old_overlapping['mRNA'] as $str_old_gene_id=>$arr_old_overlapped_mRNA)
	{
		// mRNA
		foreach($arr_old_overlapped_mRNA as $str_old_mRNA_id=>$arr_overlapped_to_new_mRNA_list)
		{
			$arr_new_gene_list = array(); // Input a new mRNA id to query the corresponding gene id
			$arr_self_overlapping_CDS = array();
			$arr_self_overlapping_exon = array();
			$arr_total_self_overlapped_new_CDS = array();
			$arr_total_self_overlapped_new_exon = array();
			$arr_candidate_overlapped_new_mRNA = array();
			if(count($arr_overlapped_to_new_mRNA_list)>=2)	// Split condition: >= 2 new mRNAs (candidate) are overlapped to an old mRNA
			{
				foreach($arr_overlapped_to_new_mRNA_list as $arr_new_gene_mRNA_ids) // $arr_new_gene_mRNA_ids[0]: overlapped new gene id, [1]: overlapped new mRNA id, [2]: length
				{
					$arr_new_gene_list[$arr_new_gene_mRNA_ids[1]] = $arr_new_gene_mRNA_ids[0];
					$arr_candidate_overlapped_new_mRNA[$arr_new_gene_mRNA_ids[1]] = 0;
				}
			}
			
			if(isset($arr_old_overlapping['exon'][$str_old_mRNA_id]))
			{
				foreach($arr_old_overlapping['exon'][$str_old_mRNA_id] as $str_old_exon_id=>$arr_overlapped_to_new_exon_list)
				{
					// Split condition 2: Overlapping only CDS not UTR
					$arr_self_overlapping_exon[$str_old_exon_id] = check_self_overlapping($arr_new_gff_structure, $arr_overlapped_to_new_exon_list);
					
					foreach($arr_overlapped_to_new_exon_list as $arr_new_mRNA_exon_ids) // $arr_new_gene_mRNA_ids[0]: overlapped new mRNA id, [1]: overlapped new exon id, [2]: length
					{
						$arr_candidate_overlapped_new_mRNA[$arr_new_mRNA_exon_ids[0]] = -1;
					}
				}
			}
			
			if(isset($arr_old_overlapping['CDS'][$str_old_mRNA_id]))
			{
				foreach($arr_old_overlapping['CDS'][$str_old_mRNA_id] as $str_old_CDS_idx=>$arr_overlapped_to_new_CDS_list)
				{
					// Split condition 2: Overlapping only CDS not UTR
					$arr_self_overlapping_CDS[$str_old_CDS_idx] = check_self_overlapping($arr_new_gff_structure, $arr_overlapped_to_new_CDS_list);
					
					foreach($arr_overlapped_to_new_CDS_list as $arr_new_mRNA_CDS_ids) // $arr_new_mRNA_CDS_ids[0]: overlapped new mRNA id, [1]: overlapped new CDS idx, [2]: length
					{
						$arr_candidate_overlapped_new_mRNA[$arr_new_mRNA_CDS_ids[0]] = 1;
					}
				}
			}

			// $arr_total_self_overlapped_new_CDS = array('new mRNA ID1' => # overlapped CDS, 'new mRNA ID2' => # overlapped CDS, ...)
			foreach($arr_self_overlapping_CDS as $arr_CDS)
			{
				foreach($arr_CDS as $str_new_mRNA_id=>$arr_overlapping_count)
				{
					if(isset($arr_total_self_overlapped_new_CDS[$str_new_mRNA_id]))
					{
						$arr_total_self_overlapped_new_CDS[$str_new_mRNA_id] += array_add($arr_overlapping_count);
					}
					else
					{
						$arr_total_self_overlapped_new_CDS[$str_new_mRNA_id] = array_add($arr_overlapping_count);
					}
				}
			}
			
			if(count($arr_total_self_overlapped_new_CDS) >= 2)	// Split condition 1-2: >= 2 new mRNAs (actual) are overlapped to an old mRNA
			{
				// Chinese: 若Split為真, 當中必定有一條new mRNA其全部的CDS均無self overlapping。故必先確認 $arr_total_self_overlapped_new_CDS 包含有這條全部的CDS均無 \
				// self overlapping的mRNA，才可將其他有CDS self overlapping的mRNA算入split當中
				// English: If mRNA split is true, there must be a new mRNA whose all CDS is NOT self overlapping. \
				// So, the first thing should be confirmed is that $arr_total_self_overlapped_new_CDS contains the mRNA without any self overlapping CDS. \
				// Then, consider another mRNAs with CDS self overlapping (isoforms) into the group of split.
			
				$is_hit_the_mRNA_without_any_CDS_self_overlapping = false;	// If any CDS self overlapping, it means that there are isoforms overlapping.
			
				foreach($arr_total_self_overlapped_new_CDS as $int_total_self_overlapped_new_CDS)
				{
					if($int_total_self_overlapped_new_CDS == 0)
					{
						$is_hit_the_mRNA_without_any_CDS_self_overlapping = true;
					}
				}
				
				if($is_hit_the_mRNA_without_any_CDS_self_overlapping)
				{
					foreach($arr_total_self_overlapped_new_CDS as $str_new_mRNA_id=>$int_total_self_overlapped_new_CDS)
					{
						$arr_new_statistics_results['gene'][$arr_new_gene_list[$str_new_mRNA_id]]['split(CDS)'] = 1;
						$arr_new_statistics_results['mRNA'][$arr_new_gene_list[$str_new_mRNA_id]][$str_new_mRNA_id]['split(CDS)'] = 1;
						fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t".$arr_new_gene_list[$str_new_mRNA_id]."\t$str_new_mRNA_id\n");
					}
				}
			}
			
			// For identification of split cases with UTR overlapping
			$is_UTR_overlapping = false;
			foreach($arr_candidate_overlapped_new_mRNA as $u)
			{
				if($u == -1)
				{
					$is_UTR_overlapping = true;
				}
			}
			if($is_UTR_overlapping)
			{
				foreach($arr_self_overlapping_exon as $arr_exon)
				{
					foreach($arr_exon as $str_new_mRNA_id=>$arr_overlapping_count)
					{
						if(isset($arr_total_self_overlapped_new_exon[$str_new_mRNA_id]))
						{
							$arr_total_self_overlapped_new_exon[$str_new_mRNA_id] += array_add($arr_overlapping_count);
						}
						else
						{
							$arr_total_self_overlapped_new_exon[$str_new_mRNA_id] = array_add($arr_overlapping_count);
						}
					}
				}
				
				if(count($arr_total_self_overlapped_new_exon) >= 2)
				{
					$is_hit_the_mRNA_without_any_exon_self_overlapping = false;
				
					foreach($arr_total_self_overlapped_new_exon as $int_total_self_overlapped_new_exon)
					{
						if($int_total_self_overlapped_new_exon == 0)
						{
							$is_hit_the_mRNA_without_any_exon_self_overlapping = true;
						}
					}
					
					if($is_hit_the_mRNA_without_any_exon_self_overlapping)
					{
						foreach($arr_total_self_overlapped_new_exon as $str_new_mRNA_id=>$int_total_self_overlapped_new_exon)
						{
							$arr_new_statistics_results['gene'][$arr_new_gene_list[$str_new_mRNA_id]]['split(UTR)'] = 1;
							$arr_new_statistics_results['mRNA'][$arr_new_gene_list[$str_new_mRNA_id]][$str_new_mRNA_id]['split(UTR)'] = 1;
							fputs($fp_UTR, "$str_old_gene_id\t$str_old_mRNA_id\t".$arr_new_gene_list[$str_new_mRNA_id]."\t$str_new_mRNA_id\n");
						}
					}
				}
			}
			
		} // Loop of $arr_old_overlapped_mRNA
	}
	
	fclose($fp);
	fclose($fp_UTR);
}

function check_merge(&$arr_new_overlapping, &$arr_new_statistics_results)
{
	// Checking merge
	global $output_dir;

	$fp = fopen($output_dir.'merge(CDS).txt', 'w');
	$fp_UTR = fopen($output_dir.'merge(UTR).txt', 'w');
	fputs($fp, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n"); // header
	fputs($fp_UTR, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n"); // header

	foreach($arr_new_overlapping['mRNA'] as $str_new_gene_id=>$arr_new_overlapped_mRNA)
	{
		// mRNA
		foreach($arr_new_overlapped_mRNA as $str_new_mRNA_id=>$arr_overlapped_to_old_mRNA_list)
		{
			$arr_candidate_overlapped_old_mRNA = array();	// This array is used for saving candidate old's mRNA IDs, due to some of them maybe possible overlap on intron or UTR regions which need to be identified and classified. So these candidates will be checked the overlapping status further. Let 0 for an intron overlapping, 1 for CDS, and -1 for UTR.
			$arr_old_gene_list = array();	// Input an old mRNA id to query the corresponding gene id
			if(count($arr_overlapped_to_old_mRNA_list)>=2)	// Merge condition 1-1: >= 2 old mRNAs (candidate) are overlapped to a new mRNA
			{
				foreach($arr_overlapped_to_old_mRNA_list as $arr_old_gene_mRNA_ids) // $arr_old_gene_mRNA_ids[0]: overlapped old gene id, [1]: overlapped old mRNA id, [2]: length
				{
					$arr_candidate_overlapped_old_mRNA[$arr_old_gene_mRNA_ids[1]] = 0; // The first step is to assume that all overlapped regions are intron overlapping and assign to 0.
					$arr_old_gene_list[$arr_old_gene_mRNA_ids[1]] = $arr_old_gene_mRNA_ids[0];
				}
			}			
			if(isset($arr_new_overlapping['exon'][$str_new_mRNA_id])) // The second step is to identify exons (CDS+UTR) overlapping, and assign them to -1 representing UTR overlapping. 
			{
				foreach($arr_new_overlapping['exon'][$str_new_mRNA_id] as $arr_overlapped_to_old_exon_list)
				{
					foreach($arr_overlapped_to_old_exon_list as $arr_old_mRNA_exon_ids) // $arr_old_mRNA_exon_ids[0]: overlapped old mRNA id, [1]: overlapped old exon index, [2]: length
					{
						$arr_candidate_overlapped_old_mRNA[$arr_old_mRNA_exon_ids[0]] = -1; // Merge condition 2-1: An UTR overlapping
					}
				}
			}
			if(isset($arr_new_overlapping['CDS'][$str_new_mRNA_id])) // The third step is to polish the overlapping results which are changed from -1 to 1 to represent CDS overlapping
			{
				foreach($arr_new_overlapping['CDS'][$str_new_mRNA_id] as $arr_overlapped_to_old_CDS_list)
				{
					foreach($arr_overlapped_to_old_CDS_list as $arr_old_mRNA_CDS_ids) // $arr_old_mRNA_CDS_ids[0]: overlapped old mRNA id, [1]: overlapped old CDS index, [2]: length
					{
						$arr_candidate_overlapped_old_mRNA[$arr_old_mRNA_CDS_ids[0]] = 1; // Merge condition 2-2: At least one CDS overlapping for each old mRNA
					}
				}
			}

			$count_overlapped_old_mRNA = 0;
			$arr_old_mRNA_ids = array();
			$arr_old_mRNA_ids_UTR = array();
			foreach($arr_candidate_overlapped_old_mRNA as $str_old_mRNA_id=>$u)
			{
				$count_overlapped_old_mRNA += abs($u); // If any candidate mRNA w/o a CDS overlapping
				
				if($u == 1)
				{
					$arr_old_mRNA_ids[] = $str_old_mRNA_id;
				}
				elseif($u == -1) //$u == -1 indicates an UTR overlapped
				{
					$arr_old_mRNA_ids_UTR[] = $str_old_mRNA_id;
				}
			}
			
			if($count_overlapped_old_mRNA >= 2)	//Merge condition 1-2: >= 2 old mRNAs (actual) are overlapped to a new mRNA
			{
				if(count($arr_old_mRNA_ids) >= 2)
				{
					$arr_new_statistics_results['gene'][$str_new_gene_id]['merge(CDS)'] = 1;
					$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['merge(CDS)'] = 1;
					
					foreach($arr_old_mRNA_ids as $str_id)
					{
						fputs($fp, $arr_old_gene_list[$str_id]."\t$str_id\t$str_new_gene_id\t$str_new_mRNA_id\n");
					}
				}
				
				if(count($arr_old_mRNA_ids_UTR) > 0)
				{
					$arr_new_statistics_results['gene'][$str_new_gene_id]['merge(UTR)'] = 1;
					$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['merge(UTR)'] = 1;
					
					foreach($arr_candidate_overlapped_old_mRNA as $str_id=>$u)
					{
						if($u != 0)
						{
							fputs($fp_UTR, $arr_old_gene_list[$str_id]."\t$str_id\t$str_new_gene_id\t$str_new_mRNA_id\n");
						}
					}
				}
			}
		}
	}
	
	fclose($fp);
	fclose($fp_UTR);
}

function check_add(&$arr_new_gff_structure, &$arr_new_overlapping, &$arr_new_statistics_results)
{
	global $OUTPUT_EXON_CDS_DETAILS;
	global $output_dir;
	
	// Checking add

	$fp = fopen($output_dir.'add.txt', 'w');
	fputs($fp, "New Gene ID\tNew mRNA ID\n"); // header
	
	if($OUTPUT_EXON_CDS_DETAILS)
	{
		$fp_exon_cds = fopen($output_dir.'exon_cds_add.txt', 'w');
		fputs($fp_exon_cds, "New Gene ID\tNew mRNA ID\tNew exon ID\tNew CDS index\n"); // header
	}

	// gene
	foreach($arr_new_gff_structure['gene'] as $str_new_gene_id=>$arr_new_gene_feature)
	{
		if(!isset($arr_new_overlapping['gene'][$str_new_gene_id]))	// Add condition in gene level: No overlapping with the old at the same coordinate
		{
			$arr_new_statistics_results['gene'][$str_new_gene_id]['add'] = 1;
			fputs($fp, "$str_new_gene_id\t\n");
			
			foreach($arr_new_gff_structure['mRNA'][$str_new_gene_id] as $str_new_mRNA_id=>$arr_new_mRNA_feature)
			{
				$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['add'] = 1; // If a gene add found, the corresponding mRNAs should be add, too
				fputs($fp, "$str_new_gene_id\t$str_new_mRNA_id\n");
				
				foreach($arr_new_gff_structure['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_new_exon_feature)
				{
					$arr_new_statistics_results['exon'][$str_new_mRNA_id][$str_new_exon_id]['add'] = 1; // If an mRNA add found, the corresponding exons/CDSs should be add, too
					if($OUTPUT_EXON_CDS_DETAILS)
					{
						fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t$str_new_exon_id\t\n");
					}
				}
				
				foreach($arr_new_gff_structure['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_new_CDS_feature)
				{
					$arr_new_statistics_results['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]['add'] = 1; // If an mRNA add found, the corresponding exons/CDSs should be add, too
					if($OUTPUT_EXON_CDS_DETAILS)
					{
						fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t\t$str_new_CDS_idx\n");
					}
				}
			}
		}
		else
		{
			// mRNA
			if(isset($arr_new_gff_structure['mRNA'][$str_new_gene_id]))
			{
				foreach($arr_new_gff_structure['mRNA'][$str_new_gene_id] as $str_new_mRNA_id=>$arr_new_mRNA_feature)
				{
					if(!isset($arr_new_overlapping['mRNA'][$str_new_gene_id][$str_new_mRNA_id]))
					{
						$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['add'] = 1;
						fputs($fp, "$str_new_gene_id\t$str_new_mRNA_id\n");
						
						foreach($arr_new_gff_structure['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_new_exon_feature)
						{
							$arr_new_statistics_results['exon'][$str_new_mRNA_id][$str_new_exon_id]['add'] = 1; // If an mRNA add found, the corresponding exons/CDSs should be add, too
							if($OUTPUT_EXON_CDS_DETAILS)
							{
								fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t$str_new_exon_id\t\n");
							}
						}
						
						foreach($arr_new_gff_structure['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_new_CDS_feature)
						{
							$arr_new_statistics_results['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]['add'] = 1; // If an mRNA add found, the corresponding exons/CDSs should be add, too
							if($OUTPUT_EXON_CDS_DETAILS)
							{
								fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t\t$str_new_CDS_idx\n");
							}
						}

					}
					else
					{
						// exon & CDS
						foreach($arr_new_gff_structure['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_new_exon_feature)
						{
							if(!isset($arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id]))
							{
								$arr_new_statistics_results['exon'][$str_new_mRNA_id][$str_new_exon_id]['add'] = 1;
								if($OUTPUT_EXON_CDS_DETAILS)
								{
									fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t$str_new_exon_id\t\n");
								}
							}
						}
						
						foreach($arr_new_gff_structure['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_new_CDS_feature)
						{
							if(!isset($arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]))
							{
								$arr_new_statistics_results['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]['add'] = 1;
								if($OUTPUT_EXON_CDS_DETAILS)
								{
									fputs($fp_exon_cds, "$str_new_gene_id\t$str_new_mRNA_id\t\t$str_new_CDS_idx\n");
								}
							}
						}
					}
				}
			}
		}
	}
	
	fclose($fp);
	if($OUTPUT_EXON_CDS_DETAILS){fclose($fp_exon_cds);}
}

function check_modified_within_boundary_coordinates(&$arr_old_gff_structure, &$arr_new_gff_structure, &$arr_new_overlapping, &$arr_new_statistics_results)
{
	// Checking modified within boundary coordinates
	global $output_dir;
	
	$fp = fopen($output_dir.'modified_within_boundary_coordinates.txt', 'w');
	fputs($fp, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\tReason\n"); // header

	// Checking mRNAs should be the first and then check genes
	foreach($arr_new_overlapping['mRNA'] as $str_new_gene_id=>$arr_new_overlapped_mRNA)
	{
		// mRNA
		foreach($arr_new_overlapped_mRNA as $str_new_mRNA_id=>$arr_overlapped_to_old_mRNA_list)
		{
			foreach($arr_overlapped_to_old_mRNA_list as $arr_old_gene_mRNA_ids)
			{
				$str_old_gene_id = $arr_old_gene_mRNA_ids[0];
				$str_old_mRNA_id = $arr_old_gene_mRNA_ids[1];
				
				if($arr_old_gff_structure['mRNA'][$str_old_gene_id][$str_old_mRNA_id][3] == $arr_new_gff_structure['mRNA'][$str_new_gene_id][$str_new_mRNA_id][3] && $arr_old_gff_structure['mRNA'][$str_old_gene_id][$str_old_mRNA_id][4] == $arr_new_gff_structure['mRNA'][$str_new_gene_id][$str_new_mRNA_id][4])
				{
					if(count($arr_new_gff_structure['exon'][$str_new_mRNA_id]) < count($arr_old_gff_structure['exon'][$str_old_mRNA_id]))	// exon delete
					{
						$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
						fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\texon delete\n");
					}
					
					// exon add, extend, reduce
					foreach($arr_new_statistics_results['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_result_list)
					{
						if($arr_result_list['add'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\texon add\n");
						}
						elseif($arr_result_list['extend'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\texon extend\n");
						}
						elseif($arr_result_list['reduce'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\texon reduce\n");
						}
					}
					
					// CDS add, extend, reduce
					foreach($arr_new_statistics_results['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_result_list)
					{
						if($arr_result_list['add'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\tCDS add\n");
						}
						elseif($arr_result_list['extend'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\tCDS extend\n");
						}
						elseif($arr_result_list['reduce'] == 1)
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['modified_within_boundary_coordinates'] = 1;
							fputs($fp, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\tCDS reduce\n");
						}
					}										
				}
			}
		}
	}
	
	// Checking genes
	foreach($arr_new_overlapping['gene'] as $str_new_gene_id=>$arr_overlapped_to_old_gene_list)
	{
		foreach($arr_overlapped_to_old_gene_list as $arr_old_gene_id)
		{
			$str_old_gene_id = $arr_old_gene_id[0];
			
			if($arr_old_gff_structure['gene'][$str_old_gene_id][3] == $arr_new_gff_structure['gene'][$str_new_gene_id][3] && $arr_old_gff_structure['gene'][$str_old_gene_id][4] == $arr_new_gff_structure['gene'][$str_new_gene_id][4])
			{
				foreach($arr_new_statistics_results['mRNA'][$str_new_gene_id] as $str_new_mRNA_id=>$arr_result_list)
				{
					foreach($arr_old_gff_structure['mRNA'][$str_old_gene_id] as $arr_old_mRNA_feature) // Generally, there is only one mRNA for each MAKER's gene. But I use loop to go over the whole array to prevent that if any excepted cases.
					{
						// Checking the new mRNA length should be equal to the old's. Some new genes contain isoforms with different length.
						if($arr_old_mRNA_feature[3] == $arr_new_gff_structure['mRNA'][$str_new_gene_id][$str_new_mRNA_id][3] && $arr_old_mRNA_feature[4] == $arr_new_gff_structure['mRNA'][$str_new_gene_id][$str_new_mRNA_id][4])
						{
							if($arr_result_list['add'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA add\n");
							}
							elseif($arr_result_list['extend'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA extend\n");
							}
							elseif($arr_result_list['reduce'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA reduce\n");
							}
							elseif($arr_result_list['merge(CDS)'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA merge(CDS)\n");
							}
							elseif($arr_result_list['split(CDS)'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA split(CDS)\n");
							}
							elseif($arr_result_list['modified_within_boundary_coordinates'] == 1)
							{
								$arr_new_statistics_results['gene'][$str_new_gene_id]['modified_within_boundary_coordinates'] = 1;
								fputs($fp, "$str_old_gene_id\t\t$str_new_gene_id\t\tmRNA modified within boundary coordinates\n");
							}
						}
					}					
				}
			}
		}
	}
	
	fclose($fp);
}

function check_extend_reduce(&$arr_old_gff_structure, &$arr_new_gff_structure, &$arr_new_overlapping, &$arr_new_statistics_results)
{
	global $OUTPUT_EXON_CDS_DETAILS;
	
	// Checking extend and reduce
	global $output_dir;
	
	$fp_extend = fopen($output_dir.'extend.txt', 'w');
	$fp_reduce = fopen($output_dir.'reduce.txt', 'w');
	fputs($fp_extend, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n"); // header
	fputs($fp_reduce, "Old Gene ID\tOld mRNA ID\tNew Gene ID\tNew mRNA ID\n");

	if($OUTPUT_EXON_CDS_DETAILS)
	{
		$fp_extend_exon_cds = fopen($output_dir.'exon_cds_extend.txt', 'w');
		$fp_reduce_exon_cds = fopen($output_dir.'exon_cds_reduce.txt', 'w');
		fputs($fp_extend_exon_cds, "Old Gene ID\tOld mRNA ID\tOld exon ID\tOld CDS index\tNew Gene ID\tNew mRNA ID\tNew exon ID\tNew CDS index\n"); // header
		fputs($fp_reduce_exon_cds, "Old Gene ID\tOld mRNA ID\tOld exon ID\tOld CDS index\tNew Gene ID\tNew mRNA ID\tNew exon ID\tNew CDS index\n"); // header
	}
	
	// gene
	foreach($arr_new_gff_structure['gene'] as $str_new_gene_id=>$arr_new_gene_feature)
	{
		if(isset($arr_new_overlapping['gene'][$str_new_gene_id]) && count($arr_new_overlapping['gene'][$str_new_gene_id])==1)	// Extend/Reduce condition 1: Overlapping to only one old (except exon/CDS).
		{
			$str_old_gene_id = $arr_new_overlapping['gene'][$str_new_gene_id][0][0];
			$arr_old_gene_feature = $arr_old_gff_structure['gene'][$str_old_gene_id];
			
			if($arr_new_gene_feature[4]-$arr_new_gene_feature[3] > $arr_old_gene_feature[4]-$arr_old_gene_feature[3])	// Extend condition 2: The new's length > the old's
			{
				$arr_new_statistics_results['gene'][$str_new_gene_id]['extend'] = 1;
				fputs($fp_extend, "$str_old_gene_id\t\t$str_new_gene_id\t\n");
			}
			
			if($arr_new_gene_feature[4]-$arr_new_gene_feature[3] < $arr_old_gene_feature[4]-$arr_old_gene_feature[3])	// Reduce condition 2: The new's length < the old's
			{
				//echo "$str_new_gene_id\n";
				$arr_new_statistics_results['gene'][$str_new_gene_id]['reduce'] = 1;
				fputs($fp_reduce, "$str_old_gene_id\t\t$str_new_gene_id\t\n");
			}
			
			// mRNA
			if(isset($arr_new_gff_structure['mRNA'][$str_new_gene_id]))
			{
				foreach($arr_new_gff_structure['mRNA'][$str_new_gene_id] as $str_new_mRNA_id=>$arr_new_mRNA_feature)
				{
					if(isset($arr_new_overlapping['mRNA'][$str_new_gene_id][$str_new_mRNA_id]) && count($arr_new_overlapping['mRNA'][$str_new_gene_id][$str_new_mRNA_id])==1)	// Extend condition 1: Overlapping to only one old.
					{
						$str_old_mRNA_id = $arr_new_overlapping['mRNA'][$str_new_gene_id][$str_new_mRNA_id][0][1];
						$arr_old_mRNA_feature = $arr_old_gff_structure['mRNA'][$str_old_gene_id][$str_old_mRNA_id];
						
						if($arr_new_mRNA_feature[4]-$arr_new_mRNA_feature[3] > $arr_old_mRNA_feature[4]-$arr_old_mRNA_feature[3])	// Extend condition 2: The new's length > the old's
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['extend'] = 1;
							fputs($fp_extend, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\n");
						}
						
						if($arr_new_mRNA_feature[4]-$arr_new_mRNA_feature[3] < $arr_old_mRNA_feature[4]-$arr_old_mRNA_feature[3])	// Reduce condition 2: The new's length < the old's
						{
							$arr_new_statistics_results['mRNA'][$str_new_gene_id][$str_new_mRNA_id]['reduce'] = 1;
							fputs($fp_reduce, "$str_old_gene_id\t$str_old_mRNA_id\t$str_new_gene_id\t$str_new_mRNA_id\n");
						}
						
						// exon
						foreach($arr_new_gff_structure['exon'][$str_new_mRNA_id] as $str_new_exon_id=>$arr_new_exon_feature)
						{
							//if(isset($arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id]) && count($arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id])==1)
							if(isset($arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id]))
							{
								for($i=0; $i<count($arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id]); $i++)
								{
									$str_old_exon_id = $arr_new_overlapping['exon'][$str_new_mRNA_id][$str_new_exon_id][$i][1];
									$arr_old_exon_feature = $arr_old_gff_structure['exon'][$str_old_mRNA_id][$str_old_exon_id];
									
									if($arr_new_exon_feature[4]-$arr_new_exon_feature[3] > $arr_old_exon_feature[4]-$arr_old_exon_feature[3])
									{
										$arr_new_statistics_results['exon'][$str_new_mRNA_id][$str_new_exon_id]['extend'] = 1;
										if($OUTPUT_EXON_CDS_DETAILS)
										{
											fputs($fp_extend_exon_cds, "$str_old_gene_id\t$str_old_mRNA_id\t$str_old_exon_id\t\t$str_new_gene_id\t$str_new_mRNA_id\t$str_new_exon_id\t\n");
										}
									}
									
									if($arr_new_exon_feature[4]-$arr_new_exon_feature[3] < $arr_old_exon_feature[4]-$arr_old_exon_feature[3])
									{
										$arr_new_statistics_results['exon'][$str_new_mRNA_id][$str_new_exon_id]['reduce'] = 1;
										if($OUTPUT_EXON_CDS_DETAILS)
										{
											fputs($fp_reduce_exon_cds, "$str_old_gene_id\t$str_old_mRNA_id\t$str_old_exon_id\t\t$str_new_gene_id\t$str_new_mRNA_id\t$str_new_exon_id\t\n");
										}
									}
								}
							}
						}
						
						
						// CDS
						foreach($arr_new_gff_structure['CDS'][$str_new_mRNA_id] as $str_new_CDS_idx=>$arr_new_CDS_feature)
						{
							//if(isset($arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]) && count($arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx])==1)
							if(isset($arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]))
							{
								for($i=0; $i<count($arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]); $i++)
								{
									$str_old_CDS_idx = $arr_new_overlapping['CDS'][$str_new_mRNA_id][$str_new_CDS_idx][$i][1];
									$arr_old_CDS_feature = $arr_old_gff_structure['CDS'][$str_old_mRNA_id][$str_old_CDS_idx];
									
									if($arr_new_CDS_feature[4]-$arr_new_CDS_feature[3] > $arr_old_CDS_feature[4]-$arr_old_CDS_feature[3])
									{
										$arr_new_statistics_results['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]['extend'] = 1;
										if($OUTPUT_EXON_CDS_DETAILS)
										{
											fputs($fp_extend_exon_cds, "$str_old_gene_id\t$str_old_mRNA_id\t\t$str_old_CDS_idx\t$str_new_gene_id\t$str_new_mRNA_id\t\t$str_new_CDS_idx\n");
										}
									}
									
									if($arr_new_CDS_feature[4]-$arr_new_CDS_feature[3] < $arr_old_CDS_feature[4]-$arr_old_CDS_feature[3])
									{
										$arr_new_statistics_results['CDS'][$str_new_mRNA_id][$str_new_CDS_idx]['reduce'] = 1;
										if($OUTPUT_EXON_CDS_DETAILS)
										{
											fputs($fp_reduce_exon_cds, "$str_old_gene_id\t$str_old_mRNA_id\t\t$str_old_CDS_idx\t$str_new_gene_id\t$str_new_mRNA_id\t\t$str_new_CDS_idx\n");
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	fclose($fp_extend);
	fclose($fp_reduce);
	if($OUTPUT_EXON_CDS_DETAILS)
	{
		fclose($fp_extend_exon_cds);
		fclose($fp_reduce_exon_cds);
	}
}

// No used
function find_UTR($arr_gff_structure)
{
	$arr_UTR = array();
	
	// gene
	foreach($arr_gff_structure['gene'] as $str_gene_id=>$arr_gene_feature)
	{
		// mRNA
		foreach($arr_gff_structure['mRNA'][$str_gene_id] as $str_mRNA_id=>$arr_mRNA_feature)
		{
			//exon
			foreach($arr_gff_structure['exon'][$str_mRNA_id] as $arr_exon_feature)
			{
				$is_overlapping_to_any_CDS = false;
				foreach($arr_gff_structure['CDS'][$str_mRNA_id] as $arr_CDS_feature)
				{
					if(test_overlapping($arr_exon_feature[3], $arr_exon_feature[4], $arr_CDS_feature[3], $arr_CDS_feature[4], $len))
					{
						$is_overlapping_to_any_CDS = true;
						
						if($arr_exon_feature[3] != $arr_CDS_feature[3])
						{
							// Fields info: 0:Scaffolds ID, 1:Category, 2:Type, 3:Start, 4:End, 5:##, 6:Strand, 7:Phase, 8:Annotation
							$arr_UTR[$str_mRNA_id][] = array($arr_exon_feature[0], $arr_exon_feature[1], 'UTR', $arr_exon_feature[3]+0, $arr_CDS_feature[3]-1, $arr_exon_feature[5], $arr_exon_feature[6], $arr_exon_feature[7], '');
						}
						
						
						if($arr_exon_feature[4] != $arr_CDS_feature[4])
						{
							// Fields info: 0:Scaffolds ID, 1:Category, 2:Type, 3:Start, 4:End, 5:##, 6:Strand, 7:Phase, 8:Annotation
							$arr_UTR[$str_mRNA_id][] = array($arr_exon_feature[0], $arr_exon_feature[1], 'UTR', $arr_CDS_feature[4]+1, $arr_exon_feature[4]+0, $arr_exon_feature[5], $arr_exon_feature[6], $arr_exon_feature[7], '');
						}
					}
				}
				if(!$is_overlapping_to_any_CDS)
				{
					$arr_UTR[$str_mRNA_id][] = array($arr_exon_feature[0], $arr_exon_feature[1], 'UTR', $arr_exon_feature[3]+0, $arr_exon_feature[4]+0, $arr_exon_feature[5], $arr_exon_feature[6], $arr_exon_feature[7], '');
				}
			}
		}
	}
	
	return $arr_UTR;
}

function init_statistics_results(&$arr_new_gff_structure)
{
	$arr_statistics_structure = array();
	
	foreach($arr_new_gff_structure['gene'] as $gene_id=>$arr_content)
	{
		$arr_statistics_structure['gene'][$gene_id] = array('add'=>0, 'extend'=>0, 'reduce'=>0, 'merge(CDS)'=>0, 'split(CDS)'=>0, 'merge(UTR)'=>0, 'split(UTR)'=>0, 'modified_within_boundary_coordinates'=>0);
	}
	
	foreach($arr_new_gff_structure['mRNA'] as $gene_id=>$arr_mRNA_content)
	{
		foreach($arr_mRNA_content as $mRNA_id=>$arr_content)
		{
			$arr_statistics_structure['mRNA'][$gene_id][$mRNA_id] = array('add'=>0, 'extend'=>0, 'reduce'=>0, 'merge(CDS)'=>0, 'split(CDS)'=>0, 'merge(UTR)'=>0, 'split(UTR)'=>0, 'modified_within_boundary_coordinates'=>0);
		}
	}
	
	foreach($arr_new_gff_structure['exon'] as $mRNA_id=>$arr_exon_content)
	{
		foreach($arr_exon_content as $exon_id=>$arr_content)
		{
			$arr_statistics_structure['exon'][$mRNA_id][$exon_id] = array('add'=>0, 'extend'=>0, 'reduce'=>0);
		}
	}

	foreach($arr_new_gff_structure['CDS'] as $mRNA_id=>$arr_CDS_content)
	{
		foreach($arr_CDS_content as $CDS_idx=>$arr_content)
		{
			$arr_statistics_structure['CDS'][$mRNA_id][$CDS_idx] = array('add'=>0, 'extend'=>0, 'reduce'=>0);
		}
	}
		
	return $arr_statistics_structure;
}

function make_structure_index(&$arr_gff_structure)
{
	$arr_feature_idx = array();

	foreach($arr_gff_structure as $type=>$tt)
	{
		if($type == 'gene')
		{
			foreach($tt as $feature_ID=>$arr_feature)
			{
				$arr_feature_idx[$arr_feature[0]][$arr_feature[2]][] = $feature_ID;
			}
		}
		else
		{
			foreach($tt as $feature_parent_ID=>$arr_feature_ID)
			{
				foreach($arr_feature_ID as $feature_ID=>$arr_feature)
				{
					$arr_feature_idx[$arr_feature[0]][$arr_feature[2]][] = array($feature_parent_ID, $feature_ID);
				}
			}
		}
	}
	
	return $arr_feature_idx;
}


function make_gff_structure(&$arr_gff)
{
	$arr_features = array();
	$gene_tmp = array();
	$mRNA_tmp = array();
	$exon_tmp = array();
	$CDS_tmp = array();
	
	foreach($arr_gff as $line)
	{
		if($line == '')  {continue;}
		if(substr($line, 0, 1) == '#')	{continue;}
		// Fields info: 0:Scaffolds ID, 1:Category, 2:Type, 3:Start, 4:End, 5:##, 6:Strand, 7:Phase, 8:Annotation
		$line_content = explode("\t", $line);
		
		// Get features' ID and parent ID
		if(preg_match('/ID=([\w-]+);*/', $line_content[8], $m))
			$feature_ID = $m[1];
		else
			$feature_ID = '';
		
		if(preg_match('/Parent=([\w-]+);*/', $line_content[8], $n))
			$feature_parent_ID = $n[1];
		else
			$feature_parent_ID = '';
		
		// Put features into arrays according to type
		switch ($line_content[2])
		{
			case 'gene':
				$gene_tmp[$feature_ID] = $line_content;
				break;
				
			case 'mRNA':
				$mRNA_tmp[$feature_parent_ID][$feature_ID] = $line_content;
				break;
			
			case 'exon':
				$exon_tmp[$feature_parent_ID][$feature_ID] = $line_content;
				break;

			case 'CDS':
				$CDS_tmp[$feature_parent_ID][] = $line_content;
				break;			
		}
		
	}
		
	$arr_features = array('gene'=>$gene_tmp, 'mRNA'=>$mRNA_tmp, 'exon'=>$exon_tmp, 'CDS'=>$CDS_tmp);
	
	return $arr_features;
	/******************************
	The structure of $arr_features: 
	-------------------------------
	genes:
		$gene_tmp = array('GENE_ID1'=>array('col1', ..., 'col9'), ...);
		['GENE_ID1'][0-8]
		['GENE_ID2'][0-8]
	mRNAs:
		$mRNA_tmp = array('GENE_ID1'=>array('mRNA_ID1'=>array('col1', ..., 'col9'), 'mRNA_ID2'=>array('col1', ..., 'col9'), ...), 'GENE_ID2'=>...)
		['GENE_ID1']['mRNA_ID1'][0-8]
		['GENE_ID1']['mRNA_ID2'][0-8]
		['GENE_ID2']['mRNA_ID1'][0-8]
		['GENE_ID2']['mRNA_ID2'][0-8]
	exons:
		$exon_tmp = array('mRNA_ID1'=>array('exon_ID1'=>array('col1', ..., 'col9'), 'exon_ID2'=>array('col1', ..., 'col9'), ...), 'mRNA_ID2'=>...)
		['mRNA_ID1']['exon_ID1'][0-8]
		['mRNA_ID1']['exon_ID2'][0-8]
		['mRNA_ID2']['exon_ID1'][0-8]
		['mRNA_ID2']['exon_ID2'][0-8]
	CDSs: (CDSs DO NOT have unique ID from WebApollo output.)
		$CDS_tmp = array('mRNA_ID1'=>array(0=>array('col1', ..., 'col9'), 1=>array('col1', ..., 'col9'), ...), 'mRNA_ID2'=>...)
		['mRNA_ID1'][0][0-8]
		['mRNA_ID1'][1][0-8]
		['mRNA_ID2'][0][0-8]
		['mRNA_ID2'][1][0-8]
	return values:
		$arr_features = array('gene'=>$gene_tmp, 'mRNA'=>$mRNA_tmp, 'exon'=>$exon_tmp, 'CDS'=>$CDS_tmp);
	******************************/
}

function test_overlapping($a_start, $a_end, $b_start, $b_end, &$len)
{
	if(($a_start<$b_start && $a_end<$b_start) || ($a_start>$b_end && $a_end>$b_end))
	{
		$len = 0;
		return false;
	}
	else
	{
		$len = min($a_end, $b_end) - max($a_start, $b_start) + 1;	//$len = the min of old/new end site minus the max of old/new start site

		return true;
	}
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
			
		}
		fclose($fp_anno_gff);
	
		return $arr_gff_content;
	}
	catch (Exception $e)
	{
		echo nl2br('Error: ',  $e->getMessage(), "\n");
		exit;
	}
}

function array_add($arr)
{
	$total = 0;
	foreach($arr as $a)
	{
		$total += $a;
	}
	
	return $total;
}

function summary(&$arr_old_gff_structure, &$arr_new_gff_structure, &$arr_new_overlapping, &$arr_new_statistics_results)
{
	// Generating summary report
	
	$arr_input_info = array('old'=>array('gene'=>0, 'mRNA'=>0, 'exon'=>0, 'CDS'=>0), 'new'=>array('gene'=>0, 'mRNA'=>0, 'exon'=>0, 'CDS'=>0));
	$arr_overlapping_info = array('gene'=>0, 'mRNA'=>0, 'exon'=>0, 'CDS'=>0);
	$arr_features_changed_info = array('gene'=>array('add'=>0, 'extend'=>0, 'reduce'=>0, 'merge(CDS)'=>0, 'merge(UTR)'=>0, 'split(CDS)'=>0, 'split(UTR)'=>0, 'modified within boundary coordinates'=>0, 'features with any changed'=>0, 'annotation changed only'=>0), 
									   'mRNA'=>array('add'=>0, 'extend'=>0, 'reduce'=>0, 'merge(CDS)'=>0, 'merge(UTR)'=>0, 'split(CDS)'=>0, 'split(UTR)'=>0, 'modified within boundary coordinates'=>0, 'features with any changed'=>0, 'annotation changed only'=>0), 
									   'exon'=>array('add'=>0, 'extend'=>0, 'reduce'=>0, 'features with any changed'=>0, 'no changed'=>0), 
									   'CDS'=>array('add'=>0, 'extend'=>0, 'reduce'=>0, 'features with any changed'=>0, 'no changed'=>0)
									  );
	global $PATH_old_gff;
	global $PATH_new_gff;
	
	// Input info
	// Original models
	foreach($arr_old_gff_structure['mRNA'] as $str_old_gene_id=>$arr_old_mRNA_list)
	{
		$arr_input_info['old']['gene']++;
		foreach($arr_old_mRNA_list as $str_old_mRNA_id=>$arr_old_mRNA_feature)
		{
			$arr_input_info['old']['mRNA']++;
		}
	}
	
	foreach($arr_old_gff_structure['exon'] as $str_old_mRNA_id=>$arr_old_exon_list)
	{
		foreach($arr_old_exon_list as $str_old_exon_id=>$arr_old_exon_feature)
		{
			$arr_input_info['old']['exon']++;
		}
	}
	
	foreach($arr_old_gff_structure['CDS'] as $str_old_mRNA_id=>$arr_old_CDS_list)
	{
		foreach($arr_old_CDS_list as $str_old_CDS_idx=>$arr_old_CDS_feature)
		{
			$arr_input_info['old']['CDS']++;
		}
	}
	// Curated features
	foreach($arr_new_gff_structure['mRNA'] as $str_new_gene_id=>$arr_new_mRNA_list)
	{
		$arr_input_info['new']['gene']++;
		foreach($arr_new_mRNA_list as $str_new_mRNA_id=>$arr_new_mRNA_feature)
		{
			$arr_input_info['new']['mRNA']++;
		}
	}
	
	foreach($arr_new_gff_structure['exon'] as $str_new_mRNA_id=>$arr_new_exon_list)
	{
		foreach($arr_new_exon_list as $str_new_exon_id=>$arr_new_exon_feature)
		{
			$arr_input_info['new']['exon']++;
		}
	}
	
	foreach($arr_new_gff_structure['CDS'] as $str_new_mRNA_id=>$arr_new_CDS_list)
	{
		foreach($arr_new_CDS_list as $str_new_CDS_idx=>$arr_new_CDS_feature)
		{
			$arr_input_info['new']['CDS']++;
		}
	}
	
	
	// Overlapping info
	foreach($arr_new_overlapping['mRNA'] as $str_new_gene_id=>$arr_new_overlapped_mRNA)
	{
		$arr_overlapping_info['gene']++;
		foreach($arr_new_overlapped_mRNA as $str_new_mRNA_id=>$arr_overlapped_to_old_mRNA_list)
		{
			$arr_overlapping_info['mRNA']++;
		}
	}
		
	foreach($arr_new_overlapping['exon'] as $str_new_mRNA_id=>$arr_new_overlapped_exon)
	{
		foreach($arr_new_overlapped_exon as $str_new_exon_id=>$arr_overlapped_to_old_exon_list)
		{
			$arr_overlapping_info['exon']++;
		}
	}
	
	foreach($arr_new_overlapping['CDS'] as $str_new_mRNA_id=>$arr_new_overlapped_CDS)
	{
		foreach($arr_new_overlapped_CDS as $str_new_CDS_idx=>$arr_overlapped_to_old_CDS_list)
		{
			$arr_overlapping_info['CDS']++;
		}
	}
	
	
	// Features changed info
	// gene
	foreach($arr_new_statistics_results['gene'] as $str_new_gene_id=>$arr_new_gene_statistics)
	{
		$count_changed = 0;
		foreach($arr_new_gene_statistics as $str_changed_type=>$is_changed)
		{
			$arr_features_changed_info['gene'][preg_replace('/_/', ' ', $str_changed_type)] += $is_changed;
			
			$count_changed += $is_changed;
		}
		if($count_changed == 0)
		{
			$arr_features_changed_info['gene']['annotation changed only']++;
		}
		else
		{
			$arr_features_changed_info['gene']['features with any changed']++;
		}
	}
	// mRNA, exon, CDS
	foreach(array('mRNA','exon','CDS') as $t)
	{
		foreach($arr_new_statistics_results[$t] as $str_parent_id=>$arr_data1)
		{
			foreach($arr_data1 as $str_id=>$arr_statistics)
			{
					$count_changed = 0;
					foreach($arr_statistics as $str_changed_type=>$is_changed)
					{
						$arr_features_changed_info[$t][preg_replace('/_/', ' ', $str_changed_type)] += $is_changed;
						
						$count_changed += $is_changed;
					}
					if($count_changed == 0)
					{
						if($t == 'mRNA')
						{
							$arr_features_changed_info[$t]['annotation changed only']++;
						}
						else
						{
							$arr_features_changed_info[$t]['no changed']++;
						}
					}
					else
					{
						$arr_features_changed_info[$t]['features with any changed']++;
					}
			}
		}
	}
	
	// Report output
	//echo nl2br("Done.\n\n");
	echo nl2br("**************************************************************\n");
    echo str_repeat('&nbsp;', 43) . nl2br("Summary Report\n");
	echo nl2br("**************************************************************\n");
	echo nl2br("Input information:\n");
	echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;Original models (".basename($PATH_old_gff).")\n");
	foreach($arr_input_info['old'] as $str_type=>$int_count)
	{
		echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$str_type: $int_count\n");
	}
	echo nl2br("\n&nbsp;&nbsp;&nbsp;&nbsp;Curated features (".basename($PATH_new_gff).")\n");
	foreach($arr_input_info['new'] as $str_type=>$int_count)
	{
		echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$str_type: $int_count\n");
	}
	
	echo nl2br("\nOverlapping info:\n");
	echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;# curated features with overlapping / # total original models (%)\n");
	foreach($arr_overlapping_info as $str_type=>$int_count)
	{
		echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$str_type: $int_count/".$arr_input_info['old'][$str_type]." (".sprintf("%.2f", $int_count/$arr_input_info['old'][$str_type]*100)."%)\n");
	}

	echo nl2br("\nFeatures changed info:\n");
	foreach($arr_features_changed_info as $str_type=>$arr_changed_type)
	{
		echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;$str_type\n");
		foreach($arr_changed_type as $str_changed_type=>$int_count)
		{
			if($str_changed_type == 'features with any changed' || $str_changed_type == 'annotation changed only' || $str_changed_type == 'no changed')
			{
				echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$str_changed_type (count at once for each feature): $int_count (# / # total $str_type"."s * 100% = ".sprintf("%.2f", $int_count/$arr_input_info['new'][$str_type]*100)."%)\n");
			}
			else
			{
				echo nl2br("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$str_changed_type: $int_count\n");
			}
		}
	}
		
	echo nl2br("\n");
}

function zip_archive($now_time)
{
	global $output_dir;
	$zip = new ZipArchive();
	$zip_filename_prefix = 'gff-cmp-cat_';
	$zip_filename = $zip_filename_prefix . $now_time . '.zip';

	if ($zip->open($output_dir.$zip_filename, ZIPARCHIVE::CREATE)!==TRUE) {
		exit("cannot open <$output_dir.$zip_filename>\n");
	}

	$archived_files = glob($output_dir . '*.txt');
    foreach ($archived_files as $archived_file) {
		$zip->addFile($archived_file, basename($archived_file));
    }
	
	$zip->close();
}

function remove_all_txt_files()
{
	global $output_dir;
	
	$txt_files = glob($output_dir . '*.txt');
    foreach ($txt_files as $txt_file) {
		unlink($txt_file);
    }
}

?>

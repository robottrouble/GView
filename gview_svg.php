#!/usr/bin/php
<?php
$startDir = dirname($argv[0]);
gcode_to_svg($argv[1], $startDir);

function gcode_to_svg($inFile, $startDir)
{
        if(($fh = fopen($inFile, 'r')) === FALSE) {
          exit;
        }
		
        $cur['X']="";
        $cur['Y']="";
        $last['X']="";
        $last['Y']="";
        $last['Z']="";
        $cur['Z']="";
        $max['X']=0;
        $max['Y']=0;
        $max['Z']="";
        $min['X']=0;
        $min['Y']=0;
        $min['Z']="";
        $resolution = 3;


        $xskew = 0;
        $yskew = 0;
        $xmag = 1;
        $ymag = $xmag;

        $layers=array();
        while($fh !== FALSE && !feof($fh))
        {
                $buf = fgets($fh, 1024);
                $bufsplit = explode(" ", $buf);

                if(is_array($bufsplit) && sizeof($bufsplit) > 0 && preg_match("/^G[1|0]$/", $bufsplit[0]))
                {
                        for($i=1; $i<sizeof($bufsplit); $i++)
                        {
                                $curcode = substr($bufsplit[$i], 0, 1);
                                $val = substr($bufsplit[$i], 1, strlen($bufsplit[$i]));
                                $cur[$curcode] = $val;
                        }

                        $displayLine = true;

                        if($cur['Z'] != $last['Z'])
                                $displayLine = false;

			$layers[$cur['Z']][] = array( 'X' => $cur['X'], 'Y' => $cur['Y'], 'Z' => $cur['Z'], 'display' => $displayLine );

                        if($min['X'] == '' || $cur['X'] < $min['X'])
                        {
                                $min['X'] = $cur['X'];
                        }

                        if($min['Y'] == '' || $cur['Y'] < $min['Y'])
                        {
                                $min['Y'] = $cur['Y'];
                        }

                        $last['X'] = $cur['X'];
                        $last['Y'] = $cur['Y'];
                        $last['Z'] = $cur['Z'];
                }
        }

        if(!is_numeric($min['X']))
                $min['X'] = 0;

        if(!is_numeric($min['Y']))
                $min['Y'] = 0;

        if($min['X'] < 0)
                $min['X'] = $min['X'] * -1;

        if($min['Y'] < 0)
                $min['Y'] = $min['Y'] * -1;

        $min['X'] += 10;
        $min['Y'] += 10;

        $layerCount = 1;
        ksort($layers);
		$svg_template = file_get_contents($startDir . '/gview_template.svg');
        foreach($layers as $layer => $coords)
        {
                if(sizeof($coords) < 4)
                        continue;

	
                $last['X'] = '';
                $last['Y'] = '';
	
                $outParts = explode(".", $inFile);
                array_pop($outParts);
                $dirName = implode(".", $outParts) . "/";
                if(!is_dir($dirName))
                {
                        mkdir($dirName);
                }
                $outParts[] = $layerCount;
                $outParts[] = 'svg';
                $outFile = $dirName . basename(implode(".", $outParts));

                if(!$outFileH = fopen($outFile, "w+"))
                {
                        echo "Failed to create file $outFile";
                        return 1;
                }

				$outLines = "";

                foreach($coords as $cur)
                {
                        if($last['X'] != '' && $last['Y'] != '')
                        {
                                $color="255,0,0";

                                if($cur['display'])
                                        $line = sprintf(' <line x1="%s" y1="%s" x2="%s" y2="%s" style="stroke:rgb(%s);stroke-width:.1%%"/>', $last['X'] * $resolution, $last['Y'] * $resolution, $cur['X'] * $resolution, $cur['Y'] * $resolution, $color);
                                $outLines .= $line . "\n";
                        }
                        $last['X'] = $cur['X'];
                        $last['Y'] = $cur['Y'];
                }

				$outputSVG = str_replace("%%DRAWING%%", $outLines, $svg_template);

                fputs($outFileH, $outputSVG);

                $layerCount++;
        }

		$html_template = file_get_contents($startDir . '/gview_template.html');

        $outParts = explode(".", basename($inFile));
		array_pop($outParts);
		$dirName = implode(".", $outParts) . "/";
		$filePrefix = $dirName . basename(implode(".", $outParts)) . ".";


		$outputHTML = str_replace("%%TOTAL_LAYERS%%", $layerCount - 1, $html_template);
		$outputHTML = str_replace("%%FILE_PREFIX%%", $filePrefix, $outputHTML);
        $outParts = explode(".", $inFile);
		array_pop($outParts);
		$dirName = dirname($inFile);
		$outParts[] = 'html';
		$outFile = $dirName . "/" . basename(implode(".", $outParts));


		if(!$htmlFH = fopen($outFile, "w+"))
		{
			echo "Failed to create file $outFile";
			return 1;
		}
																							  	
		fputs($htmlFH, $outputHTML);
        return true;
}

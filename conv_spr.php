<?php

function FindFiles ($location='', $fileregex='') 
{
    if (!$location or !is_dir($location) or !$fileregex) {
        return false;
    }
    $matchedfiles = array();
    $all = opendir($location);
    while ($file = readdir($all)) {
        if (is_dir($location.'/'.$file) and $file <> ".." and $file <> ".") {
            $subdir_matches = FindFiles($location.'/'.$file,$fileregex);
            $matchedfiles = array_merge($matchedfiles,$subdir_matches);
            unset($file);
        }
        elseif (!is_dir($location.'/'.$file)) {
            if (preg_match($fileregex,$file)) {
                array_push($matchedfiles,$location.'/'.$file);
            }
        }
    }
    closedir($all);
    unset($all);
    return $matchedfiles;
} 

function ProcessDir ( $dir )
{
    $files = FindFiles ($dir, '/^([sS]|[cC]).*\.([pP][nN][gG])$/');
    $count = count($files);
    echo "Dir: $dir, Files: $count\n";
    for ($i=0; $i < $count; $i++) 
    {
        ProcessFile($files[$i]);
    }
    echo "Dir: $dir, Files: $count\n";
}

function BitsToString ( $byte )
{
    $res = "^B";
    $mask = 128;
    while ($mask > 0) {
        if ($byte & $mask) $res .= "1"; else $res .= "0";
        $mask = $mask >> 1;
    }
    return $res;
}

function GetLumi ( $rgb )
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $lumi = sqrt($r*$r + $g*$g + $b*$b);
    return $lumi;
}

function GetImgByte ( $im, $bn, $y )
{
    $x = $bn * 8;
    $res = 0;
    for ($i=0; $i<8; $i++, $x++)
    {
        $res = $res >> 1;
        $rgb = imagecolorat($im, $x, $y);
        $lumi = GetLumi( $rgb );
        if ($lumi > 128) $res = $res | 128;
    }
    return $res;
}

function GetImgWord ( $im, $bn, $y )
{
    $x = $bn * 8;
    $res = 0;
    for ($i=0; $i<8; $i++, $x++)
    {
        $res = $res >> 1;
        $rgb = imagecolorat($im, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($b > 127) $res = $res | 0x8000;
        if ($g > 127) $res = $res | 0x0080;
    }
    return $res;    
}

function ProcessFile ( $fname )
{
    global  $fout;
    $fnamebase = basename($fname);    
    $arr = explode(".", $fnamebase);
    $sname = strtoupper($arr[0]);
    echo "$fname - $sname\n";
    
    $stype = 0; // B&W
    if ($sname[0] == 'C') $stype = 1;

    $im = imagecreatefrompng($fname);
    $width  = imagesx($im);
    $width  = $width & 0xF8;    // by 8
    $height = imagesy($im);
    $byten  = $width >> 3;

    $barr = Array();
    $size = 0;
    // construct array
    for ($y = 0; $y < $height; $y++)
    {
        for ($bn = 0; $bn < $byten; $bn++)
        {
            if ($stype == 0) $b_bits = GetImgByte($im, $bn, $y);
                        else $b_bits = GetImgWord($im, $bn, $y);
            $barr[$y][$bn] = $b_bits;
            $size++;
        }
    }

    // out array
    fputs($fout, $sname . ":");
    if (strtolower(substr($sname, 0, 3)) != "skp") {
        if ($stype == 0) 
            fputs($fout, "\t.BYTE\t" . $byten . "., " . $height . ".");
        else
            fputs($fout, "\t.WORD\t" . $byten . "., " . $height . ".");
        fputs($fout, "\n");
    }

    // 
    $cn = 0;
    $cmax = 7;
    $dcnt = 0;
    for ($y=0; $y<count($barr); $y++) 
    {
        $l = count($barr[$y]);
        for ($k=0; $k<$l; $k++)
        {
            if ($cn == 0) {
                if ($stype == 0) fputs($fout, "\t.BYTE\t");
                            else fputs($fout, "\t.WORD\t");
            } else {
                fputs($fout, ", ");
            }
            $b = $barr[$y][$k];
            fputs($fout, decoct($b)); $dcnt++;
            if ($cn == $cmax) fputs($fout, "\n");
            $cn++; if ($cn > $cmax) $cn = 0;
        }
    }
    if (($stype == 0) && ($dcnt % 1)) fputs($fout, "\t.EVEN\n");
    if ($cn != 0) fputs($fout, "\n");
}

/////////////////////////////////////////////////////

    $fout = fopen("./graphics/SPR.TXT", "w");
    ProcessDir("./graphics/");
    fclose($fout);
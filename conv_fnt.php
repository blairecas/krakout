<?php

    $fnt_height = 6;
    $barr = Array();
    $minasc = 255;
    $maxasc = 0;    

    
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
    $files = FindFiles ($dir, '/^([fF]|[cC]).*\.([pP][nN][gG])$/');
    $count = count($files);
    for ($i=0; $i < $count; $i++) 
    {
        ProcessFile($files[$i]);
    }
    echo "Dir: $dir, Files: $count\n";
}


function GetLumi ( $rgb )
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $lumi = sqrt($r*$r + $g*$g + $b*$b);
    return $lumi;
}


function GetImgByte ( $im, $y )
{
    $x = 0;
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

function ProcessFile ( $fname )
{
    global  $barr, $minasc, $maxasc, $fnt_height;
    
    $fnamebase = basename($fname);
    $arr1 = explode(".", $fnamebase); 
    $arr2 = explode("_", $arr1[0]);
    $asc = hexdec($arr2[1]);
    if ($asc < $minasc) $minasc = $asc;
    if ($asc > $maxasc) $maxasc = $asc;
    
    $im = imagecreatefrompng($fname);
    $width  = imagesx($im);
    $height = imagesy($im);

    if ($width != 8 || $height != $fnt_height) {
        echo "ERR: $fname, size problems, must be 8x$fnt_height";
        die;
    }
    
    $barr[$asc] = Array();
    
    // construct array
    for ($y = 0; $y < $height; $y++)
    {
        $b_bits = GetImgByte($im, $y);
        $barr[$asc][$y] = $b_bits;
    }
}

function OutputFile ()
{
    global  $fout, $barr, $fnt_height;
    
    ksort($barr);
    fputs($fout, "FN6DAT:");
    foreach ($barr as $asc => $bchar)
    {
        for ($y=0; $y<$fnt_height; $y++) {
            if ($y == 0) fputs($fout, "\t.WORD\t");
            $b = $bchar[$y];
            $w = $b | ($b << 8);
            fputs($fout, decoct($w));
            if ($y < ($fnt_height-1)) fputs($fout, ", ");
            if ($y == ($fnt_height-1)) fputs($fout, "\n");
        }
    }
    
    fputs($fout, "\nFN6DA2:");
    foreach ($barr as $asc => $bchar)
    {
        for ($y=0; $y<$fnt_height; $y++) {
            if ($y == 0) fputs($fout, "\t.BYTE\t");
            $b = $bchar[$y];
            fputs($fout, decoct($b));
            if ($y < ($fnt_height-1)) fputs($fout, ", ");
            if ($y == ($fnt_height-1)) fputs($fout, "\n");
        }
    }
}

function OutputAddrArray ()
{
    global  $fout, $minasc, $maxasc, $fnt_height;
    $k=0; $ofs = 0;
    fputs($fout, "FN8OFS:");
    for ($asc=$minasc; $asc<=$maxasc; $asc++)
    {
        if ($k==0) fputs($fout, "\t.WORD\t");
        fputs($fout, decoct($ofs));
        if ($k<7) fputs($fout, ", ");
        if ($k==7) fputs($fout, "\n");
        $k++;
        if ($k>=8) $k=0;
        $ofs = $ofs + ($fnt_height*2);
    }
    fputs($fout, "\n");
    
    $k=0; $ofs = 0;
    fputs($fout, "\nFN8OF2:");
    for ($asc=$minasc; $asc<=$maxasc; $asc++)
    {
        if ($k==0) fputs($fout, "\t.WORD\t");
        fputs($fout, decoct($ofs));
        if ($k<7) fputs($fout, ", ");
        if ($k==7) fputs($fout, "\n");
        $k++;
        if ($k>=8) $k=0;
        $ofs = $ofs + $fnt_height;
    }
    fputs($fout, "\n");    
}

/////////////////////////////////////////////////////

    ProcessDir("./graphics/fnt/");
    $fout = fopen("./graphics/FNT.TXT", "w");
    OutputAddrArray();
    OutputFile();
    fclose($fout);
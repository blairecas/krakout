<?php    
    $prefix = 'krk';

    $allRAM = Array(
	'cpu'      => Array(),
	'cpustart' => 02000,	// CPU starting addr
	'cpuend'   => 0xFFFF,	// CPU ending addr
        'cpumax'   => 0,        // maximal used addr
	'ppu'	   => Array(),
	'ppustart' => 010000,
	'ppuend'   => 077777,
        'ppumax'   => 0
    );

    $lnum = 0;

    echo "\n";

    ProcessFile('cpu');
    ProcessFile('ppu');

    // writing to one "overlay" file
    echo "\n";
    $totalcount = 0;
    $fname = strtoupper($prefix . 'bin.dat');
    $fout = fopen($fname, "w");
    fwrite($fout, "0123456789ABCDEF");  // reserved 0x10 bytes
    echo "hdr :: 16 bytes written\n";
    $totalcount += 16;
    WriteSection($fout, 'cpu');
    WriteSection($fout, 'ppu');
    echo "total file size is $totalcount bytes\n";
    // align to block length (512 bytes)
    $newcount = ($totalcount + 0x200) & 0xFE00;
    $blockscount = $newcount/0x200;
    echo "aligning total size to $newcount bytes ($blockscount blocks)\n";
    for ($i=$totalcount; $i<$newcount; $i++) fwrite($fout, chr(0x00));
    fclose($fout);
    // put info
    $fout = fopen($fname, "rw+");
    fseek($fout, 0);
    WriteWord($fout, $blockscount);
    WriteWord($fout, intval((32*256) / $blockscount));
    WriteWord($fout, $allRAM['cpustart']);
    WriteWord($fout, intval(($allRAM['cpumax']-$allRAM['cpustart']+1)/2));
    WriteWord($fout, $allRAM['ppustart']);
    WriteWord($fout, intval(($allRAM['ppumax']-$allRAM['ppustart']+1)/2));
    WriteWord($fout, 0x0000);
    WriteWord($fout, 0x0000);
    
//////////////////////////////////////////////////////////////////////////////////////////////////

function ProcessFile ( $processor )
{
    global $prefix, $allRAM;
    $fname = strtoupper($prefix . $processor . '.lst');    
    echo "processing $fname\n";
    $lcount = 0;
    $fin = fopen($fname, "r");
    if ($fin === false) {
        echo "ERROR: file $fname not found\n";
        die;
    }
    // skip 3 lines
    fgets($fin);
    fgets($fin);
    fgets($fin);
    while (!feof($fin))
    {
        $s = fgets($fin);
        $b = UseLine($s, $processor);
        if (!$b) break;
        $lcount++;
    }
    fclose($fin);    
    echo "used $lcount lines\n";
    // align max bytes
    $max = $allRAM[$processor.'max'];
    $max = (($max + 0x20) & 0xFFF0) - 1;
    $allRAM[$processor.'max'] = $max;
}


function UseLine ( $sline, $processor )
{
    global $lnum;
    
    // empty string?
    $sline = rtrim($sline); if (strlen($sline)==0) return true;
    // assume 'Symbol table' as end
    if (strcasecmp($sline, "Symbol table") == 0) return false;
    // first character
    $fc = ord($sline[0]);
    // it's a page description - skip it
    if ($fc == 0x0C) return true;
    // no line number
    $lnum = 0;
    if ($fc == 0x09) $sline = substr($sline, 1);
                else $sline = GetLineNumber($sline, $lnum);
    // try to get addr
    $gAddr = 0; $type0 = -1;
    $sline = GetOctal($sline, $gAddr, $type0);
    if ($type0 < 0) {
        echo "ERROR: in ADDR on $lnum\n";
        die;
    }
    if ($gAddr == 0) return true;
    // now trying to get three octals
    $oct1 = 0; $type1 = -1; $sline = GetOctal($sline, $oct1, $type1);
    $oct2 = 0; $type2 = -1; $sline = GetOctal($sline, $oct2, $type2);
    $oct3 = 0; $type3 = -1; $sline = GetOctal($sline, $oct3, $type3);
    if ($type1 < 0 || $type2 < 0 || $type3 < 0) {
        echo "ERROR: in DATA on $lnum\n";
        die;
    }
    if ($type1==0 && $type2==0 && $type3==0) return true;
    if ($type1==0 && ($type2>0 || $type3>0)) {
        echo "ERROR: in DATA on $lnum\n";
        die;
    }
    if ($type2==0 && $type3>0) {
        echo "ERROR: in DATA on $lnum\n";
        die;
    }    
    // DEBUG: echo decoct($gAddr)."-".$type0."\t\t".decoct($oct1)."-".$type1."\t\t".decoct($oct2)."-".$type2."\t\t".decoct($oct3)."-".$type3."\n";
    // now we have addr and up to three octals
    $gAddr = PutBytes($gAddr, $oct1, $type1, $processor);
    $gAddr = PutBytes($gAddr, $oct2, $type2, $processor);
    $gAddr = PutBytes($gAddr, $oct3, $type3, $processor);
    return true;
}


function GetLineNumber ($s, &$lnum)
{
    $s1 = trim(substr($s, 0, 8));
    $lnum = intval($s1, 10);
    return substr($s, 8);
}


function GetOctal ( $s, &$num, &$type )
{
    $l = 0;
    $sbuf = "";
    while ($l<8 && strlen($s) > 0)
    {
        $fc = ord($s[0]);
        if ($fc == 0x09) { $l = (($l+8) >> 3) << 3; $s = substr($s, 1); break; }
        if ($fc == 0x20) { $l++; $s = substr($s, 1); continue; }
        if ($fc < 0x30 || $fc > 0x37) { $type = -1; return ""; }
        $sbuf .= chr($fc);
        $s = substr($s, 1);
        $l++;
    }
    if (strlen($sbuf) == 0) {
        $type = 0;
        return $s;
    }
    $type = 1; if (strlen($sbuf) > 3) $type = 2;
    $num = octdec($sbuf);
    return $s;
}


function PutBytes ($adr, $w, $type, $proc)
{
    global $allRAM, $lnum;
    
    if (($adr < $allRAM[$proc.'start']) || ($adr > $allRAM[$proc.'end'])) {
        echo "ERROR: address $adr is out of range on line $lnum\n";
        die;
    }
    // type == 0 - don't use this
    if ($type == 0) return $adr;
    // type == 1 - its a byte
    if ($type == 1) { 
        $allRAM[$proc][$adr] = $w & 0xFF;
        if ($adr > $allRAM[$proc.'max']) $allRAM[$proc.'max'] = $adr;
        return ($adr+1); // return next addr
    }
    // type == 2 - its a word
    if ($type == 2) {
        $allRAM[$proc][$adr] = $w & 0xFF;
        $allRAM[$proc][$adr+1] = ($w>>8) & 0xFF;
        if (($adr+1) > $allRAM[$proc.'max']) $allRAM[$proc.'max'] = ($adr+1);
        return ($adr+2);
    }
    echo "ERROR in PutBytes() $adr $w $type on line $lnum\n";
    die;
}


function WriteSection ($g, $proc)
{
    global $allRAM, $totalcount;
    $start = $allRAM[$proc.'start'];
    $length = $allRAM[$proc.'max'] - $start + 1;
    $count = 0;
    for ($i=$start; $i<($start+$length); $i++)
    {
        $byte = 0x00;
        if (isset($allRAM[$proc][$i])) $byte = $allRAM[$proc][$i];
        $s = chr($byte);
        fwrite($g, $s, 1);
        $count++;
    }
    echo "$proc :: $count bytes written\n";
    $totalcount += $count;
}


function WriteWord ($g, $w)
{
    $w = $w & 0xFFFF;
    $b1 = $w & 0xFF;
    $b2 = ($w & 0xFF00) >> 8;
    fwrite($g, chr($b1));
    fwrite($g, chr($b2));
}

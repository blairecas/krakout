<?php
	$fout = fopen("./work/krk_tbl.txt", "w");

    // lev x(low byte), y(high byte)
    fputs($fout, "LEVELX:\n");
    for ($y=0; $y<12; $y++) {
        fputs($fout, "\t.BYTE\t");
        for ($x=0; $x<16; $x++)
        {
            $_tx = (4+$x*2);
            $_ty = (48+$y*16);
            fputs($fout, "".decoct($_tx).",".decoct($_ty));
            if ($x<15) fputs($fout, ", ");
        }
        fputs($fout, "\n");
    }
    
	// alpha rot
	$alph = 0;
    $i = 0;
    fputs($fout, "TBALPH:");
	for ($alph=0; $alph<128; $alph++)
	{   
        $dalph = $alph * 2.0 * pi() / 128.0;
        $dx = cos($dalph);
        $dy = sin($dalph);
        $idx = intval($dx * 64);
        $idy = intval($dy * 64);
        if ($i == 0) fputs($fout, "\t.WORD\t");
        if ($i > 0 && $i <= 8) fputs($fout, ", ");
        fputs($fout, "".$idx.".,".$idy.".");
        if ($i == 8) { fputs($fout, "\n"); $i=0; } else $i++;        
	}
    fputs($fout, "\n");
    
    // line addr
    $k = 0;
    $va = 32768;
    fputs($fout, "LINEAD:\n");
    for ($y=0; $y<288; $y++)
    {
        if ($k==0) fputs($fout, "\t.WORD\t");
        fputs($fout, "".decoct($va));
        if ($k<9) fputs($fout, ",");
        if ($k==9) fputs($fout, "\n");
        $k++; if ($k > 9) $k=0;
        $va += 80;
    }
    fputs($fout, "\n");
    
    // kub2va
    fputs($fout, "KUB2VA:\n");
    $k = 0;
    for ($y=0; $y<12; $y++) {
        for ($x=0; $x<16; $x++) {
            $kcoord = $y*16 + $x;
            $vy = $y*16 + 48;
            $vx = $x*16 + 32;
            $va = 32768 + $vy*80 + ($vx>>3);
            if ($k==0) fputs($fout, "\t.WORD\t");
            fputs($fout, "".decoct($va));
            if ($k<9) fputs($fout, ",");
            if ($k==9) fputs($fout, "\n");
            $k++; if ($k > 9) $k=0;
        }
    }
    
    
    
    fclose($fout);

<?php
	$f = fopen("tables.txt", "w");

	// vlines table
	fputs($f, "\nVLinesTable:\n");
	$vaddr = 0100000;
	$paddr_start = 01130;
	$paddr = $paddr_start + 4;
	$col = 0;
	$count = 288;
	$vaddr_addition = 80;
	for ($i=0; $i<$count; $i++)
	{
		if ($col == 0) fputs($f, "\t.word\t");
		fputs($f, decoct($vaddr) . "," . decoct($paddr));
		$vaddr += $vaddr_addition;
		$paddr += 4;
		if ($col != 15  && $i < ($count-1)) { fputs($f, ", "); $col++; } else { fputs($f, "\n"); $col = 0; }
	}
	fputs($f, "\t.word\t" . decoct($vaddr) . "," . decoct($paddr-4) . "\n");
	$paddr +=4;
	// second table 
	fputs($f, "\nVLinesTable2:\n");
	$vaddr = 0100050;
	$col = 0;
	$count = 288;
	$vaddr_addition = 80;
	for ($i=0; $i<$count; $i++)
	{
		if ($col == 0) fputs($f, "\t.word\t");
		fputs($f, decoct($vaddr) . "," . decoct($paddr));
		$vaddr += $vaddr_addition;
		$paddr += 4;
		if ($col != 15  && $i < ($count-1)) { fputs($f, ", "); $col++; } else { fputs($f, "\n"); $col = 0; }
	}
	fputs($f, "\t.word\t" . decoct($vaddr) . "," . decoct($paddr-4) . "\n");

	fclose($f); 

<?php
// scratch/test_gs.php
$temp1 = __DIR__ . '/test1.pdf';
$temp2 = __DIR__ . '/test2.pdf';

// create dummy pdfs
file_put_contents($temp1, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n188\n%%EOF\n");

file_put_contents($temp2, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n188\n%%EOF\n");

$files = [$temp1, $temp2];

// Test with escapeshellarg (single quotes)
$list1 = __DIR__ . '/list1.txt';
file_put_contents($list1, implode("\n", array_map('escapeshellarg', $files)));

// Test without quotes
$list2 = __DIR__ . '/list2.txt';
file_put_contents($list2, implode("\n", $files));

$out1 = __DIR__ . '/out1.pdf';
$out2 = __DIR__ . '/out2.pdf';

echo "Run with escapeshellarg:\n";
echo shell_exec("gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($out1) . " @" . escapeshellarg($list1) . " 2>&1");
echo "\nSize of out1: " . (file_exists($out1) ? filesize($out1) : 'not found') . "\n";

echo "Run without quotes:\n";
echo shell_exec("gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($out2) . " @" . escapeshellarg($list2) . " 2>&1");
echo "\nSize of out2: " . (file_exists($out2) ? filesize($out2) : 'not found') . "\n";

unlink($temp1);
unlink($temp2);
unlink($list1);
unlink($list2);
if (file_exists($out1)) unlink($out1);
if (file_exists($out2)) unlink($out2);

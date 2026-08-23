<!DOCTYPE html>
<html><head><title>Cek Path</title></head><body style="font-family:monospace;padding:40px">
<h2>📍 Diagnostic Root</h2>
<p>CWD: <b><?php echo getcwd(); ?></b></p>
<p>__DIR__: <b><?php echo __DIR__; ?></b></p>
<p>Document Root: <b><?php echo $_SERVER['DOCUMENT_ROOT']; ?></b></p>
<p>Script: <b><?php echo $_SERVER['SCRIPT_FILENAME']; ?></b></p>
<hr>
<h3>Folder yang ditemukan:</h3>
<pre><?php
foreach(scandir(__DIR__) as $f) {
    if($f !== '.' && $f !== '..') {
        $type = is_dir($f) ? '📁' : '📄';
        echo "$type $f\n";
    }
}
?></pre>
</body></html>
